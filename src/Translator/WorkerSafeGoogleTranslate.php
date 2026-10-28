<?php

declare(strict_types=1);

namespace Nowo\GoogleTranslatePhpBundle\Translator;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Stichoza\GoogleTranslate\GoogleTranslate;
use Stichoza\GoogleTranslate\Tokens\TokenProviderInterface;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;

use function array_key_exists;
use function hash;
use function is_string;
use function json_encode;
use function strlen;

use const JSON_THROW_ON_ERROR;

/**
 * FrankenPHP/worker-safe GoogleTranslate:
 * - Resets every mutable setting (target, source, pattern, URL, client, Guzzle options, token provider)
 *   to the constructor values ({@see ResetInterface}); the bundle also calls {@see reset()} at the start
 *   of each main request, so per-request setter calls never reach the next request even without
 *   {@code services_resetter}.
 * - Uses a per-call placeholder counter instead of upstream {@code static $index}
 *   in {@see GoogleTranslate::extractParameters()}.
 * - Logs outbound translate start/success/failure without source text (REQ-OBS-001).
 * - Optional PSR-6 cache of translations ({@code cache_pool} per profile) and {@see translateMany()}, which sends each
 *   distinct string once.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class WorkerSafeGoogleTranslate extends GoogleTranslate implements ResetInterface
{
    /**
     * Timeouts applied by {@see trans()} when the caller does not pass them.
     */
    public const DEFAULT_TRANS_OPTIONS = ['timeout' => 10.0, 'connect_timeout' => 5.0];

    private readonly string $defaultTarget;

    private readonly ?string $defaultSource;

    private readonly bool|string $defaultPreserveParameters;

    private readonly LoggerInterface $logger;

    private readonly string $defaultUrl;

    private readonly mixed $defaultClient;

    /** @var array<string, mixed> */
    private readonly array $defaultOptions;

    private readonly TokenProviderInterface $defaultTokenProvider;

    private readonly ?CacheItemPoolInterface $cache;

    private readonly int $cacheTtl;

    /**
     * @param array<string, mixed> $options Guzzle client options (e.g. timeout, connect_timeout, proxy)
     * @param string|null $url Google Translate endpoint (null keeps the upstream default)
     * @param string|null $client Google Translate {@code client} URL param (null keeps the upstream default)
     * @param CacheItemPoolInterface|null $cache Stores translations keyed by endpoint, client, source, target, parameter
     *                                           pattern and text hash (null = no cache)
     * @param int $cacheTtl Cache lifetime in seconds (0 = no expiry)
     */
    public function __construct(
        string $target = 'en',
        ?string $source = null,
        array $options = [],
        ?TokenProviderInterface $tokenProvider = null,
        bool|string $preserveParameters = false,
        ?LoggerInterface $logger = null,
        ?string $url = null,
        ?string $client = null,
        ?CacheItemPoolInterface $cache = null,
        int $cacheTtl = 86400,
    ) {
        $this->cache                     = $cache;
        $this->cacheTtl                  = $cacheTtl;
        $this->defaultTarget             = $target;
        $this->defaultSource             = $source;
        $this->defaultPreserveParameters = $preserveParameters;
        $this->logger                    = $logger ?? new NullLogger();

        parent::__construct($target, $source, $options, $tokenProvider, $preserveParameters);

        if ($url !== null && $url !== '') {
            parent::setUrl($url);
        }
        if ($client !== null && $client !== '') {
            parent::setClient($client);
        }

        $this->defaultUrl           = $this->url;
        $this->defaultClient        = $this->urlParams['client'] ?? null;
        $this->defaultOptions       = $this->options;
        $this->defaultTokenProvider = $this->tokenProvider;
    }

    /**
     * Restores the configured profile state (target, source, URL, client, options, token provider) and clears the last detected source.
     */
    public function reset(): void
    {
        $this->lastDetectedSource = null;
        $this->setTarget($this->defaultTarget);
        $this->setSource($this->defaultSource);
        $this->preserveParameters($this->defaultPreserveParameters);
        $this->setUrl($this->defaultUrl);
        $this->urlParams['client'] = $this->defaultClient;
        $this->setOptions($this->defaultOptions);
        $this->setTokenProvider($this->defaultTokenProvider);
    }

    /**
     * Same as the parent helper (a fresh instance per call, so no shared state), with the
     * bundle's default timeouts when {@code $options} does not set them.
     *
     * @param array<string, mixed> $options
     */
    public static function trans(
        string $string,
        string $target = 'en',
        ?string $source = null,
        array $options = [],
        ?TokenProviderInterface $tokenProvider = null,
        bool|string $preserveParameters = false,
    ): ?string {
        return parent::trans($string, $target, $source, $options + self::DEFAULT_TRANS_OPTIONS, $tokenProvider, $preserveParameters);
    }

    /**
     * Translates the string with the profile settings, logging start / failure without the text; null when Google returns no translation.
     */
    public function translate(string $string): ?string
    {
        // Upstream returns early (same source/target, empty response) without touching it.
        $this->lastDetectedSource = null;

        if (!$this->cache instanceof CacheItemPoolInterface) {
            return $this->translateRemote($string);
        }

        // A cache hit does not detect the source language: getLastDetectedSource() stays null.
        $item = $this->cache->getItem($this->cacheKey($string));
        if ($item->isHit() && is_string($item->get())) {
            return $item->get();
        }

        $result = $this->translateRemote($string);
        if ($result !== null) {
            $item->set($result);
            if ($this->cacheTtl > 0) {
                $item->expiresAfter($this->cacheTtl);
            }
            $this->cache->save($item);
        }

        return $result;
    }

    /**
     * Translates several strings with the profile settings, keeping the keys. Each distinct string is translated once
     * (and served from the cache when one is configured), so repeated catalogue values cost a single request.
     *
     * @template TKey of array-key
     *
     * @param array<TKey, string> $strings
     *
     * @return array<TKey, string|null>
     */
    public function translateMany(array $strings): array
    {
        $translated = [];
        $results    = [];
        foreach ($strings as $key => $string) {
            if (!array_key_exists($string, $translated)) {
                $translated[$string] = $this->translate($string);
            }
            $results[$key] = $translated[$string];
        }

        return $results;
    }

    private function cacheKey(string $string): string
    {
        $scope = json_encode([$this->url, $this->urlParams['client'] ?? null, $this->source, $this->target, $this->pattern], JSON_THROW_ON_ERROR);

        return 'nowo_gtp_' . hash('sha256', $scope . "\0" . $string);
    }

    private function translateRemote(string $string): ?string
    {
        $context = [
            'bundle' => 'nowo_google_translate_php',
            'action' => 'translate',
            'target' => $this->target,
            'source' => $this->source,
            'bytes'  => strlen($string),
        ];

        $this->logger->debug('Google Translate request starting', $context);

        try {
            $result = parent::translate($string);
            $this->logger->debug('Google Translate request succeeded', $context);

            return $result;
        } catch (Throwable $e) {
            $this->logger->warning('Google Translate request failed', $context + [
                'exception' => $e::class,
            ]);

            throw $e;
        }
    }

    /**
     * Same behaviour as the parent, but the placeholder counter is local to each call
     * so it does not leak across requests in long-running workers.
     */
    protected function extractParameters(string $string): string
    {
        if (!$this->pattern) {
            return $string;
        }

        $index = 0;

        return preg_replace_callback(
            $this->pattern,
            static function () use (&$index): string {
                return '#{' . $index++ . '}';
            },
            $string,
        ) ?: $string;
    }
}
