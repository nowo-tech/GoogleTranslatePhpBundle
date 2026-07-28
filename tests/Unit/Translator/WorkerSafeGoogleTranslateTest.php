<?php

declare(strict_types=1);

namespace Nowo\GoogleTranslatePhpBundle\Tests\Unit\Translator;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use Nowo\GoogleTranslatePhpBundle\Translator\WorkerSafeGoogleTranslate;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use ReflectionMethod;
use ReflectionProperty;
use Stichoza\GoogleTranslate\Exceptions\TranslationRequestException;
use Stichoza\GoogleTranslate\GoogleTranslate;
use Stringable;

use function sprintf;

final class WorkerSafeGoogleTranslateTest extends TestCase
{
    public function testExtractParametersResetsIndexBetweenCalls(): void
    {
        $translator = new WorkerSafeGoogleTranslate('es');
        $translator->preserveParameters(true);

        $method = new ReflectionMethod(WorkerSafeGoogleTranslate::class, 'extractParameters');

        self::assertSame('Page #{0} of #{1}', $method->invoke($translator, 'Page :current of :total'));
        self::assertSame('Hello #{0}', $method->invoke($translator, 'Hello :name'));
    }

    public function testExtractParametersUsesLocalCounterNotStatic(): void
    {
        $translator = new WorkerSafeGoogleTranslate('es');
        $translator->preserveParameters(true);

        $method = new ReflectionMethod(WorkerSafeGoogleTranslate::class, 'extractParameters');

        for ($i = 0; $i < 50; ++$i) {
            self::assertSame(
                'A #{0} B #{1}',
                $method->invoke($translator, 'A :x B :y'),
                sprintf('Placeholder index must restart on call #%d', $i + 1),
            );
        }
    }

    public function testExtractParametersWithoutPatternReturnsOriginal(): void
    {
        $translator = new WorkerSafeGoogleTranslate('es');
        $translator->preserveParameters(false);

        $method = new ReflectionMethod(WorkerSafeGoogleTranslate::class, 'extractParameters');

        self::assertSame('Hello :name', $method->invoke($translator, 'Hello :name'));
    }

    public function testResetRestoresDefaultsAndClearsDetectedSource(): void
    {
        $translator = new WorkerSafeGoogleTranslate('es', 'en', ['timeout' => 3.0], null, true);

        $translator->setTarget('fr');
        $translator->setSource('de');
        $translator->preserveParameters(false);

        $lastDetected = new ReflectionProperty(GoogleTranslate::class, 'lastDetectedSource');
        $lastDetected->setValue($translator, 'en');

        $translator->reset();

        self::assertNull($translator->getLastDetectedSource());

        $target  = new ReflectionProperty(GoogleTranslate::class, 'target');
        $source  = new ReflectionProperty(GoogleTranslate::class, 'source');
        $pattern = new ReflectionProperty(GoogleTranslate::class, 'pattern');

        self::assertSame('es', $target->getValue($translator));
        self::assertSame('en', $source->getValue($translator));
        self::assertSame('/:(\w+)/', $pattern->getValue($translator));
    }

    public function testTimeoutOptionsArePassedToGuzzleRequest(): void
    {
        $mock = new MockHandler([
            new ConnectException('timed out', new Request('GET', 'https://example.test')),
        ]);

        $translator = new WorkerSafeGoogleTranslate('es', 'en', [
            'timeout'         => 0.01,
            'connect_timeout' => 0.01,
            'handler'         => HandlerStack::create($mock),
        ]);

        $this->expectException(TranslationRequestException::class);
        $translator->translate('Hello');
    }

    public function testTranslateSuccessLogsWithoutSourceText(): void
    {
        $logger = new class() extends AbstractLogger {
            /** @var list<array{level: string|Stringable, message: string|Stringable, context: array<string, mixed>}> */
            public array $records = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = [
                    'level'   => $level,
                    'message' => $message,
                    'context' => $context,
                ];
            }
        };

        // Same source/target short-circuits upstream without HTTP.
        $translator = new WorkerSafeGoogleTranslate('es', 'es', [], null, false, $logger);
        $secret     = 'SECRET_SUCCESS_PAYLOAD';

        self::assertSame($secret, $translator->translate($secret));

        $debug = array_values(array_filter(
            $logger->records,
            static fn (array $r): bool => (string) $r['level'] === 'debug',
        ));
        self::assertGreaterThanOrEqual(2, count($debug));
        $encoded = json_encode($logger->records, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString($secret, $encoded);
        self::assertSame(strlen($secret), $debug[0]['context']['bytes']);
    }

    public function testTranslateFailureLogsWithoutSourceText(): void
    {
        $logger = new class() extends AbstractLogger {
            /** @var list<array{level: string|Stringable, message: string|Stringable, context: array<string, mixed>}> */
            public array $records = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = [
                    'level'   => $level,
                    'message' => $message,
                    'context' => $context,
                ];
            }
        };

        $mock = new MockHandler([
            new ConnectException('timed out', new Request('GET', 'https://example.test')),
        ]);

        $translator = new WorkerSafeGoogleTranslate(
            'es',
            'en',
            [
                'timeout'         => 0.01,
                'connect_timeout' => 0.01,
                'handler'         => HandlerStack::create($mock),
            ],
            null,
            false,
            $logger,
        );

        try {
            $translator->translate('SECRET_PAYLOAD_DO_NOT_LOG');
            self::fail('Expected TranslationRequestException');
        } catch (TranslationRequestException) {
        }

        $warnings = array_values(array_filter(
            $logger->records,
            static fn (array $r): bool => (string) $r['level'] === 'warning',
        ));
        self::assertNotEmpty($warnings);
        $encoded = json_encode($logger->records, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('SECRET_PAYLOAD_DO_NOT_LOG', $encoded);
        self::assertSame('nowo_google_translate_php', $warnings[0]['context']['bundle']);
        self::assertSame('translate', $warnings[0]['context']['action']);
        self::assertSame(strlen('SECRET_PAYLOAD_DO_NOT_LOG'), $warnings[0]['context']['bytes']);
    }
}
