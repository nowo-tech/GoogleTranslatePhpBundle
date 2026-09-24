# Upgrading

## Table of contents

- [From 1.0.6 to 1.0.7](#from-106-to-107)
- [From 1.0.5 to 1.0.6](#from-105-to-106)
- [From 1.0.4 to 1.0.5](#from-104-to-105)
- [1.0.4 (2026-08-20)](#104-2026-08-20)
- [1.0.3 (2026-08-19)](#103-2026-08-19)
- [1.0.2](#102)
- [1.0.1](#101)
- [1.0.0](#100)

## From 1.0.6 to 1.0.7

No YAML / Flex configuration change.

Behaviour to check:

- Setter calls on the injected translator (`setTarget()`, `setSource()`, `preserveParameters()`, `setUrl()`, `setClient()`, `setOptions()`, `setTokenProvider()`) are undone at the start of every **main** HTTP request and by `reset()`. If you configured a translator once at boot and relied on it persisting across requests, move that setting into a named profile instead.
- FrankenPHP worker with **kernel not reset** / without `services_resetter` is supported: `ResetTranslatorsOnRequestSubscriber` restores profile defaults on each main request.
- `WorkerSafeGoogleTranslate` has optional constructor arguments `$url` and `$client`. Manual `setUrl()` / `setClient()` after construction are reverted by `reset()`.
- `WorkerSafeGoogleTranslate::trans()` applies `timeout: 10` / `connect_timeout: 5` unless you pass them.
- Long-running CLI (Messenger): keep `services_resetter` enabled, or `clone` before mutating.

```bash
composer update nowo-tech/google-translate-php-bundle
```

See [USAGE.md](USAGE.md) and [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md).

## From 1.0.5 to 1.0.6

No breaking changes. **No application upgrade steps.**

```bash
composer update nowo-tech/google-translate-php-bundle
```

## From 1.0.4 to 1.0.5

Review the [CHANGELOG](CHANGELOG.md) entry. PHP **8.2+** is required.

```bash
composer update nowo-tech/google-translate-php-bundle
```

## 1.0.4 (2026-08-20)

Review Flex `when@prod` outbound timeouts (`8s` / `3s` connect). Raise them together with PHP/proxy deadlines if needed.

```bash
composer update nowo-tech/google-translate-php-bundle
```

## 1.0.3 (2026-08-19)

No application upgrade steps.

```bash
composer update nowo-tech/google-translate-php-bundle
```

## 1.0.2

- No application upgrade steps. **Demos only:** Hot Reload Bundle `^1.4` (FrankenPHP Mercure/`hot_reload`, `dev`/`test`). Shipped demos are Symfony 8 only (Symfony 6/7 demo apps removed).

## 1.0.1

No application or public API changes for consumers of `WorkerSafeGoogleTranslate` / named profiles.

**Applications:** no upgrade steps beyond `composer update nowo-tech/google-translate-php-bundle`.

**Contributors / demos:** Compose V2 preference, `make demo-smoke` / `check-open-prs`, DebugBundle in demos.

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
3. Under FrankenPHP **worker** mode, prefer profiles over mutating the shared singleton; the bundle resets on each main request (and via `kernel.reset` when `services_resetter` runs).

### General upgrade checklist (future minors)

1. Read [CHANGELOG.md](CHANGELOG.md).
2. Run `composer update nowo-tech/google-translate-php-bundle`.
3. Clear Symfony cache.
4. Confirm `nowo_google_translate_php` profiles still match your app (especially timeouts and `url`).
