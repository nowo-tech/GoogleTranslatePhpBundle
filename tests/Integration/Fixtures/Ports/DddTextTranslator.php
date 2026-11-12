<?php

declare(strict_types=1);

namespace Nowo\GoogleTranslatePhpBundle\Tests\Integration\Fixtures\Ports;

/**
 * A DDD host's own domain port: no bundle, vendor or framework type.
 */
interface DddTextTranslator
{
    /** The translation, or null when the translation service is unavailable. */
    public function translate(string $text, string $targetLanguage): ?string;
}
