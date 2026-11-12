<?php

declare(strict_types=1);

namespace Nowo\GoogleTranslatePhpBundle\Exception;

use Stichoza\GoogleTranslate\Exceptions\LargeTextException;

/**
 * The text is too large for one request (HTTP 413) (since 1.4).
 *
 * Wraps the stichoza {@see LargeTextException} as `previous` and keeps its message and code. Extends it in 1.x only so that
 * `catch (LargeTextException)` blocks keep working; catch {@see TranslationExceptionInterface} or this class instead.
 *
 * @since 1.4.0
 */
final class TranslationTextTooLargeException extends LargeTextException implements TranslationExceptionInterface
{
    /**
     * @internal builds the bundle exception from the vendor one
     */
    public static function fromVendor(LargeTextException $previous): self
    {
        return new self($previous->getMessage(), (int) $previous->getCode(), $previous->getSeverity(), $previous->getFile(), $previous->getLine(), $previous);
    }
}
