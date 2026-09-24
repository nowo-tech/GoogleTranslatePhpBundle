<?php

declare(strict_types=1);

namespace Nowo\GoogleTranslatePhpBundle\Translator;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Stichoza\GoogleTranslate\GoogleTranslate;
use Stichoza\GoogleTranslate\Tokens\TokenProviderInterface;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;

use function strlen;

/**
 * FrankenPHP/worker-safe GoogleTranslate:
 * - Resets every mutable setting (target, source, pattern, URL, client, Guzzle options, token provider)
 *   to the constructor values ({@see ResetInterface}); the bundle also calls {@see reset()} at the start
 *   of each main request, so per-request setter calls never reach the next request even without
 *   {@code services_resetter}.
 * - Uses a per-call placeholder counter instead of upstream {@code static $index}
 *   in {@see GoogleTranslate::extractParameters()}.
 * - Logs outbound translate start/success/failure without source text (REQ-OBS-001).
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

    /**
     * @param array<string, mixed> $options Guzzle client options (e.g. timeout, connect_timeout, proxy)
     * @param string|null $url Google Translate endpoint (null keeps the upstream default)
     * @param string|null $client Google Translate {@code client} URL param (null keeps the upstream default)
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
    ) {
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

    public function translate(string $string): ?string
    {
        // Upstream returns early (same source/target, empty response) without touching it.
        $this->lastDetectedSource = null;

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
