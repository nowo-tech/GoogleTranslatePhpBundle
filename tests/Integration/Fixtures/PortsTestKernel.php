<?php

declare(strict_types=1);

namespace Nowo\GoogleTranslatePhpBundle\Tests\Integration\Fixtures;

use Nowo\GoogleTranslatePhpBundle\GoogleTranslatePhpBundle;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Two profiles (default, marketing) and the given host services (autowired, public), in its own cache directory.
 */
final class PortsTestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param list<class-string> $hostServices
     */
    public function __construct(private readonly string $baseDir, private readonly array $hostServices)
    {
        parent::__construct('test', true);
    }

    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new GoogleTranslatePhpBundle()];
    }

    public function getProjectDir(): string
    {
        return $this->baseDir;
    }

    public function getCacheDir(): string
    {
        return $this->baseDir . '/cache';
    }

    public function getLogDir(): string
    {
        return $this->baseDir . '/log';
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
        $container->services()->set('logger', NullLogger::class);
        $container->extension('nowo_google_translate_php', [
            'profiles' => [
                'default'   => ['target' => 'en', 'source' => 'auto'],
                'marketing' => ['target' => 'es'],
            ],
        ]);
        foreach ($this->hostServices as $class) {
            $container->services()->set($class)->autowire()->public();
        }
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
    }
}
