<?php

declare(strict_types=1);

namespace Nowo\GoogleTranslatePhpBundle\Translator;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Stichoza\GoogleTranslate\GoogleTranslate;
use Stichoza\GoogleTranslate\Tokens\TokenProviderInterface;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;

/**
 * FrankenPHP/worker-safe GoogleTranslate:
 * - Resets mutable instance state between requests ({@see ResetInterface}).
 * - Uses a per-call placeholder counter instead of upstream {@code static $index}
 *   in {@see GoogleTranslate::extractParameters()}.
 * - Logs outbound translate start/success/failure without source text (REQ-OBS-001).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class WorkerSafeGoogleTranslate extends GoogleTranslate implements ResetInterface
{
    private readonly string $defaultTarget;

    private readonly ?string $defaultSource;

    private readonly bool|string $defaultPreserveParameters;

    private readonly LoggerInterface $logger;

    /**
     * @param array<string, mixed> $options Guzzle client options (e.g. timeout, connect_timeout, proxy)
     */
    public function __construct(
        string $target = 'en',
        ?string $source = null,
        array $options = [],
        ?TokenProviderInterface $tokenProvider = null,
        bool|string $preserveParameters = false,
        ?LoggerInterface $logger = null,
    ) {
        $this->defaultTarget             = $target;
        $this->defaultSource             = $source;
        $this->defaultPreserveParameters = $preserveParameters;
        $this->logger                    = $logger ?? new NullLogger();

        parent::__construct($target, $source, $options, $tokenProvider, $preserveParameters);
    }

    public function reset(): void
    {
        $this->lastDetectedSource = null;
        $this->setTarget($this->defaultTarget);
        $this->setSource($this->defaultSource);
        $this->preserveParameters($this->defaultPreserveParameters);
    }

    public function translate(string $string): ?string
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
