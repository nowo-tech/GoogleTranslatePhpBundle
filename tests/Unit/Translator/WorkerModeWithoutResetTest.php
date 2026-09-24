<?php

declare(strict_types=1);

namespace Nowo\GoogleTranslatePhpBundle\Tests\Unit\Translator;

use Nowo\GoogleTranslatePhpBundle\DependencyInjection\GoogleTranslatePhpExtension;
use Nowo\GoogleTranslatePhpBundle\EventSubscriber\ResetTranslatorsOnRequestSubscriber;
use Nowo\GoogleTranslatePhpBundle\Translator\WorkerSafeGoogleTranslate;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Stichoza\GoogleTranslate\GoogleTranslate;
use Stichoza\GoogleTranslate\Tokens\TokenProviderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Same container and translator instances across consecutive requests, services_resetter never runs.
 */
final class WorkerModeWithoutResetTest extends TestCase
{
    private const ID = 'nowo_google_translate_php.translator.default';

    public function testSettersFromOneRequestDoNotReachTheNext(): void
    {
        $container = $this->compileContainer([
            'url'    => 'https://translate.google.cn/translate_a/single',
            'client' => 'gtx',
        ]);

        /** @var ResetTranslatorsOnRequestSubscriber $subscriber */
        $subscriber = $container->get(ResetTranslatorsOnRequestSubscriber::class);
        // No translator instantiated yet: nothing to reset, nothing created.
        $subscriber->onKernelRequest($this->requestEvent(HttpKernelInterface::MAIN_REQUEST));
        self::assertFalse($container->initialized(self::ID));

        /** @var WorkerSafeGoogleTranslate $translator */
        $translator = $container->get(self::ID);
        $defaults   = $this->state($translator);

        // Request 1 (tenant A) reconfigures the shared service.
        $subscriber->onKernelRequest($this->requestEvent(HttpKernelInterface::MAIN_REQUEST));
        $translator->setTarget('fr')->setSource('de')->preserveParameters(true);
        $translator->setUrl('https://proxy.tenant-a.example/translate')->setClient('webapp');
        $translator->setOptions(['proxy' => 'socks5://tenant-a:1080']);
        $translator->setTokenProvider($this->createStub(TokenProviderInterface::class));
        (new ReflectionProperty(GoogleTranslate::class, 'lastDetectedSource'))->setValue($translator, 'de');

        // A sub-request (fragment / ESI) keeps the main request's settings.
        $subscriber->onKernelRequest($this->requestEvent(HttpKernelInterface::SUB_REQUEST));
        self::assertSame('fr', $this->state($translator)['target']);

        // Request 2 (tenant B): no reset() call from the framework.
        $subscriber->onKernelRequest($this->requestEvent(HttpKernelInterface::MAIN_REQUEST));

        self::assertSame($defaults, $this->state($translator));
        self::assertSame('en', $defaults['target']);
        self::assertSame('auto', $defaults['source']);
        self::assertNull($defaults['pattern']);
        self::assertSame('https://translate.google.cn/translate_a/single', $defaults['url']);
        self::assertSame('gtx', $defaults['client']);
        self::assertEqualsWithDelta(10.0, $defaults['options']['timeout'], 0.001);
        self::assertArrayNotHasKey('proxy', $defaults['options']);
        self::assertNull($translator->getLastDetectedSource());
    }

    public function testSubscriberListensEarlyOnKernelRequest(): void
    {
        self::assertSame(
            [KernelEvents::REQUEST => ['onKernelRequest', 4096]],
            ResetTranslatorsOnRequestSubscriber::getSubscribedEvents(),
        );
    }

    public function testTranslateNeverReturnsAPreviousDetectedSource(): void
    {
        $translator = new WorkerSafeGoogleTranslate('es', 'es');
        (new ReflectionProperty(GoogleTranslate::class, 'lastDetectedSource'))->setValue($translator, 'fr');

        // Same source/target short-circuits upstream before language detection.
        self::assertSame('hola', $translator->translate('hola'));
        self::assertNull($translator->getLastDetectedSource());
    }

    public function testStaticTransBuildsAFreshInstancePerCall(): void
    {
        $shared = new WorkerSafeGoogleTranslate('fr', 'de', [], null, true);

        // Same source/target returns without HTTP; the shared service is untouched.
        self::assertSame('Hola :name', WorkerSafeGoogleTranslate::trans('Hola :name', 'es', 'es', [], null, true));
        self::assertSame('Hi', WorkerSafeGoogleTranslate::trans('Hi', 'en', 'en', ['timeout' => 2.5]));
        self::assertSame('fr', $this->state($shared)['target']);
    }

    public function testConstructorUrlAndClientAreTheResetDefaults(): void
    {
        $translator = new WorkerSafeGoogleTranslate(url: 'https://translate.google.cn/translate_a/single', client: 'webapp');
        $translator->setUrl('https://other.example/')->setClient('gtx');
        $translator->reset();

        self::assertSame('https://translate.google.cn/translate_a/single', $this->state($translator)['url']);
        self::assertSame('webapp', $this->state($translator)['client']);

        $upstreamDefaults = new WorkerSafeGoogleTranslate(url: '', client: '');
        self::assertSame('https://translate.google.com/translate_a/single', $this->state($upstreamDefaults)['url']);
        self::assertSame('webapp', $this->state($upstreamDefaults)['client']);
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function compileContainer(array $profile): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new GoogleTranslatePhpExtension())->load([['profiles' => ['default' => $profile]]], $container);
        $container->getDefinition(self::ID)->setPublic(true);
        $container->getDefinition(ResetTranslatorsOnRequestSubscriber::class)->setPublic(true);
        $container->compile();

        return $container;
    }

    private function requestEvent(int $type): RequestEvent
    {
        return new RequestEvent($this->createStub(HttpKernelInterface::class), new Request(), $type);
    }

    /**
     * @return array{target: mixed, source: mixed, pattern: mixed, url: mixed, client: mixed, options: array<string, mixed>, tokenProvider: mixed}
     */
    private function state(WorkerSafeGoogleTranslate $translator): array
    {
        $read = static fn (string $name): mixed => (new ReflectionProperty(GoogleTranslate::class, $name))->getValue($translator);

        /** @var array<string, mixed> $urlParams */
        $urlParams = $read('urlParams');
        /** @var array<string, mixed> $options */
        $options = $read('options');

        return [
            'target'        => $read('target'),
            'source'        => $read('source'),
            'pattern'       => $read('pattern'),
            'url'           => $read('url'),
            'client'        => $urlParams['client'] ?? null,
            'options'       => $options,
            'tokenProvider' => $read('tokenProvider'),
        ];
    }
}
