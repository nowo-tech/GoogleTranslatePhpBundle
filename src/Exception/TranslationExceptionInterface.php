<?php

declare(strict_types=1);

namespace Nowo\GoogleTranslatePhpBundle\Exception;

use Throwable;

/**
 * Every translation failure the bundle reports (since 1.4, REQ-ARCH-001 rule 3): catch this interface (or one of its
 * classes) instead of the stichoza exception types.
 *
 * The original stichoza exception is the `previous` exception. In 1.x each class also extends the stichoza exception
 * it replaces (e.g. {@see TranslationRateLimitedException} extends `RateLimitException`), so `catch` blocks written for
 * the vendor types keep working; 2.0 removes those vendor parents.
 *
 * @since 1.4.0
 */
interface TranslationExceptionInterface extends Throwable
{
}
