<?php

declare(strict_types=1);

namespace Nowo\GoogleTranslatePhpBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

use function is_bool;
use function is_string;
use function preg_match;
use function str_starts_with;

/**
 * Configuration tree for nowo_google_translate_php (named profiles).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('nowo_google_translate_php');
        $rootNode    = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('default_profile')
                    ->info('Name of the profile used when no profile is requested')
                    ->defaultValue('default')
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('profiles')
                    ->info('Named translator profiles (target, timeouts, Guzzle options, …)')
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('target')
                                ->info('Default target language (ISO 639)')
                                ->defaultValue('en')
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('source')
                                ->info('Default source language; null/empty = auto-detect')
                                ->defaultNull()
                            ->end()
                            ->floatNode('timeout')
                                ->info('HTTP request timeout in seconds (Guzzle); REQ-RUNTIME-001')
                                ->defaultValue(10.0)
                                ->min(0.1)
                            ->end()
                            ->floatNode('connect_timeout')
                                ->info('HTTP connect timeout in seconds (Guzzle); REQ-RUNTIME-001')
                                ->defaultValue(5.0)
                                ->min(0.1)
                            ->end()
                            ->scalarNode('url')
                                ->info('Google Translate endpoint URL override (https only; null = upstream default)')
                                ->defaultNull()
                                ->validate()
                                    ->ifTrue(static function ($v): bool {
                                        if ($v === null || $v === '') {
                                            return false;
                                        }

                                        if (!is_string($v)) {
                                            return true;
                                        }

                                        return !str_starts_with($v, 'https://')
                                            || preg_match('#\s#', $v) === 1;
                                    })
                                    ->thenInvalid('url must be null/empty or an https:// URL without whitespace.')
                                ->end()
                            ->end()
                            ->scalarNode('client')
                                ->info('Google Translate client param (gtx or webapp)')
                                ->defaultValue('gtx')
                                ->cannotBeEmpty()
                            ->end()
                            ->variableNode('preserve_parameters')
                                ->info('false, true (default :param regex), or a custom regex string')
                                ->defaultFalse()
                                ->validate()
                                    ->ifTrue(static fn ($v): bool => !is_bool($v) && !is_string($v))
                                    ->thenInvalid('preserve_parameters must be a boolean or a regex string.')
                                ->end()
                            ->end()
                            ->arrayNode('guzzle_options')
                                ->info('Extra Guzzle options merged after timeout/connect_timeout')
                                ->normalizeKeys(false)
                                ->variablePrototype()->end()
                                ->defaultValue([])
                            ->end()
                        ->end()
                    ->end()
                    ->defaultValue([
                        'default' => [
                            'target'              => 'en',
                            'source'              => null,
                            'timeout'             => 10.0,
                            'connect_timeout'     => 5.0,
                            'url'                 => null,
                            'client'              => 'gtx',
                            'preserve_parameters' => false,
                            'guzzle_options'      => [],
                        ],
                    ])
                ->end()
            ->end();

        return $treeBuilder;
    }
}
