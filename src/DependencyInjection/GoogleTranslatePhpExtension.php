<?php

declare(strict_types=1);

namespace Nowo\GoogleTranslatePhpBundle\DependencyInjection;

use Nowo\GoogleTranslatePhpBundle\Exception\UnknownProfileException;
use Nowo\GoogleTranslatePhpBundle\Translator\WorkerSafeGoogleTranslate;
use Stichoza\GoogleTranslate\GoogleTranslate;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

use function is_string;
use function sprintf;

/**
 * Loads services and wires WorkerSafeGoogleTranslate instances from named profiles.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class GoogleTranslatePhpExtension extends Extension
{
    /**
     * @param array<int, array<string, mixed>> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $configuration = new Configuration();
        $config        = $this->processConfiguration($configuration, $configs);

        $defaultProfile = (string) $config['default_profile'];

        if (!isset($config['profiles'][$defaultProfile])) {
            throw UnknownProfileException::forProfile($defaultProfile);
        }

        foreach ($config['profiles'] as $name => $profileConfig) {
            $this->registerTranslator($container, (string) $name, $profileConfig);
        }

        $defaultServiceId = sprintf('nowo_google_translate_php.translator.%s', $defaultProfile);

        $container->setAlias(WorkerSafeGoogleTranslate::class, $defaultServiceId)
            ->setPublic(false);
        $container->setAlias(GoogleTranslate::class, $defaultServiceId)
            ->setPublic(false);
        $container->setAlias('nowo_google_translate_php.translator', $defaultServiceId)
            ->setPublic(false);

        $container->setParameter('nowo_google_translate_php.default_profile', $defaultProfile);
        $container->setParameter('nowo_google_translate_php.profiles', array_keys($config['profiles']));
    }

    /**
     * @param array<string, mixed> $profileConfig
     */
    private function registerTranslator(ContainerBuilder $container, string $name, array $profileConfig): void
    {
        $source = $profileConfig['source'];
        if (is_string($source) && $source === '') {
            $source = null;
        }

        /** @var array<string, mixed> $guzzleOptions */
        $guzzleOptions = $profileConfig['guzzle_options'];
        $options       = array_merge($guzzleOptions, [
            'timeout'         => (float) $profileConfig['timeout'],
            'connect_timeout' => (float) $profileConfig['connect_timeout'],
        ]);

        $definition = new Definition(WorkerSafeGoogleTranslate::class, [
            '$target'             => (string) $profileConfig['target'],
            '$source'             => $source,
            '$options'            => $options,
            '$tokenProvider'      => null,
            '$preserveParameters' => $profileConfig['preserve_parameters'],
            '$logger'             => new Reference('logger', ContainerBuilder::NULL_ON_INVALID_REFERENCE),
        ]);
        $definition->setAutowired(false);
        $definition->setAutoconfigured(true);
        $definition->addTag('kernel.reset', ['method' => 'reset']);

        if (!empty($profileConfig['url'])) {
            $definition->addMethodCall('setUrl', [(string) $profileConfig['url']]);
        }

        $definition->addMethodCall('setClient', [(string) $profileConfig['client']]);

        $serviceId = sprintf('nowo_google_translate_php.translator.%s', $name);
        $container->setDefinition($serviceId, $definition);
    }

    public function getAlias(): string
    {
        return 'nowo_google_translate_php';
    }
}
