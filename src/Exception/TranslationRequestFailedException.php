<?php

declare(strict_types=1);

namespace Nowo\GoogleTranslatePhpBundle\Exception;

use Stichoza\GoogleTranslate\Exceptions\TranslationRequestException;

/**
 * The translation request failed (HTTP or transport error) (since 1.4).
 *
 * Wraps the stichoza {@see TranslationRequestException} as `previous` and keeps its message and code. Extends it in 1.x only so that
 * `catch (TranslationRequestException)` blocks keep working; catch {@see TranslationExceptionInterface} or this class instead.
 *
 * @since 1.4.0
 */
final class TranslationRequestFailedException extends TranslationRequestException implements TranslationExceptionInterface
{
    /**
     * @internal builds the bundle exception from the vendor one
     */
    public static function fromVendor(TranslationRequestException $previous): self
    {
        return new self($previous->getMessage(), (int) $previous->getCode(), $previous->getSeverity(), $previous->getFile(), $previous->getLine(), $previous);
    }
}
