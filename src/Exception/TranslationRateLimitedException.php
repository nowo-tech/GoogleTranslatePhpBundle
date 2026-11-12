<?php

declare(strict_types=1);

namespace Nowo\GoogleTranslatePhpBundle\Exception;

use Stichoza\GoogleTranslate\Exceptions\RateLimitException;

/**
 * Google rejected the request for excessive use (HTTP 429 / 503) (since 1.4).
 *
 * Wraps the stichoza {@see RateLimitException} as `previous` and keeps its message and code. Extends it in 1.x only so that
 * `catch (RateLimitException)` blocks keep working; catch {@see TranslationExceptionInterface} or this class instead.
 *
 * @since 1.4.0
 */
final class TranslationRateLimitedException extends RateLimitException implements TranslationExceptionInterface
{
    /**
     * @internal builds the bundle exception from the vendor one
     */
    public static function fromVendor(RateLimitException $previous): self
    {
        return new self($previous->getMessage(), (int) $previous->getCode(), $previous->getSeverity(), $previous->getFile(), $previous->getLine(), $previous);
    }
}
