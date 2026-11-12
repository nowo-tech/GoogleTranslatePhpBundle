# Usage

## Table of contents

- [Inject the translator](#inject-the-translator)
  - [Standard Symfony host](#standard-symfony-host)
  - [DDD / hexagonal host](#ddd--hexagonal-host)
- [Preserve placeholders](#preserve-placeholders)
- [Cache and lists](#cache-and-lists)
- [FrankenPHP worker](#frankenphp-worker)
- [Logging](#logging)
- [Errors](#errors)

## Inject the translator

Type-hint the bundle port `Nowo\GoogleTranslatePhpBundle\Contract\GoogleTranslatorInterface` (since 1.4). It is
autowired to the **default profile**; `GoogleTranslatorInterface $<profile>Translator` (for example
`$marketingTranslator`) selects another profile.

### Standard Symfony host

```php
use Nowo\GoogleTranslatePhpBundle\Contract\GoogleTranslatorInterface;

final class CatalogueFiller
{
    public function __construct(
        private readonly GoogleTranslatorInterface $translator,
    ) {
    }

    public function fill(string $text): ?string
    {
        return $this->translator->translate($text);          // profile target / source
    }

    public function fillFor(string $text, string $locale): ?string
    {
        return $this->translator->translateTo($text, $locale); // per call; the profile is not changed
    }
}
```

`WorkerSafeGoogleTranslate` (the concrete class, with the fluent setters of `stichoza/google-translate-php`) still
resolves to the default profile. The `Stichoza\GoogleTranslate\GoogleTranslate` autowiring alias is **deprecated
since 1.4** (removed in 2.0): type-hint the interface instead.

### DDD / hexagonal host

Keep your domain port free of the bundle and the vendor; implement it in the infrastructure layer on
`GoogleTranslatorInterface` and `TranslationExceptionInterface` only (bundle-owned types, no stichoza class, no
setter):

```php
// src/Domain/Translation/TextTranslator.php
interface TextTranslator
{
    public function translate(string $text, string $targetLanguage): ?string;
}

// src/Infrastructure/Translation/GoogleTextTranslator.php
use Nowo\GoogleTranslatePhpBundle\Contract\GoogleTranslatorInterface;
use Nowo\GoogleTranslatePhpBundle\Exception\TranslationExceptionInterface;

final readonly class GoogleTextTranslator implements TextTranslator
{
    public function __construct(private GoogleTranslatorInterface $translator)
    {
    }

    public function translate(string $text, string $targetLanguage): ?string
    {
        try {
            return $this->translator->translateTo($text, $targetLanguage);
        } catch (TranslationExceptionInterface) {
            return null; // or your own domain exception
        }
    }
}
```

`translateTo()` / `translateManyTo()` take the languages per call (source `null` = detect) and work on a copy of the
profile reset to its configured settings, so a Messenger handler or a console command needs no setter and leaves
nothing behind.

## Preserve placeholders

```yaml
profiles:
    default:
        preserve_parameters: true   # keeps :name style tokens
```

Or per call:

```php
$translator->preserveParameters(true)->translate('Hello :name');
```

`WorkerSafeGoogleTranslate` uses a **per-call** placeholder counter (safe under FrankenPHP worker).

## Cache and lists

Set `cache_pool` on a profile to keep translations in a PSR-6 pool (requires `psr/cache`, e.g. Symfony's `cache.app`):

```yaml
nowo_google_translate_php:
    profiles:
        default:
            target: es
            cache_pool: cache.app
            cache_ttl: 86400
```

`translateMany()` translates a list in one call, keeps the keys and requests each distinct string once:

```php
$labels = $translator->translateMany(['save' => 'Save', 'cancel' => 'Cancel', 'ok' => 'Save']);
```

## FrankenPHP worker

The service implements `ResetInterface` and is tagged `kernel.reset`. `reset()` restores every setting to the profile values (target, source, `preserve_parameters`, `url`, `client`, Guzzle options including timeouts, token provider) and clears `lastDetectedSource`.

The bundle also calls `reset()` on every already-instantiated profile at the start of each **main** request (`ResetTranslatorsOnRequestSubscriber`, `kernel.request` priority 4096), so setter calls made in one request never reach the next one, even when `services_resetter` does not run. Setter changes are therefore scoped to the current request (sub-requests keep them). Since 1.3.1 the same reset also runs at the Messenger message and console command boundaries. Prefer `translateTo()` (1.4) to the setters: it never changes the shared profile.

## Logging

Outbound `translate()` calls emit **debug** (start/success) and **warning** (failure) records with metadata only (`target`, `source`, byte length). Source strings are never logged. See [SECURITY.md](SECURITY.md).

## Errors

Since 1.4 every translation failure is a `Nowo\GoogleTranslatePhpBundle\Exception\TranslationExceptionInterface`:

| Bundle exception (1.4) | Replaces (stichoza, kept as `previous`) | When |
| --- | --- | --- |
| `TranslationRateLimitedException` | `RateLimitException` | HTTP 429 / 503 |
| `TranslationTextTooLargeException` | `LargeTextException` | HTTP 413 |
| `TranslationRequestFailedException` | `TranslationRequestException` | other HTTP or transport errors |
| `TranslationDecodingFailedException` | `TranslationDecodingException` | the response cannot be decoded |
| `LanguagesRequestFailedException` | `LanguagesRequestException` | `languages()` failed |

Each class keeps the vendor message and code and, in 1.x, extends the stichoza class it replaces, so existing
`catch (RateLimitException $e)` blocks keep working. Catch the bundle types in new code; 2.0 removes the vendor
parents. The static vendor helper `langs()` builds a plain stichoza instance and still throws the vendor types.
