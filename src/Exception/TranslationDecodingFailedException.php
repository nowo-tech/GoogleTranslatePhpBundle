<?php

declare(strict_types=1);

namespace Nowo\GoogleTranslatePhpBundle\Exception;

use Stichoza\GoogleTranslate\Exceptions\TranslationDecodingException;

/**
 * The translation response could not be decoded (since 1.4).
 *
 * Wraps the stichoza {@see TranslationDecodingException} as `previous` and keeps its message and code. Extends it in 1.x only so that
 * `catch (TranslationDecodingException)` blocks keep working; catch {@see TranslationExceptionInterface} or this class instead.
 *
 * @since 1.4.0
 */
final class TranslationDecodingFailedException extends TranslationDecodingException implements TranslationExceptionInterface
{
    /**
     * @internal builds the bundle exception from the vendor one
     */
    public static function fromVendor(TranslationDecodingException $previous): self
    {
        return new self($previous->getMessage(), (int) $previous->getCode(), $previous);
    }
}
