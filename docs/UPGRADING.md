# Upgrading

## Table of contents

- [Unreleased](#unreleased)
- [To 1.4.0](#to-140)
  - [Standard host](#standard-host)
  - [DDD host](#ddd-host)
  - [Removal in 2.0](#removal-in-20)
- [To 1.3.1](#to-131)
- [To 1.3.0](#to-130)
- [To 1.2.3](#to-123)
- [To 1.2.2](#to-122)
- [To 1.2.1](#to-121)
- [To 1.2.0](#to-120)
- [To 1.1.2](#to-112)
- [To 1.1.1](#to-111)
- [To 1.1.0](#to-110)
- [To 1.0.8](#to-108)
- [From 1.0.6 to 1.0.7](#from-106-to-107)
- [From 1.0.5 to 1.0.6](#from-105-to-106)
- [From 1.0.4 to 1.0.5](#from-104-to-105)
- [1.0.4 (2026-08-20)](#104-2026-08-20)
- [1.0.3 (2026-08-19)](#103-2026-08-19)
- [1.0.2](#102)
- [1.0.1](#101)
- [1.0.0](#100)
  - [Install](#install)
  - [Configuration to review](#configuration-to-review)
  - [Integrators coming from raw `stichoza/google-translate-php`](#integrators-coming-from-raw-stichozagoogle-translate-php)
  - [General upgrade checklist (future minors)](#general-upgrade-checklist-future-minors)

## Unreleased

## To 1.4.0

From **1.3.x**:

```bash
composer update nowo-tech/google-translate-php-bundle
```

Nothing to change. The deprecation report lists services that still autowire `Stichoza\GoogleTranslate\GoogleTranslate`.

### Standard host

| 1.3 | 1.4 |
| --- | --- |
| `GoogleTranslate $translator` / `WorkerSafeGoogleTranslate $translator` | `GoogleTranslatorInterface $translator` |
| `$translator->setTarget('fr')->setSource('en')->translate($text)` | `$translator->translateTo($text, 'fr', 'en')` |
| a profile service id in `services.yaml` | `GoogleTranslatorInterface $marketingTranslator` |
| `catch (RateLimitException $e)` | `catch (TranslationRateLimitedException $e)` (or `TranslationExceptionInterface`) |

- The fluent setters keep working on `WorkerSafeGoogleTranslate`; `translateTo()` ignores them (it starts from the
  profile settings).
- A runtime `setOptions()` (proxy, timeouts, …), `setUrl()`, `setClient()`, `setTokenProvider()` or
  `preserveParameters()` call on the shared instance does **not** apply to `translateTo()` / `translateManyTo()`:
  put such settings in the profile configuration (or keep using the setters with `translate()`).
- `GoogleTranslatorInterface $<profile>Translator` exists only when the camelized name is a valid PHP variable name
  and unique: a profile named `2024`, `1st` or `0` gets no named alias, nor do `fr_ca` and `fr-ca` together (both
  would be `$frCaTranslator`). Use the service id `nowo_google_translate_php.translator.<profile>` for those.
- Failures are the bundle exceptions: vendor `catch` blocks still catch them (each extends the stichoza class), and the
  vendor exception is `getPrevious()`. Only an exact class comparison (`get_class($e) === RateLimitException::class`)
  changes.

### DDD host

Implement your own domain port in the infrastructure layer on `GoogleTranslatorInterface` and catch
`TranslationExceptionInterface` (see USAGE, "DDD / hexagonal host"): no stichoza type and no setter in your code.

### Removal in 2.0

The `Stichoza\GoogleTranslate\GoogleTranslate` autowiring alias, and the stichoza parent classes of the bundle
exceptions (catch the bundle types).

## To 1.3.1

From **1.3.0**:

```bash
composer update nowo-tech/google-translate-php-bundle
```

Next patch: 1.3.1. No API change.

- The translator profiles are now also reset to their configured defaults when a Messenger message is received,
  after it is handled or failed, and when a console command starts and terminates (before, only at the start of a
  main HTTP request and by `kernel.reset`). Settings changed with the fluent setters last for the current message or
  command only. If a handler or command relied on settings made by a previous message or command, configure them in a
  profile instead, or set them again at the start of each unit of work.

## To 1.3.0

From **1.2.3**:

```bash
composer update nowo-tech/google-translate-php-bundle
```

## To 1.2.3

From **1.2.2**:

```bash
composer update nowo-tech/google-translate-php-bundle
```

## To 1.2.2

From **1.2.1**:

```bash
composer update nowo-tech/google-translate-php-bundle
```

## To 1.2.1

From **1.2.0** — documented PHP requirement matches composer.json

```bash
composer update nowo-tech/google-translate-php-bundle
```

- No breaking changes. No application upgrade steps.

## To 1.2.0

From **1.1.2** — guzzle_options precedence documented; Symfony floors 6.4/7.4.

```bash
composer update nowo-tech/google-translate-php-bundle
```

- Requires Symfony 6.4, 7.4 or 8.x (previously 6.0+ / 7.0+ were allowed but untested). Upgrade Symfony first if you are on 6.0–6.3 or 7.0–7.3.

## To 1.1.2

From **1.1.1** — dependency refresh and repository maintenance.

```bash
composer update nowo-tech/google-translate-php-bundle
```

- No breaking changes. No application upgrade steps.

## To 1.1.1

From **1.1.0** — dependency refresh, test/style fixes. No breaking changes. No application upgrade steps.

```bash
composer update nowo-tech/google-translate-php-bundle
```

## To 1.1.0

From **1.0.8** — `url_host_allowlist`.

```bash
composer update nowo-tech/google-translate-php-bundle
php bin/console cache:clear
```

- Custom profile `url` hosts must be listed in `url_host_allowlist` (or leave `url` null for the upstream default).

## To 1.0.8

From **1.0.7** — REQ-CS-008 Igor FrankenPHP worker audit (igor-php require-dev, igor.json, make igor).

```bash
composer update nowo-tech/google-translate-php-bundle
php bin/console cache:clear
```

- No application upgrade steps for require-dev Igor tooling (REQ-CS-008). Consumers do not pull `igor-php/igor-php` transitively.

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
