# Usage

## Table of contents

- [Inject the translator](#inject-the-translator)
- [Preserve placeholders](#preserve-placeholders)
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

## FrankenPHP worker

The service implements `ResetInterface` and is tagged `kernel.reset`. `reset()` restores every setting to the profile values (target, source, `preserve_parameters`, `url`, `client`, Guzzle options including timeouts, token provider) and clears `lastDetectedSource`.

The bundle also calls `reset()` on every already-instantiated profile at the start of each **main** request (`ResetTranslatorsOnRequestSubscriber`, `kernel.request` priority 4096), so setter calls made in one request never reach the next one, even when `services_resetter` does not run. Setter changes are therefore scoped to the current request (sub-requests keep them). In long-running CLI processes (Messenger), keep `services_resetter` enabled or `clone` the service before changing it.

## Logging

Outbound `translate()` calls emit **debug** (start/success) and **warning** (failure) records with metadata only (`target`, `source`, byte length). Source strings are never logged. See [SECURITY.md](SECURITY.md).

## Errors

Upstream may throw `RateLimitException`, `LargeTextException`, `TranslationRequestException`, or `TranslationDecodingException`. Handle them in application code; the demo catches rate-limit and request failures gracefully.
