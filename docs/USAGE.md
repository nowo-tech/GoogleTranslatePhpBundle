# Usage

## Table of contents

- [Inject the translator](#inject-the-translator)
- [Preserve placeholders](#preserve-placeholders)
- [Cache and lists](#cache-and-lists)
- [FrankenPHP worker](#frankenphp-worker)
- [Logging](#logging)
- [Errors](#errors)

## Inject the translator

```php
use Nowo\GoogleTranslatePhpBundle\Translator\WorkerSafeGoogleTranslate;
// or: use Stichoza\GoogleTranslate\GoogleTranslate;

final class CatalogueFiller
{
    public function __construct(
        private readonly WorkerSafeGoogleTranslate $translator,
    ) {
    }

    public function fill(string $text): ?string
    {
        return $this->translator->translate($text);
    }
}
```

Both `WorkerSafeGoogleTranslate` and `GoogleTranslate` resolve to the **default profile**.

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

The bundle also calls `reset()` on every already-instantiated profile at the start of each **main** request (`ResetTranslatorsOnRequestSubscriber`, `kernel.request` priority 4096), so setter calls made in one request never reach the next one, even when `services_resetter` does not run. Setter changes are therefore scoped to the current request (sub-requests keep them). In long-running CLI processes (Messenger), keep `services_resetter` enabled or `clone` the service before changing it.

## Logging

Outbound `translate()` calls emit **debug** (start/success) and **warning** (failure) records with metadata only (`target`, `source`, byte length). Source strings are never logged. See [SECURITY.md](SECURITY.md).

## Errors

Upstream may throw `RateLimitException`, `LargeTextException`, `TranslationRequestException`, or `TranslationDecodingException`. Handle them in application code; the demo catches rate-limit and request failures gracefully.
