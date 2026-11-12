<?php

declare(strict_types=1);

namespace Nowo\GoogleTranslatePhpBundle\Contract;

use Nowo\GoogleTranslatePhpBundle\Exception\TranslationExceptionInterface;

/**
 * The bundle's translation port (since 1.4, REQ-ARCH-001 rules 1 and 3): type-hint this interface instead of the
 * stichoza `GoogleTranslate` class or the bundle's `WorkerSafeGoogleTranslate`.
 *
 * Autowired to the default profile; `GoogleTranslatorInterface $<profile>Translator` (e.g. `$marketingTranslator`)
 * selects another profile. Failures are reported as {@see TranslationExceptionInterface}, never as a vendor type in the
 * signature (the concrete exceptions still extend the stichoza ones in 1.x, so existing catch blocks keep working).
 *
 * {@see translate()} / {@see translateMany()} use the profile settings (target, source, parameter pattern, endpoint);
 * {@see translateTo()} / {@see translateManyTo()} take the languages per call and never change the shared profile, so
 * they need no setter and leave nothing behind for the next request, message or command (FrankenPHP worker mode without
 * kernel reset).
 *
 * @since 1.4.0
 */
interface GoogleTranslatorInterface
{
    /**
     * Translates with the profile settings; null when Google returns no translation.
     *
     * @throws TranslationExceptionInterface When the request fails, is rate limited, is too large or cannot be decoded
     */
    public function translate(string $string): ?string;

    /**
     * Translates several strings with the profile settings, keeping the keys; each distinct string is sent once.
     *
     * @template TKey of array-key
     *
     * @param array<TKey, string> $strings
     *
     * @throws TranslationExceptionInterface When a request fails, is rate limited, is too large or cannot be decoded
     *
     * @return array<TKey, string|null>
     */
    public function translateMany(array $strings): array;

    /**
     * Translates into `$target` from `$source` (null: detect the source language), starting from the profile's other
     * settings (endpoint, parameter pattern, timeouts, cache); the shared profile is not changed.
     *
     * @throws TranslationExceptionInterface When the request fails, is rate limited, is too large or cannot be decoded
     */
    public function translateTo(string $string, string $target, ?string $source = null): ?string;

    /**
     * {@see translateTo()} for several strings, keeping the keys; each distinct string is sent once.
     *
     * @template TKey of array-key
     *
     * @param array<TKey, string> $strings
     *
     * @throws TranslationExceptionInterface When a request fails, is rate limited, is too large or cannot be decoded
     *
     * @return array<TKey, string|null>
     */
    public function translateManyTo(array $strings, string $target, ?string $source = null): array;
}
