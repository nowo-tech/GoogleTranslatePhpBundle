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
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
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
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Event\WorkerStoppedEvent;
use Symfony\Component\Messenger\EventListener\ResetServicesListener;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Worker;
use Throwable;

use function is_array;
use function json_encode;
use function sprintf;

use const PHP_INT_MIN;

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
     * Audits NM / NL2-v / NL2-v2 through a real console Application in the kept-alive kernel: (a) a nested command whose
     * terminate listener throws, (c) an unknown nested command and (d) a nested command whose console.command is stopped
     * above 4096 never reset the outer command's settings mid-run; a self-recursive command whose error listener
     * throws, an error listener at PHP_INT_MIN that throws, a throwing WorkerMessageReceivedEvent listener inside a
     * command and a failing command inside a message (then message failed + worker stopped) never keep the process
     * inside a unit. Every outermost command still ends at the defaults, twice in a row.
     */
    public function testSkippedConsoleEventsAndFailedRunsNeverUnbalanceTheBoundaries(): void
    {
        self::bootKernel(['debug' => false]);
        $translator = $this->translator();
        $dispatcher = $this->dispatcher();
        $throwing   = static function (): never {
            throw new RuntimeException('listener failed');
        };
        $throwingInnerTerminate = static function (ConsoleTerminateEvent $event): void {
            if ($event->getCommand()?->getName() === 'app:inner') {
                throw new RuntimeException('terminate listener failed');
            }
        };
        $stoppingInnerCommand = static function (ConsoleCommandEvent $event): void {
            if ($event->getCommand()?->getName() === 'app:inner') {
                $event->stopPropagation();
            }
        };
        $application = $this->application();
        $depth       = 0;
        $recursive   = (new Command('app:rec'))->setCode(static function (InputInterface $input, OutputInterface $output) use ($application, $dispatcher, $throwing, &$depth): int {
            if (++$depth > 1) {
                throw new RuntimeException('inner run failed');
            }
            $dispatcher->addListener(ConsoleEvents::ERROR, $throwing);
            try {
                $application->run(new ArrayInput(['command' => 'app:rec']), $output);
            } catch (Throwable) {
            } finally {
                $dispatcher->removeListener(ConsoleEvents::ERROR, $throwing);
            }

            return Command::SUCCESS;
        });
        $failing = (new Command('app:fail'))->setCode(static function (): never {
            throw new RuntimeException('command failed');
        });
        $consume = (new Command('app:consume'))->setCode(static function () use ($dispatcher, $throwing): int {
            $dispatcher->addListener(WorkerMessageReceivedEvent::class, $throwing);
            try {
                $dispatcher->dispatch(new WorkerMessageReceivedEvent(new Envelope(new TenantTranslation('A', 0)), 'async'));
            } catch (RuntimeException) {
            } finally {
                $dispatcher->removeListener(WorkerMessageReceivedEvent::class, $throwing);
            }

            return Command::SUCCESS;
        });
        $application->setCommandLoader(new FactoryCommandLoader([
            'app:rec'     => static fn (): Command => $recursive,
            'app:fail'    => static fn (): Command => $failing,
            'app:consume' => static fn (): Command => $consume,
        ]));

        $nestedCases = [
            'throwing terminate listener' => [ConsoleEvents::TERMINATE, $throwingInnerTerminate, 0, 'app:inner'],
            'unknown nested command'      => [ConsoleEvents::COMMAND, null, 0, 'app:does-not-exist'],
            'stopped console.command'     => [ConsoleEvents::COMMAND, $stoppingInnerCommand, 10000, 'app:inner'],
        ];
        foreach ($nestedCases as $case => [$event, $listener, $priority, $nested]) {
            if ($listener !== null) {
                $dispatcher->addListener($event, $listener, $priority);
            }
            try {
                for ($run = 0; $run < 2; ++$run) {
                    self::assertSame(['target' => 'fr', 'source' => 'de', 'proxy' => null], $this->runOuterWithNestedRun($translator, $nested), $case);
                    $this->assertTranslatorAtDefaults($translator, $case);
                }
            } finally {
                if ($listener !== null) {
                    $dispatcher->removeListener($event, $listener);
                }
            }
        }

        $probes = [
            'self-recursive command, throwing error listener' => static function () use ($application): void {
                $application->run(new ArrayInput(['command' => 'app:rec']), new NullOutput());
            },
            'error listener at PHP_INT_MIN throwing' => static function () use ($application, $dispatcher, $throwing): void {
                $dispatcher->addListener(ConsoleEvents::ERROR, $throwing, PHP_INT_MIN);
                try {
                    $application->run(new ArrayInput(['command' => 'app:fail']), new NullOutput());
                } catch (RuntimeException) {
                } finally {
                    $dispatcher->removeListener(ConsoleEvents::ERROR, $throwing);
                }
            },
            'throwing received listener inside a command' => static function () use ($application): void {
                $application->run(new ArrayInput(['command' => 'app:consume']), new NullOutput());
            },
            'failing command inside a message, worker stopped' => function () use ($application, $dispatcher, $throwing): void {
                $envelope = new Envelope(new TenantTranslation('A', 0, true));
                $dispatcher->dispatch(new WorkerMessageReceivedEvent($envelope, 'async'));
                $dispatcher->addListener(ConsoleEvents::ERROR, $throwing);
                try {
                    $application->run(new ArrayInput(['command' => 'app:fail']), new NullOutput());
                } catch (RuntimeException) {
                } finally {
                    $dispatcher->removeListener(ConsoleEvents::ERROR, $throwing);
                }
                $dispatcher->dispatch(new WorkerMessageFailedEvent($envelope, 'async', new RuntimeException('handler failed')));
                $dispatcher->dispatch(new WorkerStoppedEvent($this->createStub(Worker::class)));
            },
        ];
        foreach ($probes as $probe => $run) {
            $depth = 0;
            $run();
            self::assertSame(['target' => 'fr', 'source' => 'de', 'proxy' => null], $this->runOuterWithNestedRun($translator, 'app:inner'), $probe);
            $this->assertTranslatorAtDefaults($translator, $probe);
        }
    }

    private function application(): Application
    {
        $application = new Application('worker-test');
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $application->setDispatcher($this->dispatcher());

        return $application;
    }

    /**
     * Runs app:outer through a real console Application: it configures the translator, runs $nested in-process (a
     * failure of the nested run is swallowed) and returns the translator state right after the nested run.
     *
     * @return array<string, mixed>
     */
    private function runOuterWithNestedRun(WorkerSafeGoogleTranslate $translator, string $nested): array
    {
        $application = $this->application();
        $seen        = [];
        $inner       = (new Command('app:inner'))->setCode(static fn (): int => Command::SUCCESS);
        $outer       = (new Command('app:outer'))->setCode(function (InputInterface $input, OutputInterface $output) use ($application, $translator, $nested, &$seen): int {
            $translator->setTarget('fr')->setSource('de');
            try {
                $application->doRun(new ArrayInput(['command' => $nested]), $output);
            } catch (Throwable) {
                // The nested run failed (throwing listener, unknown command); the outer command goes on.
            }
            $seen = $this->state($translator);

            return Command::SUCCESS;
        });
        $application->setCommandLoader(new FactoryCommandLoader([
            'app:outer' => static fn (): Command => $outer,
            'app:inner' => static fn (): Command => $inner,
        ]));

        self::assertSame(Command::SUCCESS, $application->run(new ArrayInput(['command' => 'app:outer']), new NullOutput()));
        self::assertNotSame([], $seen);

        return $seen;
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
