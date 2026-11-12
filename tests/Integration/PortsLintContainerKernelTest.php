<?php

declare(strict_types=1);

namespace Nowo\GoogleTranslatePhpBundle\Tests\Integration;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Nowo\GoogleTranslatePhpBundle\Contract\GoogleTranslatorInterface;
use Nowo\GoogleTranslatePhpBundle\Tests\Integration\Fixtures\Ports\BothPortsConsumer;
use Nowo\GoogleTranslatePhpBundle\Tests\Integration\Fixtures\Ports\DddGoogleTextTranslator;
use Nowo\GoogleTranslatePhpBundle\Tests\Integration\Fixtures\Ports\LegacyPortConsumer;
use Nowo\GoogleTranslatePhpBundle\Tests\Integration\Fixtures\Ports\NewPortConsumer;
use Nowo\GoogleTranslatePhpBundle\Tests\Integration\Fixtures\Ports\ProfilePortConsumer;
use Nowo\GoogleTranslatePhpBundle\Tests\Integration\Fixtures\PortsTestKernel;
use Nowo\GoogleTranslatePhpBundle\Translator\WorkerSafeGoogleTranslate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Stichoza\GoogleTranslate\GoogleTranslate;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;

use const E_USER_DEPRECATED;

/**
 * 1.4: `lint:container` passes with host services on the new port only, on the deprecated vendor-class alias only, and
 * on both; every one of them gets the default profile's translator, and only the vendor-class alias reports a
 * deprecation. `GoogleTranslatorInterface $marketingTranslator` selects the marketing profile.
 */
final class PortsLintContainerKernelTest extends TestCase
{
    private string $baseDir;

    private ?PortsTestKernel $kernel = null;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/nowo-gtp-ports-' . uniqid();
    }

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        (new Filesystem())->remove($this->baseDir);
    }

    /**
     * @return iterable<string, array{class-string, bool}>
     */
    public static function hostServices(): iterable
    {
        yield 'new port only' => [NewPortConsumer::class, false];
        yield 'DDD host adapter on the new port' => [DddGoogleTextTranslator::class, false];
        yield 'deprecated vendor class only' => [LegacyPortConsumer::class, true];
        yield 'both' => [BothPortsConsumer::class, true];
    }

    /**
     * @param class-string<BothPortsConsumer|DddGoogleTextTranslator|LegacyPortConsumer|NewPortConsumer> $consumerClass
     */
    #[DataProvider('hostServices')]
    public function testLintContainerPassesAndTheHostGetsTheDefaultProfile(string $consumerClass, bool $expectDeprecation): void
    {
        $deprecations = [];
        // @phpstan-ignore frankenphp.worker.noSetErrorExceptionHandler (test only; restored in finally)
        set_error_handler(static function (int $type, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        }, E_USER_DEPRECATED);
        try {
            $this->kernel = new PortsTestKernel($this->baseDir, [$consumerClass, ProfilePortConsumer::class]);
            $this->kernel->boot();
            $application = new Application($this->kernel);
            $application->setAutoExit(false);
            $lint = new CommandTester($application->find('lint:container'));
            $exit = $lint->execute([]);
        } finally {
            restore_error_handler();
        }

        self::assertSame(0, $exit, $lint->getDisplay());
        self::assertStringContainsString('linted successfully', $lint->getDisplay());

        $aliasDeprecations = array_filter($deprecations, static fn (string $message): bool => str_contains($message, 'Stichoza\GoogleTranslate\GoogleTranslate" autowiring alias is deprecated'));
        self::assertSame($expectDeprecation, $aliasDeprecations !== [], implode("\n", $deprecations));

        $container     = $this->kernel->getContainer();
        $testContainer = $container->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $testContainer);
        $default   = $testContainer->get('nowo_google_translate_php.translator.default');
        $marketing = $testContainer->get('nowo_google_translate_php.translator.marketing');
        self::assertInstanceOf(WorkerSafeGoogleTranslate::class, $default);
        self::assertInstanceOf(GoogleTranslatorInterface::class, $marketing);
        self::assertNotSame($default, $marketing);

        $consumer = $container->get($consumerClass);
        self::assertInstanceOf($consumerClass, $consumer);
        if ($consumer instanceof DddGoogleTextTranslator) {
            // The adapter translates through the port and maps a failure without any vendor type.
            (new ReflectionProperty(GoogleTranslate::class, 'client'))->setValue($default, new Client(['handler' => HandlerStack::create(new MockHandler([
                new Response(200, [], '[[["Hola","Hello",null,null,10]],null,"en"]'),
                new Response(429, [], 'slow down'),
            ]))]));
            self::assertSame('Hola', $consumer->translate('Hello', 'es'));
            self::assertNull($consumer->translate('Hello', 'fr'));
            self::assertSame('en', (new ReflectionProperty(GoogleTranslate::class, 'target'))->getValue($default), 'the shared default profile is unchanged');

            return;
        }
        self::assertSame($default, $consumer->translator);
        if ($consumer instanceof BothPortsConsumer) {
            self::assertSame($default, $consumer->legacy);
        }

        $profile = $container->get(ProfilePortConsumer::class);
        self::assertInstanceOf(ProfilePortConsumer::class, $profile);
        self::assertSame($marketing, $profile->marketingTranslator);
    }
}
