<?php

declare(strict_types=1);

namespace Nowo\GoogleTranslatePhpBundle\Tests\Unit\DependencyInjection;

use Nowo\GoogleTranslatePhpBundle\DependencyInjection\GoogleTranslatePhpExtension;
use Nowo\GoogleTranslatePhpBundle\Exception\UnknownProfileException;
use Nowo\GoogleTranslatePhpBundle\Translator\WorkerSafeGoogleTranslate;
use PHPUnit\Framework\TestCase;
use Stichoza\GoogleTranslate\GoogleTranslate;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class GoogleTranslatePhpExtensionTest extends TestCase
{
    public function testLoadRegistersDefaultTranslatorWithTimeoutsAndResetTag(): void
    {
        $container = new ContainerBuilder();
        $extension = new GoogleTranslatePhpExtension();
        $extension->load([[]], $container);

        $id = 'nowo_google_translate_php.translator.default';
        self::assertTrue($container->hasDefinition($id));

        $definition = $container->getDefinition($id);
        self::assertSame(WorkerSafeGoogleTranslate::class, $definition->getClass());
        self::assertTrue($definition->hasTag('kernel.reset'));

        $args = $definition->getArguments();
        self::assertSame('en', $args['$target']);
        self::assertNull($args['$source']);
        self::assertEqualsWithDelta(10.0, $args['$options']['timeout'], 0.001);
        self::assertEqualsWithDelta(5.0, $args['$options']['connect_timeout'], 0.001);
        self::assertInstanceOf(Reference::class, $args['$logger']);
        self::assertSame('logger', (string) $args['$logger']);

        self::assertTrue($container->hasAlias(WorkerSafeGoogleTranslate::class));
        self::assertTrue($container->hasAlias(GoogleTranslate::class));
        self::assertTrue($container->hasAlias('nowo_google_translate_php.translator'));
        self::assertSame('default', $container->getParameter('nowo_google_translate_php.default_profile'));
    }

    public function testLoadAppliesUrlClientAndEmptySource(): void
    {
        $container = new ContainerBuilder();
        $extension = new GoogleTranslatePhpExtension();
        $extension->load([[
            'profiles' => [
                'default' => [
                    'target'         => 'fr',
                    'source'         => '',
                    'url'            => 'https://translate.google.cn/translate_a/single',
                    'client'         => 'webapp',
                    'guzzle_options' => [
                        'proxy' => 'socks5://localhost:1080',
                    ],
                ],
            ],
        ]], $container);

        $definition = $container->getDefinition('nowo_google_translate_php.translator.default');
        $args       = $definition->getArguments();

        self::assertNull($args['$source']);
        self::assertSame('socks5://localhost:1080', $args['$options']['proxy']);
        self::assertEqualsWithDelta(10.0, $args['$options']['timeout'], 0.001);

        $calls = $definition->getMethodCalls();
        self::assertContains(['setUrl', ['https://translate.google.cn/translate_a/single']], $calls);
        self::assertContains(['setClient', ['webapp']], $calls);
    }

    public function testUnknownDefaultProfileThrows(): void
    {
        $this->expectException(UnknownProfileException::class);

        $extension = new GoogleTranslatePhpExtension();
        $extension->load([[
            'default_profile' => 'missing',
            'profiles'        => [
                'default' => [],
            ],
        ]], new ContainerBuilder());
    }

    public function testGetAlias(): void
    {
        self::assertSame('nowo_google_translate_php', (new GoogleTranslatePhpExtension())->getAlias());
    }
}
