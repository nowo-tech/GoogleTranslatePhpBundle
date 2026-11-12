<?php

declare(strict_types=1);

namespace Nowo\GoogleTranslatePhpBundle\Tests\Unit\Translator;

use ArrayObject;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Nowo\GoogleTranslatePhpBundle\Contract\GoogleTranslatorInterface;
use Nowo\GoogleTranslatePhpBundle\Exception\LanguagesRequestFailedException;
use Nowo\GoogleTranslatePhpBundle\Exception\TranslationDecodingFailedException;
use Nowo\GoogleTranslatePhpBundle\Exception\TranslationExceptionInterface;
use Nowo\GoogleTranslatePhpBundle\Exception\TranslationRateLimitedException;
use Nowo\GoogleTranslatePhpBundle\Exception\TranslationRequestFailedException;
use Nowo\GoogleTranslatePhpBundle\Exception\TranslationTextTooLargeException;
use Nowo\GoogleTranslatePhpBundle\Translator\WorkerSafeGoogleTranslate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use Stichoza\GoogleTranslate\Exceptions\LanguagesRequestException;
use Stichoza\GoogleTranslate\Exceptions\LargeTextException;
use Stichoza\GoogleTranslate\Exceptions\RateLimitException;
use Stichoza\GoogleTranslate\Exceptions\TranslationDecodingException;
use Stichoza\GoogleTranslate\Exceptions\TranslationRequestException;
use Stichoza\GoogleTranslate\GoogleTranslate;
use Throwable;

/**
 * 1.4: the bundle port {@see GoogleTranslatorInterface} and the bundle exception hierarchy.
 *
 * - translateTo() / translateManyTo() send exactly the request that setTarget() + setSource() + translate() sends and
 *   return the same result, without changing the shared profile;
 * - every stichoza failure surfaces as a bundle exception that is still caught by the 1.3 catch block of the vendor
 *   type, with the vendor exception as `previous` (same message and code).
 *
 * @phpstan-type History array{request: \Psr\Http\Message\RequestInterface, response: \Psr\Http\Message\ResponseInterface|null, error: mixed, options: array<mixed>}
 */
final class GoogleTranslatorPortTest extends TestCase
{
    private const HOLA = '[[["Hola","Hello",null,null,10]],null,"en"]';

    public function testTheTranslatorImplementsThePort(): void
    {
        self::assertContains(GoogleTranslatorInterface::class, class_implements(WorkerSafeGoogleTranslate::class));
    }

    public function testTranslateToSendsTheSameRequestAsTheSettersAndLeavesTheProfileUnchanged(): void
    {
        [$legacy, $legacyRequests] = $this->translator('de', null, [new Response(200, [], self::HOLA)]);
        $legacy->setTarget('es');
        $legacy->setSource('en');
        $legacyResult          = $legacy->translate('Hello');
        [$port, $portRequests] = $this->translator('de', null, [new Response(200, [], self::HOLA), new Response(200, [], '[[["Hallo","Hello",null,null,10]],null,"en"]')]);
        $port->setTarget('it'); // a setter call on the shared service does not leak into translateTo()
        $portResult = $port->translateTo('Hello', 'es', 'en');

        self::assertSame('Hola', $legacyResult);
        self::assertSame($legacyResult, $portResult);
        self::assertSame($this->uris($legacyRequests), $this->uris($portRequests), 'same endpoint, query and languages');
        self::assertSame('it', $this->property($port, 'target'), 'the shared profile keeps its own state');
        self::assertSame('auto', $this->property($port, 'source'));

        $port->reset();
        self::assertSame('Hallo', $port->translate('Hello'));
        self::assertStringContainsString('tl=de', $this->uris($portRequests)[1], 'the profile target after reset');
    }

    public function testTranslateToWithoutSourceDetectsTheLanguage(): void
    {
        [$port, $requests] = $this->translator('de', 'fr', [new Response(200, [], self::HOLA)]);

        self::assertSame('Hola', $port->translateTo('Hello', 'es'));
        self::assertStringContainsString('sl=auto', $this->uris($requests)[0]);
        self::assertSame('fr', $this->property($port, 'source'));
    }

    public function testTranslateManyToKeepsKeysAndSendsEachDistinctStringOnce(): void
    {
        [$legacy, $legacyRequests] = $this->translator('de', null, [new Response(200, [], self::HOLA)]);
        $legacy->setTarget('es');
        $legacy->setSource('en');
        $expected              = $legacy->translateMany(['a' => 'Hello', 'b' => 'Hello']);
        [$port, $portRequests] = $this->translator('de', null, [new Response(200, [], self::HOLA)]);

        self::assertSame($expected, $port->translateManyTo(['a' => 'Hello', 'b' => 'Hello'], 'es', 'en'));
        self::assertSame(['a' => 'Hola', 'b' => 'Hola'], $expected);
        self::assertSame($this->uris($legacyRequests), $this->uris($portRequests));
        self::assertSame('de', $this->property($port, 'target'));
    }

    /**
     * @return iterable<string, array{Response|Throwable, class-string<TranslationExceptionInterface>, class-string<Throwable>}>
     */
    public static function translateFailures(): iterable
    {
        yield 'rate limited (429)' => [new Response(429, [], 'slow down'), TranslationRateLimitedException::class, RateLimitException::class];
        yield 'rate limited (503)' => [new Response(503, [], 'unavailable'), TranslationRateLimitedException::class, RateLimitException::class];
        yield 'text too large (413)' => [new Response(413, [], 'too large'), TranslationTextTooLargeException::class, LargeTextException::class];
        yield 'request failed (500)' => [new Response(500, [], 'boom'), TranslationRequestFailedException::class, TranslationRequestException::class];
        yield 'transport error' => [new ConnectException('timed out', new Request('GET', 'https://example.test')), TranslationRequestFailedException::class, TranslationRequestException::class];
        yield 'undecodable response' => [new Response(200, [], 'not json'), TranslationDecodingFailedException::class, TranslationDecodingException::class];
    }

    /**
     * @param class-string<TranslationExceptionInterface> $bundleClass
     * @param class-string<Throwable> $vendorClass
     */
    #[DataProvider('translateFailures')]
    public function testFailuresAreBundleExceptionsCaughtByTheVendorCatchBlocks(Response|Throwable $failure, string $bundleClass, string $vendorClass): void
    {
        foreach (['translate', 'translateMany', 'translateTo', 'translateManyTo'] as $method) {
            [$translator] = $this->translator('es', 'en', [$failure]);

            $caught = $this->call($translator, $method);

            self::assertInstanceOf($bundleClass, $caught, $method);
            self::assertInstanceOf($vendorClass, $caught, $method . ': the 1.3 catch block of the vendor type still catches it');
            $previous = $caught->getPrevious();
            self::assertInstanceOf($vendorClass, $previous, $method);
            self::assertNotInstanceOf(TranslationExceptionInterface::class, $previous, $method . ': previous is the original vendor exception');
            self::assertSame($previous->getMessage(), $caught->getMessage(), $method);
            self::assertSame($previous->getCode(), $caught->getCode(), $method);
        }
    }

    public function testVendorCatchBlocksOfTheReleasedApiStillWork(): void
    {
        [$translator] = $this->translator('es', 'en', [new Response(429, [], 'slow down')]);

        try {
            $translator->translate('Hello');
            self::fail('Expected a RateLimitException');
        } catch (RateLimitException $e) {
            // 1.3 host code
            self::assertSame(429, $e->getCode());
        }
    }

    /**
     * @return iterable<string, array{Response, class-string<TranslationExceptionInterface>, class-string<Throwable>}>
     */
    public static function languagesFailures(): iterable
    {
        yield 'rate limited' => [new Response(429, [], 'slow down'), TranslationRateLimitedException::class, RateLimitException::class];
        yield 'request failed' => [new Response(500, [], 'boom'), LanguagesRequestFailedException::class, LanguagesRequestException::class];
    }

    /**
     * @param class-string<TranslationExceptionInterface> $bundleClass
     * @param class-string<Throwable> $vendorClass
     */
    #[DataProvider('languagesFailures')]
    public function testLanguagesFailuresAreBundleExceptions(Response $failure, string $bundleClass, string $vendorClass): void
    {
        [$translator] = $this->translator('es', 'en', [$failure]);

        try {
            $translator->languages('en');
            self::fail('Expected ' . $bundleClass);
        } catch (TranslationExceptionInterface $e) {
            self::assertInstanceOf($bundleClass, $e);
            self::assertInstanceOf($vendorClass, $e);
            self::assertInstanceOf($vendorClass, $e->getPrevious());
        }
    }

    public function testOtherThrowablesAndBundleExceptionsAreNotWrappedAgain(): void
    {
        [$translator] = $this->translator('es', 'en', [new RuntimeException('not a Guzzle error')]);

        // Upstream turns any Throwable from the HTTP client into a TranslationRequestException.
        try {
            $translator->translate('Hello');
            self::fail('Expected a TranslationRequestFailedException');
        } catch (TranslationRequestFailedException $e) {
            self::assertInstanceOf(TranslationRequestException::class, $e->getPrevious());
        }

        $bundleException = TranslationRateLimitedException::fromVendor(new RateLimitException('x', 429));
        $other           = new RuntimeException('other');
        $map             = new ReflectionMethod(WorkerSafeGoogleTranslate::class, 'bundleException');
        self::assertSame($bundleException, $map->invoke($translator, $bundleException));
        self::assertSame($other, $map->invoke($translator, $other));
    }

    /**
     * @param list<Response|Throwable> $responses
     *
     * @return array{WorkerSafeGoogleTranslate, ArrayObject<int, History>} the translator and the requests it sent
     */
    private function translator(string $target, ?string $source, array $responses): array
    {
        /** @var ArrayObject<int, History> $requests */
        $requests = new ArrayObject();
        /** @var ArrayObject<int, History> $history */
        $history = $requests;
        $stack   = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($history));

        $translator = new WorkerSafeGoogleTranslate($target, $source);
        // The HTTP client is shared by the copies translateTo() works on (setOptions() never rebuilds it).
        (new ReflectionProperty(GoogleTranslate::class, 'client'))->setValue($translator, new Client(['handler' => $stack]));

        return [$translator, $requests];
    }

    private function call(WorkerSafeGoogleTranslate $translator, string $method): Throwable
    {
        try {
            match ($method) {
                'translate'       => $translator->translate('Hello'),
                'translateMany'   => $translator->translateMany(['Hello']),
                'translateTo'     => $translator->translateTo('Hello', 'fr', 'en'),
                'translateManyTo' => $translator->translateManyTo(['Hello'], 'fr', 'en'),
            };
        } catch (Throwable $e) {
            return $e;
        }

        self::fail($method . ' should fail');
    }

    /**
     * @param ArrayObject<int, History> $requests
     *
     * @return list<string>
     */
    private function uris(ArrayObject $requests): array
    {
        $uris = [];
        foreach ($requests as $entry) {
            $uris[] = $entry['request']->getUri() . "\n" . $entry['request']->getBody();
        }

        return $uris;
    }

    private function property(WorkerSafeGoogleTranslate $translator, string $name): mixed
    {
        return (new ReflectionProperty(GoogleTranslate::class, $name))->getValue($translator);
    }
}
