<?php

declare(strict_types=1);

namespace Nowo\GoogleTranslatePhpBundle\Translator;

use Nowo\GoogleTranslatePhpBundle\Contract\GoogleTranslatorInterface;
use Nowo\GoogleTranslatePhpBundle\Exception\LanguagesRequestFailedException;
use Nowo\GoogleTranslatePhpBundle\Exception\TranslationDecodingFailedException;
use Nowo\GoogleTranslatePhpBundle\Exception\TranslationExceptionInterface;
use Nowo\GoogleTranslatePhpBundle\Exception\TranslationRateLimitedException;
use Nowo\GoogleTranslatePhpBundle\Exception\TranslationRequestFailedException;
use Nowo\GoogleTranslatePhpBundle\Exception\TranslationTextTooLargeException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Stichoza\GoogleTranslate\Exceptions\LanguagesRequestException;
use Stichoza\GoogleTranslate\Exceptions\LargeTextException;
use Stichoza\GoogleTranslate\Exceptions\RateLimitException;
use Stichoza\GoogleTranslate\Exceptions\TranslationDecodingException;
use Stichoza\GoogleTranslate\Exceptions\TranslationRequestException;
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
 * - Since 1.4 implements the bundle port {@see GoogleTranslatorInterface} (type-hint it instead of this class or the
 *   stichoza parent), with {@see translateTo()} / {@see translateManyTo()} that never change the shared profile, and
 *   reports failures as {@see TranslationExceptionInterface} (each class extends the stichoza exception it replaces,
 *   which stays the `previous` exception).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class WorkerSafeGoogleTranslate extends GoogleTranslate implements GoogleTranslatorInterface, ResetInterface
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

    /**
     * Translates into `$target` from `$source` (null: detect the source language) on a copy of this profile reset to
     * its configured settings: this instance is not changed, and setter calls made on it do not apply.
     *
     * @since 1.4.0
     */
    public function translateTo(string $string, string $target, ?string $source = null): ?string
    {
        return $this->forLanguages($target, $source)->translate($string);
    }

    /**
     * {@see translateTo()} for several strings, keeping the keys; each distinct string is sent once.
     *
     * @template TKey of array-key
     *
     * @param array<TKey, string> $strings
     *
     * @return array<TKey, string|null>
     *
     * @since 1.4.0
     */
    public function translateManyTo(array $strings, string $target, ?string $source = null): array
    {
        return $this->forLanguages($target, $source)->translateMany($strings);
    }

    /**
     * Same as the parent; since 1.4 failures are {@see LanguagesRequestFailedException} /
     * {@see TranslationRateLimitedException} (subclasses of the stichoza exceptions, which stay `previous`).
     *
     * @return array<string, string>|string[]
     */
    public function languages(?string $target = null): array
    {
        try {
            return parent::languages($target);
        } catch (Throwable $e) {
            throw $this->bundleException($e);
        }
    }

    /**
     * The stichoza exception as the bundle exception that extends it (the original is `previous`); other throwables
     * and bundle exceptions are returned unchanged.
     */
    private function bundleException(Throwable $e): Throwable
    {
        return match (true) {
            $e instanceof TranslationExceptionInterface => $e,
            $e instanceof RateLimitException            => TranslationRateLimitedException::fromVendor($e),
            $e instanceof LargeTextException            => TranslationTextTooLargeException::fromVendor($e),
            $e instanceof TranslationRequestException   => TranslationRequestFailedException::fromVendor($e),
            $e instanceof TranslationDecodingException  => TranslationDecodingFailedException::fromVendor($e),
            $e instanceof LanguagesRequestException     => LanguagesRequestFailedException::fromVendor($e),
            default                                     => $e,
        };
    }

    /**
     * A copy of this profile with its configured settings and the given languages (this instance is not changed).
     */
    private function forLanguages(string $target, ?string $source): self
    {
        $translator = clone $this;
        $translator->reset();
        $translator->setTarget($target);
        $translator->setSource($source);

        return $translator;
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

            throw $this->bundleException($e);
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
