<?php

declare(strict_types=1);

namespace Nowo\GoogleTranslatePhpBundle\Tests\Unit\DependencyInjection;

use Nowo\GoogleTranslatePhpBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    public function testDefaultsIncludeDefaultProfile(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[]]);

        self::assertSame('default', $config['default_profile']);
        self::assertArrayHasKey('default', $config['profiles']);
        self::assertSame('en', $config['profiles']['default']['target']);
        self::assertNull($config['profiles']['default']['source']);
        self::assertEqualsWithDelta(10.0, (float) $config['profiles']['default']['timeout'], 0.001);
        self::assertEqualsWithDelta(5.0, (float) $config['profiles']['default']['connect_timeout'], 0.001);
        self::assertSame('gtx', $config['profiles']['default']['client']);
        self::assertFalse($config['profiles']['default']['preserve_parameters']);
        self::assertSame([], $config['profiles']['default']['guzzle_options']);
    }

    public function testCustomProfileTimeouts(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'default_profile' => 'fast',
            'profiles'        => [
                'fast' => [
                    'target'          => 'es',
                    'timeout'         => 30,
                    'connect_timeout' => 2.5,
                ],
            ],
        ]]);

        self::assertSame('fast', $config['default_profile']);
        self::assertEqualsWithDelta(30.0, (float) $config['profiles']['fast']['timeout'], 0.001);
        self::assertEqualsWithDelta(2.5, (float) $config['profiles']['fast']['connect_timeout'], 0.001);
        self::assertSame('es', $config['profiles']['fast']['target']);
    }

    public function testInvalidPreserveParametersRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration(new Configuration(), [[
            'profiles' => [
                'default' => [
                    'preserve_parameters' => 123,
                ],
            ],
        ]]);
    }

    public function testHttpUrlRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration(new Configuration(), [[
            'profiles' => [
                'default' => [
                    'url' => 'http://evil.example/translate',
                ],
            ],
        ]]);
    }

    public function testHttpsUrlAccepted(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'profiles' => [
                'default' => [
                    'url' => 'https://translate.googleapis.com/translate_a/single',
                ],
            ],
        ]]);

        self::assertSame(
            'https://translate.googleapis.com/translate_a/single',
            $config['profiles']['default']['url'],
        );
    }

    public function testUrlWithWhitespaceRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration(new Configuration(), [[
            'profiles' => [
                'default' => [
                    'url' => "https://example.com/translate\n",
                ],
            ],
        ]]);
    }

    public function testEmptyUrlAllowed(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'profiles' => [
                'default' => [
                    'url' => '',
                ],
            ],
        ]]);

        self::assertSame('', $config['profiles']['default']['url']);
    }
}
