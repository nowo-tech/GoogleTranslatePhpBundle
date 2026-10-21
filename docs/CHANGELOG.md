# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [[Unreleased]](#unreleased)
- [[1.2.3] - 2026-10-21](#123---2026-10-21)
- [[1.2.2] - 2026-10-20](#122---2026-10-20)
- [[1.2.1] - 2026-10-13](#121---2026-10-13)
- [[1.2.0] - 2026-10-13](#120---2026-10-13)
- [[1.1.2] - 2026-10-13](#112---2026-10-13)
- [[1.1.1] - 2026-10-09](#111---2026-10-09)
- [[1.1.0] - 2026-09-28](#110---2026-09-28)
- [[1.0.8] - 2026-09-27](#108---2026-09-27)
- [[1.0.7] - 2026-09-24](#107---2026-09-24)
- [[1.0.6] - 2026-09-07](#106---2026-09-07)
- [[1.0.5] - 2026-08-24](#105---2026-08-24)
- [[1.0.4] - 2026-08-20](#104---2026-08-20)
- [[1.0.3] - 2026-08-19](#103---2026-08-19)
- [[1.0.2] - 2026-08-18](#102---2026-08-18)
- [[1.0.1] - 2026-07-29](#101---2026-07-29)
- [[1.0.0] - 2026-07-28](#100---2026-07-28)

## [Unreleased]

## [1.2.3] - 2026-10-21

Wave-2 REQ compliance: documented public API, PHPStan and Igor in CI, demo form type and timeout hierarchy. See [UPGRADING](UPGRADING.md#to-123).

### Changed

- PHPDoc description on every public method of `src/` (REQ-CS-001).
- Rector targets PHP 8.2 (package floor) with at most 2 workers (REQ-CS-003, REQ-SF-001).
- CI: the code-style job also runs PHPStan and Igor, on pushes as well as pull requests (REQ-CS-005/006/008).
- `release-check` starts with `ensure-up` and runs the new `check-no-raw-html-form` gate (REQ-MAKE-002).
- Demo: the translate form is a Symfony Form (`TranslateFormType`, `symfony/form` added) instead of raw `<form>` / `<textarea>` (REQ-DEMO-001, REQ-TWIG-005); Caddyfiles and `php-dev.ini` set PHP `max_execution_time` 20 s, Caddy write 30 s and FrankenPHP `max_wait_time` 30 s above the 10 s translate timeout (REQ-RUNTIME-001).
- `docs/PSR.md`: PSR-18 rationale corrected (the bundle calls Google over Guzzle through the upstream library, which has no PSR-18 seam) (REQ-CS-007).
- `.scripts/check-no-cursor-coauthor.sh` accepts git worktrees (REQ-GIT-001); the generated demo `config/reference.php` is excluded from PHP-CS-Fixer; pending Rector finding applied.

- Dependencies refreshed (Composer and pnpm/npm lock files of the bundle and its demos, campaign 2026-10-19).
- `.php-cs-fixer.dist.php`: `new ParallelConfig(2)` (REQ-CS-001).

## [1.2.2] - 2026-10-20

Documentation: wave-1 REQ compliance (README, CONTRIBUTING, issue templates, TOCs). See [UPGRADING](UPGRADING.md#to-122).

### Changed

- Documentation: wave-1 REQ compliance — README documentation list order (PSR under Additional documentation), CONTRIBUTING Cursor Agent settings and post-release-commit trailer check, canonical bug-report template, doc TOCs.

## [1.2.1] - 2026-10-13

### Fixed

- `docs/INSTALLATION.md`: PHP requirement corrected to `>= 8.2, < 8.6` (matches `composer.json`; it still said 8.1).

## [1.2.0] - 2026-10-13

### Fixed

- `guzzle_options` config info text: the profile `timeout` / `connect_timeout` always win over the same keys in `guzzle_options` (the old text said the reverse).

### Changed

- Symfony constraints raised from `^6.0 || ^7.0 || ^8.0` to `^6.4 || ^7.4 || ^8.0` so the lowest allowed versions are the ones CI tests; dropped the redundant `7.0` CI cell (it resolved to 7.4). README / INSTALLATION / badge aligned.

## [1.1.2] - 2026-10-13

### Changed

- Development: `composer.json` pins `config.platform.php` to 8.2.0 so the committed lock stays installable on the minimum PHP; CI overrides the platform per matrix cell.

## [1.1.1] - 2026-10-09

### Fixed

- Tests: `WorkerModeWithoutResetTest` no longer trips PHPStan 2.3 `staticMethod.impossibleType` (state snapshots stored in variables).

### Changed

- Code style: PHP CS Fixer applied to `Configuration` (`url_host_allowlist` exception message).
- Dev dependencies (Dependabot + lock refresh): PHPStan 2.3.1 (+ phpunit/symfony extensions 2.1), PHPUnit 11.5.57, Rector 2.7.0, `igor-php/igor-php` 0.10.1, `nowo-tech/phpstan-frankenphp` 1.2.3.
- Demo (Symfony 8): `stichoza/google-translate-php` 5.3.5, Twig 3.30.0, HotReloadBundle 1.5.5, TwigInspectorBundle 1.1.7; regenerated `config/reference.php`.

## [1.1.0] - 2026-09-28

### Security

- Profile `url` hosts must be in `url_host_allowlist` (defaults: translate.google.com / .cn / translate.googleapis.com); other HTTPS hosts are rejected.

## [1.0.8] - 2026-09-27

### Added

- **REQ-CS-008:** `igor-php/igor-php` (require-dev only), root `igor.json`, Composer/`Makefile` `igor` target, and `release-check` wiring for FrankenPHP worker-state audit.

### Changed

- **Worker safety (Igor):** justified `// @igor-ignore` annotations and/or `ResetInterface` / request-scoped fixes so `make igor` passes on package `src/`.

[1.2.3]: https://github.com/nowo-tech/GoogleTranslatePhpBundle/releases/tag/v1.2.3
[1.2.2]: https://github.com/nowo-tech/GoogleTranslatePhpBundle/releases/tag/v1.2.2
[1.2.1]: https://github.com/nowo-tech/GoogleTranslatePhpBundle/releases/tag/v1.2.1
[1.2.0]: https://github.com/nowo-tech/GoogleTranslatePhpBundle/releases/tag/v1.2.0
[1.1.2]: https://github.com/nowo-tech/GoogleTranslatePhpBundle/releases/tag/v1.1.2
[1.1.1]: https://github.com/nowo-tech/GoogleTranslatePhpBundle/releases/tag/v1.1.1
[1.1.0]: https://github.com/nowo-tech/GoogleTranslatePhpBundle/releases/tag/v1.1.0
[1.0.8]: https://github.com/nowo-tech/GoogleTranslatePhpBundle/releases/tag/v1.0.8

## [1.0.7] - 2026-09-24

### Fixed

- **Worker mode without `services_resetter` / kernel not reset:** new `EventSubscriber\ResetTranslatorsOnRequestSubscriber` resets every already-instantiated translator profile at the start of each main request (`kernel.request`, priority 4096). Setter calls (`setTarget()`, `preserveParameters()`, `setOptions()`, …) and `lastDetectedSource` no longer leak into the next request in FrankenPHP workers.
- **`reset()`** now also restores `url`, the `client` URL param, the Guzzle options (including `timeout` / `connect_timeout`) and the token provider.
- **`translate()`** clears `lastDetectedSource` before each call, so an early return (same source/target, empty response) no longer exposes the previous value.

### Changed

- `url` / `client` are passed as new optional constructor arguments (`$url`, `$client`) instead of `setUrl()` / `setClient()` method calls, so they are the values restored by `reset()`.
- `WorkerSafeGoogleTranslate::trans()` adds the default `timeout` (10 s) / `connect_timeout` (5 s) when `$options` does not set them (`DEFAULT_TRANS_OPTIONS`).

### Documentation

- [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md) — full worker / scenario-B audit (verdict: viable).
- Spec Kit baseline inventory + FRs for request-boundary reset without `services_resetter`.
- USAGE / DEMO-FRANKENPHP / ENGRAM / UPGRADING updated for kernel-not-reset behaviour.

## [1.0.6] - 2026-09-07

### Changed

- **Dependency:** bump `symfony/service-contracts`.
- **Deps (dev):** phpstan / rector / php-cs-fixer bumps.
- **Style:** demo `bundles.php` CS alignment.

### Notes

- **No API or configuration changes** for integrators.

## [1.0.5] - 2026-08-24

### Changed

- Raise minimum PHP to **8.2** and sync README badge (REQ-SF-001).
- **Docs:** PHP-FIG PSR evaluation (REQ-CS-007).

### Notes

- **No API or configuration changes** for integrators unless noted above.

## [1.0.4] - 2026-08-20

### Security

- **Flex recipe `when@prod`:** tighter default profile timeouts (`timeout: 8.0`, `connect_timeout: 3.0`). Prefer **`^1.0.4`**.

## [1.0.3] - 2026-08-19

### Security

- **CI:** run `composer audit --locked` after dependency install (REQ-SEC / P3).

## [1.0.2] - 2026-08-18

### Changed

- **Demos:** pin `nowo-tech/hot-reload-bundle` to `^1.4` with FrankenPHP Mercure/`hot_reload` (`dev`/`test` only).
- **Demos:** Symfony 8 only; Symfony 6/7 demo apps removed.

## [1.0.1] - 2026-07-29

### Added

- **`make demo-smoke`** — boots demos and asserts HTTP 200 (REQ-TEST-011); wired for pre-release use via demo `release-verify`.
- **`make check-open-prs`** — fails when unresolved open GitHub PRs remain (REQ-REL-003); included in `release-check`.
- **Compose V2 preference** — root and demo Makefiles prefer `docker compose`, with fallback to `docker-compose` (REQ-MAKE-010).
- **Optional monorepo Makefile includes** — `-include` for update-deps helpers so standalone GitHub checkouts do not break (REQ-MAKE-009).
- **Demo DebugBundle** — Symfony Debug Bundle registered in demo `bundles.php` / `composer.json` (dev/test) for the mandatory demo stack.

### Changed

- **CI / PHPUnit** — `SYMFONY_DEPRECATIONS_HELPER=max[direct]=0` in `phpunit.xml.dist` and CI test steps (REQ-SF-005).

### Documentation

- TOC sections on CONTRIBUTING, USAGE, SECURITY, DEMO-FRANKENPHP, GITHUB_CI, SPEC-DRIVEN-DEVELOPMENT (REQ-DOCS-005).
- SECURITY 12.4.1 checklist notes REQ-SEC-004 Pass (conditional) grade and residual risk.

## [1.0.0] - 2026-07-28

First public release of `nowo-tech/google-translate-php-bundle`.

### Added

- **`WorkerSafeGoogleTranslate`** — extends `stichoza/google-translate-php` with `ResetInterface` / `kernel.reset` for FrankenPHP worker (and other long-running PHP) request boundaries.
- **Named profiles** — `default_profile` + `profiles` (target, source, client, `preserve_parameters`, `guzzle_options`).
- **HTTP timeouts (REQ-RUNTIME-001)** — per-profile `timeout` (default `10.0`) and `connect_timeout` (default `5.0`) passed to Guzzle.
- **HTTPS-only `url` override** — config tree rejects non-`https://` endpoints (SSRF hygiene).
- **Observability (REQ-OBS-001)** — structured debug/warning logs for translate start/success/failure (`bundle`, `action`, `target`, `source`, `bytes`); never logs source text.
- **Defensive `extractParameters()`** — per-call placeholder counter (no leaked upstream `static $index` across worker requests).
- **Symfony Flex recipe** — `.symfony/recipe/nowo-tech/google-translate-php-bundle/1.0/`.
- **Demos** — FrankenPHP Symfony 7 & 8 under `demo/` (`FRANKENPHP_MODE` default `worker`).
- **QA / Nowo scaffold** — PHPUnit 100% line coverage on `src/`, PHPStan level 8 + FrankenPHP classic/worker rulesets, CS-Fixer, Rector, Spec Kit baseline (`specs/001-baseline/`), CI matrix (PHP 8.1–8.5 × Symfony 6.4–8.1), release workflow.

### Documentation

- Root README with badges, Documentation links, FrankenPHP Friendly Worker Mode banner.
- `docs/`: INSTALLATION, CONFIGURATION, USAGE, CONTRIBUTING, CHANGELOG, UPGRADING, RELEASE, SECURITY, ENGRAM, SPEC-KIT, SPEC-DRIVEN-DEVELOPMENT, DEMO-FRANKENPHP, GITHUB_CI.
- Code of Conduct and GitHub templates (issues, PR, SECURITY, CODEOWNERS).

### Security

- Threat model and 12.4.1 release checklist in [SECURITY.md](SECURITY.md).
- REQ-SEC-004 Pass (conditional): residual risk from unofficial Google scrape and trusted HTTPS URL hosts — see monorepo security analysis.

[Unreleased]: https://github.com/nowo-tech/GoogleTranslatePhpBundle/compare/v1.2.3...HEAD
[1.0.7]: https://github.com/nowo-tech/GoogleTranslatePhpBundle/releases/tag/v1.0.7
[1.0.6]: https://github.com/nowo-tech/GoogleTranslatePhpBundle/releases/tag/v1.0.6
[1.0.5]: https://github.com/nowo-tech/GoogleTranslatePhpBundle/releases/tag/v1.0.5
[1.0.4]: https://github.com/nowo-tech/GoogleTranslatePhpBundle/releases/tag/v1.0.4
[1.0.2]: https://github.com/nowo-tech/GoogleTranslatePhpBundle/releases/tag/v1.0.2
[1.0.1]: https://github.com/nowo-tech/GoogleTranslatePhpBundle/releases/tag/v1.0.1
[1.0.0]: https://github.com/nowo-tech/GoogleTranslatePhpBundle/releases/tag/v1.0.0
