<?php

declare(strict_types=1);

namespace Nowo\GoogleTranslatePhpBundle\Tests\Integration\Fixtures;

use Nowo\GoogleTranslatePhpBundle\GoogleTranslatePhpBundle;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Minimal kernel for the kept-alive worker tests: FrameworkBundle (its Messenger integration never adds
 * ResetServicesListener to the dispatcher; only messenger:consume without --no-reset does) and this bundle with one profile. The default translator is exposed as a
 * public alias so the test can drive it like a host service would.
 */
final class WorkerTestKernel extends Kernel
{
    use MicroKernelTrait;

    public const TRANSLATOR = 'test.nowo_google_translate_php.translator';

    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new GoogleTranslatePhpBundle()];
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/nowo-google-translate-php-bundle/' . $this->environment . '/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/nowo-google-translate-php-bundle/' . $this->environment . '/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret'                => 'REDACTED-TEST-x',
            'test'                  => true,
            'http_method_override'  => false,
            'handle_all_throwables' => true,
            'php_errors'            => ['log' => true],
        ]);
        // Quiet logger: the framework's Messenger failure listener logs every failing test message as critical.
        $container->services()->set('logger', NullLogger::class);
        $container->extension('nowo_google_translate_php', [
            'profiles' => ['default' => ['target' => 'en', 'source' => 'auto']],
        ]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->setAlias(self::TRANSLATOR, 'nowo_google_translate_php.translator.default')->setPublic(true);
    }
}
