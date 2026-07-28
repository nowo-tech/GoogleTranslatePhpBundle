# Google Translate PHP Bundle — Baseline product specification

**Package**: `nowo-tech/google-translate-php-bundle`  
**Configuration root**: `nowo_google_translate_php`  
**Feature Branch**: `001-baseline`  
**Created**: 2026-07-22  
**Last audited**: 2026-07-28  
**Status**: Active  
**Inventory**: [`code-inventory.md`](code-inventory.md)

**Related docs**: [`docs/SPEC-DRIVEN-DEVELOPMENT.md`](../../docs/SPEC-DRIVEN-DEVELOPMENT.md), [`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md), [`docs/USAGE.md`](../../docs/USAGE.md)

---

## Summary

Symfony bundle wrapping [`stichoza/google-translate-php`](https://github.com/Stichoza/google-translate-php) with **named profiles**, **explicit Guzzle HTTP timeouts** (REQ-RUNTIME-001), and **FrankenPHP worker** compatibility via `ResetInterface` plus a per-call placeholder counter.

Production surface under `src/`: **6** units (5 PHP + 1 YAML config).

---

## Functional requirements

### Bundle / DI

| ID | Requirement |
| --- | --- |
| FR-BUNDLE-001 | Bundle entry wires `GoogleTranslatePhpExtension` as the container extension. |
| FR-CFG-001 | Config tree exposes `default_profile` and at least one named entry under `profiles` (target, source, timeouts, client, url, preserve_parameters, guzzle_options). |
| FR-CFG-002 | Extension registers one `WorkerSafeGoogleTranslate` service per profile (`nowo_google_translate_php.translator.<name>`), aliases the default profile for autowiring, and tags each definition with `kernel.reset`. |
| FR-CFG-003 | When `default_profile` is not a key under `profiles`, boot fails with `UnknownProfileException`. |

### Translator / runtime

| ID | Requirement |
| --- | --- |
| FR-TR-001 | `WorkerSafeGoogleTranslate` extends upstream `GoogleTranslate` and implements `ResetInterface`. |
| FR-TR-002 | `reset()` restores configured target/source/`preserve_parameters` and clears `lastDetectedSource`. |
| FR-TR-003 | `extractParameters()` uses a **per-call** placeholder counter (no leaked `static $index` across worker requests). |
| FR-TR-004 | Each profile passes `timeout` and `connect_timeout` into Guzzle client options (no unbounded HTTP wait). |
| FR-OBS-001 | Outbound `translate()` logs start/success/failure with structured metadata (`bundle`, `action`, `target`, `source`, `bytes`) and never logs source text. |
| FR-SEC-001 | Profile `url` overrides must be `https://` (or null/empty); non-HTTPS values are rejected by the config tree. |

### Resources

| ID | Requirement |
| --- | --- |
| FR-DI-001 | `Resources/config/services.yaml` loads as the extension bootstrap (definitions for translators are built in PHP). |

---

## User Scenarios & Testing

### User Story 1 — Translate with default profile (Priority: P1)

**US-01**

**Given** default profile `target: es`, **When** `WorkerSafeGoogleTranslate::translate('Hello')` runs successfully, **Then** a Spanish translation (or upstream exception) is returned without leaking state to the next request after `reset()`.

### User Story 2 — Named profiles (Priority: P1)

**US-02**

**Given** profiles `default` and `slow` with different timeouts, **When** the container boots with `default_profile: default`, **Then** autowiring resolves to the default translator and `nowo_google_translate_php.translator.slow` is available by id.

### User Story 3 — Unknown default profile (Priority: P2)

**US-03**

**Given** `default_profile: missing` not listed under `profiles`, **When** the extension loads, **Then** `UnknownProfileException` is thrown.

### User Story 4 — Worker reset (Priority: P1)

**US-04**

**Given** a singleton translator mutated during a request, **When** `reset()` runs, **Then** target/source/`preserve_parameters` restore configured defaults and `lastDetectedSource` is null.

### User Story 5 — HTTP timeouts (Priority: P1)

**US-05**

**Given** profile `timeout` / `connect_timeout`, **When** Guzzle cannot complete in time, **Then** a translation request exception surfaces (no unbounded wait).

---

## Success criteria

| ID | Criterion |
| --- | --- |
| SC-01 | Inventory maps **6/6** production units under `src/` to semantic `FR-*` IDs (no `FR-SRC-*`). |
| SC-02 | PHPUnit line coverage on `src/` is **100%** (`make test-coverage` / `composer coverage-check`). |
| SC-03 | PHPStan with FrankenPHP classic + worker rulesets exits **0**. |

---

## Non-goals

- Official Google Cloud Translation / DeepL / LibreTranslate backends (see TranslationYamlToolsBundle).
- Admin Web UI, Twig namespaces, or i18n catalogue domains for this package.
- Replacing upstream scrape semantics or guaranteeing Google ToS compliance.

---

## Validation commands

```bash
make setup-hooks
make phpstan
make test-coverage
composer coverage-check
```

## Traceability

| Requirement | Primary tests |
| --- | --- |
| FR-BUNDLE-001 | `tests/Unit/GoogleTranslatePhpBundleTest.php` |
| FR-CFG-001 | `tests/Unit/DependencyInjection/ConfigurationTest.php` |
| FR-CFG-002, FR-CFG-003, FR-DI-001 | `tests/Unit/DependencyInjection/GoogleTranslatePhpExtensionTest.php` |
| FR-TR-001 … FR-TR-004 | `tests/Unit/Translator/WorkerSafeGoogleTranslateTest.php` |
| FR-CFG-003 | `tests/Unit/Exception/UnknownProfileExceptionTest.php` |
