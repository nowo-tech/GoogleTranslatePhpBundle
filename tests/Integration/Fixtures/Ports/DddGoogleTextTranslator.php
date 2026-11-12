<?php

declare(strict_types=1);

namespace Nowo\GoogleTranslatePhpBundle\Tests\Integration\Fixtures\Ports;

use Nowo\GoogleTranslatePhpBundle\Contract\GoogleTranslatorInterface;
use Nowo\GoogleTranslatePhpBundle\Exception\TranslationExceptionInterface;

/**
 * The DDD host's infrastructure adapter: only bundle-owned types (the port and the exception interface), no stichoza
 * class and no setter.
 */
final readonly class DddGoogleTextTranslator implements DddTextTranslator
{
    public function __construct(private GoogleTranslatorInterface $translator)
    {
    }

    public function translate(string $text, string $targetLanguage): ?string
    {
        try {
            return $this->translator->translateTo($text, $targetLanguage, 'en');
        } catch (TranslationExceptionInterface) {
            return null;
        }
    }
}
