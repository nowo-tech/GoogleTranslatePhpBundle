# Upgrading

## 1.0.0

First public release. No prior stable tags to migrate from.

### Install

```bash
composer require nowo-tech/google-translate-php-bundle:^1.0
```

With Symfony Flex, the recipe registers the bundle and publishes `config/packages/nowo_google_translate_php.yaml`. Without Flex, enable `GoogleTranslatePhpBundle` in `config/bundles.php` and add configuration (see [CONFIGURATION.md](CONFIGURATION.md)).

### Configuration to review

| Key | Notes |
|-----|--------|
| `default_profile` | Must exist under `profiles` or boot throws `UnknownProfileException`. |
| `profiles.*.timeout` / `connect_timeout` | Defaults `10.0` / `5.0`; raise PHP/proxy deadlines together under FrankenPHP. |
| `profiles.*.url` | Optional; **must** be `https://` or empty/`null`. |
| Logging | Translator uses the app `logger` when available; production apps usually keep level ≥ `info` so debug translate noise stays off. |

### Integrators coming from raw `stichoza/google-translate-php`

1. Prefer injecting `WorkerSafeGoogleTranslate` (or the aliased `GoogleTranslate`) from this bundle instead of constructing the client manually.
2. Move target/source/timeouts into named profiles.
3. Under FrankenPHP **worker** mode, rely on `kernel.reset` — do not keep request-local mutations on the singleton across requests without calling `reset()`.

### General upgrade checklist (future minors)

1. Read [CHANGELOG.md](CHANGELOG.md).
2. Run `composer update nowo-tech/google-translate-php-bundle`.
3. Clear Symfony cache.
4. Confirm `nowo_google_translate_php` profiles still match your app (especially timeouts and `url`).
