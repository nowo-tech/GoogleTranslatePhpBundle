<?php

declare(strict_types=1);

namespace Nowo\GoogleTranslatePhpBundle\Exception;

use Stichoza\GoogleTranslate\Exceptions\LanguagesRequestException;

/**
 * The supported-languages request failed (since 1.4).
 *
 * Wraps the stichoza {@see LanguagesRequestException} as `previous` and keeps its message and code. Extends it in 1.x only so that
 * `catch (LanguagesRequestException)` blocks keep working; catch {@see TranslationExceptionInterface} or this class instead.
 *
 * @since 1.4.0
 */
final class LanguagesRequestFailedException extends LanguagesRequestException implements TranslationExceptionInterface
{
    /**
     * @internal builds the bundle exception from the vendor one
     */
    public static function fromVendor(LanguagesRequestException $previous): self
    {
        return new self($previous->getMessage(), (int) $previous->getCode(), $previous->getSeverity(), $previous->getFile(), $previous->getLine(), $previous);
    }
}
