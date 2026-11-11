<?php

declare(strict_types=1);

namespace Nowo\GoogleTranslatePhpBundle\Tests\Integration;

use Nowo\GoogleTranslatePhpBundle\Tests\Integration\Fixtures\TenantReceiver;
use Nowo\GoogleTranslatePhpBundle\Tests\Integration\Fixtures\TenantTranslation;
use Nowo\GoogleTranslatePhpBundle\Tests\Integration\Fixtures\WorkerTestKernel;
use Nowo\GoogleTranslatePhpBundle\Translator\WorkerSafeGoogleTranslate;
use ReflectionProperty;
use RuntimeException;
use Stichoza\GoogleTranslate\GoogleTranslate;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\CommandLoader\FactoryCommandLoader;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\EventListener\ResetServicesListener;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Worker;

use function is_array;
use function json_encode;
use function sprintf;

/**
 * Kept-alive kernel, services resetter never called (FRANKENPHP_RESET_KERNEL=false with the resetter removed or
 * bypassed, `messenger:consume --no-reset`, long-running console processes): one booted kernel, the same translator
 * instance, many units of work for two tenants in alternation.
 *
 * Each unit first checks that the shared translator starts at the profile defaults, then reconfigures it with its own
 * tenant's target, source and Guzzle proxy, as host code that calls the fluent setters would. A unit that starts with
 * the other tenant's settings is a leak.
 *
 * N = 500 per path (HTTP, Messenger with a real Worker and no ResetServicesListener, console Application runs).
 * Memory margin: 1 MiB after a 50-unit warm-up.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class WorkerBoundariesKernelTest extends KernelTestCase
{
    private const ITERATIONS    = 500;
    private const WARMUP        = 50;
    private const MEMORY_MARGIN = 1024 * 1024;

    /** @var array<string, array{target: string, source: string, proxy: string}> */
    private const TENANTS = [
        'A' => ['target' => 'fr', 'source' => 'de', 'proxy' => 'socks5://tenant-a.example:1080'],
        'B' => ['target' => 'es', 'source' => 'it', 'proxy' => 'socks5://tenant-b.example:1080'],
    ];

    /** @var list<string> */
    private array $leaks = [];

    private int $memoryAtWarmup = 0;

    private int $memoryAtEnd = 0;

    protected static function getKernelClass(): string
    {
        return WorkerTestKernel::class;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->leaks = [];
    }

    public function testManyMainRequestsForTwoTenantsStartFromTheProfileDefaults(): void
    {
        self::bootKernel(['debug' => false]);
        $translator = $this->translator();
        $dispatcher = $this->dispatcher();
        $kernel     = self::$kernel;
        self::assertInstanceOf(HttpKernelInterface::class, $kernel);

        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            $request = Request::create('/translate/' . $this->tenantFor($i));
            $request->attributes->set('_controller', 'already_routed');
            $dispatcher->dispatch(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST), KernelEvents::REQUEST);
            $this->unitOfWork($translator, $this->tenantFor($i), $i);
        }

        $this->assertNoLeakAndFlatMemory('HTTP requests');
    }

    public function testManyMessengerMessagesForTwoTenantsStartFromTheProfileDefaults(): void
    {
        self::bootKernel(['debug' => false]);
        $translator = $this->translator();
        $dispatcher = $this->dispatcher();

        foreach ($dispatcher->getListeners(WorkerRunningEvent::class) as $listener) {
            self::assertFalse(
                is_array($listener) && $listener[0] instanceof ResetServicesListener,
                'The worker must run without ResetServicesListener (messenger:consume --no-reset).',
            );
        }

        $receiver = new TenantReceiver(self::ITERATIONS);

        $bus = new MessageBus([new HandleMessageMiddleware(new HandlersLocator([
            TenantTranslation::class => [function (TenantTranslation $message) use ($translator): void {
                $this->unitOfWork($translator, $message->tenant, $message->index);
                if ($message->fail) {
                    throw new RuntimeException('Handler failed for tenant ' . $message->tenant);
                }
            }],
        ]))]);

        $worker     = new Worker(['async' => $receiver], $bus, $dispatcher);
        $stopOnIdle = static function (WorkerRunningEvent $event): void {
            if ($event->isWorkerIdle()) {
                $event->getWorker()->stop();
            }
        };
        $dispatcher->addListener(WorkerRunningEvent::class, $stopOnIdle);

        try {
            $worker->run(['sleep' => 0]);
        } finally {
            $dispatcher->removeListener(WorkerRunningEvent::class, $stopOnIdle);
        }

        self::assertSame(self::ITERATIONS, $receiver->acknowledged + $receiver->rejected);
        self::assertGreaterThan(0, $receiver->rejected);
        $this->assertNoLeakAndFlatMemory('Messenger messages');
        $this->assertTranslatorAtDefaults($translator, 'after the last WorkerMessageHandled / Failed');
    }

    public function testManyConsoleCommandRunsForTwoTenantsStartFromTheProfileDefaults(): void
    {
        self::bootKernel(['debug' => false]);
        $translator = $this->translator();

        $command = (new Command('app:translate-tenant'))
            ->addArgument('tenant', InputArgument::REQUIRED)
            ->addArgument('index', InputArgument::REQUIRED)
            ->setCode(function (InputInterface $input, OutputInterface $output) use ($translator): int {
                $this->unitOfWork($translator, (string) $input->getArgument('tenant'), (int) $input->getArgument('index'));

                return Command::SUCCESS;
            });

        $application = new Application('worker-test');
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $application->setDispatcher($this->dispatcher());
        $application->setCommandLoader(new FactoryCommandLoader(['app:translate-tenant' => static fn (): Command => $command]));

        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            $exitCode = $application->run(
                new ArrayInput(['command' => 'app:translate-tenant', 'tenant' => $this->tenantFor($i), 'index' => (string) $i]),
                new NullOutput(),
            );
            self::assertSame(Command::SUCCESS, $exitCode);
            $this->assertTranslatorAtDefaults($translator, sprintf('after console.terminate of run %d', $i));
        }

        $this->assertNoLeakAndFlatMemory('console command runs');
    }

    /**
     * MI-G-L1: a command run in-process from another command (Application::doRun()) fires console.command /
     * console.terminate, but must not reset the profile the outer command configured; the outermost command still
     * starts and ends at the defaults.
     */
    public function testNestedCommandKeepsTheOuterCommandsSettings(): void
    {
        self::bootKernel(['debug' => false]);
        $translator  = $this->translator();
        $application = new Application('worker-test');
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $application->setDispatcher($this->dispatcher());

        $seenAfterInner = [];
        $inner          = (new Command('app:inner'))->setCode(static fn (): int => Command::SUCCESS);
        $outer          = (new Command('app:outer'))->setCode(function (InputInterface $input, OutputInterface $output) use ($application, $translator, &$seenAfterInner): int {
            $translator->setTarget('fr')->setSource('de');
            $application->doRun(new ArrayInput(['command' => 'app:inner']), $output);
            $application->doRun(new ArrayInput(['command' => 'app:inner']), $output);
            $seenAfterInner = $this->state($translator);

            return Command::SUCCESS;
        });
        $application->setCommandLoader(new FactoryCommandLoader([
            'app:outer' => static fn (): Command => $outer,
            'app:inner' => static fn (): Command => $inner,
        ]));

        for ($i = 0; $i < 2; ++$i) {
            self::assertSame(Command::SUCCESS, $application->run(new ArrayInput(['command' => 'app:outer']), new NullOutput()));
            self::assertSame(['target' => 'fr', 'source' => 'de', 'proxy' => null], $seenAfterInner, 'A nested command reset the outer command\'s settings.');
            $this->assertTranslatorAtDefaults($translator, 'after the outermost console.terminate');
        }
    }

    /**
     * MI-G-L1: a command run in-process from a Messenger handler must not reset the handler's settings; the message
     * boundaries still reset them.
     */
    public function testCommandRunFromAMessageHandlerKeepsTheHandlersSettings(): void
    {
        self::bootKernel(['debug' => false]);
        $translator  = $this->translator();
        $dispatcher  = $this->dispatcher();
        $application = new Application('worker-test');
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $application->setDispatcher($dispatcher);
        $inner = (new Command('app:inner'))->setCode(static fn (): int => Command::SUCCESS);
        $application->setCommandLoader(new FactoryCommandLoader(['app:inner' => static fn (): Command => $inner]));

        $seenAfterCommand = [];
        $bus              = new MessageBus([new HandleMessageMiddleware(new HandlersLocator([
            TenantTranslation::class => [function (TenantTranslation $message) use ($application, $translator, &$seenAfterCommand): void {
                $translator->setTarget('fr')->setSource('de');
                $application->doRun(new ArrayInput(['command' => 'app:inner']), new NullOutput());
                $seenAfterCommand[] = $this->state($translator);
            }],
        ]))]);

        $worker     = new Worker(['async' => new TenantReceiver(4)], $bus, $dispatcher);
        $stopOnIdle = static function (WorkerRunningEvent $event): void {
            if ($event->isWorkerIdle()) {
                $event->getWorker()->stop();
            }
        };
        $dispatcher->addListener(WorkerRunningEvent::class, $stopOnIdle);

        try {
            $worker->run(['sleep' => 0]);
        } finally {
            $dispatcher->removeListener(WorkerRunningEvent::class, $stopOnIdle);
        }

        self::assertCount(4, $seenAfterCommand);
        foreach ($seenAfterCommand as $seen) {
            self::assertSame(['target' => 'fr', 'source' => 'de', 'proxy' => null], $seen, 'A command run from the handler reset its settings.');
        }
        $this->assertTranslatorAtDefaults($translator, 'after the last message');

        // After the worker stopped, a standalone command resets again.
        $translator->setTarget('it');
        $application->run(new ArrayInput(['command' => 'app:inner']), new NullOutput());
        $this->assertTranslatorAtDefaults($translator, 'after a standalone command once the worker stopped');
    }

    /**
     * One unit of work for $tenant: the shared translator must be at the profile defaults, then the unit applies its
     * own per-tenant settings (left in place on purpose, as host code would).
     */
    private function unitOfWork(WorkerSafeGoogleTranslate $translator, string $tenant, int $index): void
    {
        if ($index === self::WARMUP) {
            gc_collect_cycles();
            $this->memoryAtWarmup = memory_get_usage();
        }

        $state = $this->state($translator);
        if ($state !== ['target' => 'en', 'source' => 'auto', 'proxy' => null]) {
            $this->leaks[] = sprintf('unit %d (tenant %s) started with %s', $index, $tenant, json_encode($state));
        }

        $settings = self::TENANTS[$tenant];
        $translator->setTarget($settings['target'])->setSource($settings['source'])->preserveParameters(true);
        $translator->setOptions(['proxy' => $settings['proxy'], 'timeout' => 10.0, 'connect_timeout' => 5.0]);

        if ($index === self::ITERATIONS - 1) {
            gc_collect_cycles();
            $this->memoryAtEnd = memory_get_usage();
        }
    }

    private function assertNoLeakAndFlatMemory(string $path): void
    {
        self::assertSame([], $this->leaks, sprintf('Translator settings leaked between %s.', $path));
        self::assertGreaterThan(0, $this->memoryAtWarmup);
        self::assertLessThan(self::MEMORY_MARGIN, $this->memoryAtEnd - $this->memoryAtWarmup, sprintf('Memory grew across %s.', $path));
    }

    private function assertTranslatorAtDefaults(WorkerSafeGoogleTranslate $translator, string $when): void
    {
        self::assertSame(['target' => 'en', 'source' => 'auto', 'proxy' => null], $this->state($translator), 'Translator not at the profile defaults ' . $when);
    }

    /**
     * @return array{target: mixed, source: mixed, proxy: mixed}
     */
    private function state(WorkerSafeGoogleTranslate $translator): array
    {
        $read = static fn (string $name): mixed => (new ReflectionProperty(GoogleTranslate::class, $name))->getValue($translator);

        /** @var array<string, mixed> $options */
        $options = $read('options');

        return ['target' => $read('target'), 'source' => $read('source'), 'proxy' => $options['proxy'] ?? null];
    }

    private function tenantFor(int $index): string
    {
        return $index % 2 === 0 ? 'A' : 'B';
    }

    private function translator(): WorkerSafeGoogleTranslate
    {
        $translator = self::getContainer()->get(WorkerTestKernel::TRANSLATOR);
        self::assertInstanceOf(WorkerSafeGoogleTranslate::class, $translator);

        return $translator;
    }

    private function dispatcher(): EventDispatcherInterface
    {
        $dispatcher = self::getContainer()->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        return $dispatcher;
    }
}
