# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [[Unreleased]](#unreleased)
- [[1.0.4] - 2026-08-20](#104---2026-08-20)
- [[1.0.2] - 2026-08-18](#102---2026-08-18)
- [[1.0.1] - 2026-07-29](#101---2026-07-29)
- [[1.0.0] - 2026-07-28](#100---2026-07-28)

## [Unreleased]


## [1.0.5] - 2026-08-24

### Changed

- Raise minimum PHP to **8.2** and sync README badge (REQ-SF-001).
- **Docs:** PHP-FIG PSR evaluation (REQ-CS-007).

### Notes

- **No API or configuration changes** for integrators unless noted above.

[1.0.5]: https://github.com/nowo-tech/GoogleTranslatePhpBundle/releases/tag/v1.0.5

## [1.0.4] - 2026-08-20

### Security

- **Flex recipe `when@prod`:** tighter default profile timeouts (`timeout: 8.0`, `connect_timeout: 3.0`). Prefer **`^1.0.4`**.

[1.0.4]: https://github.com/nowo-tech/GoogleTranslatePhpBundle/releases/tag/v1.0.4

## [1.0.3] - 2026-08-19

### Security

- **CI:** run `composer audit --locked` after dependency install (REQ-SEC / P3).

## [1.0.2] - 2026-08-18

### Changed

- **Demos:** pin `nowo-tech/hot-reload-bundle` to `^1.4` with FrankenPHP Mercure/`hot_reload` (`dev`/`test` only).
- **Demos:** Symfony 8 only; Symfony 6/7 demo apps removed.

[1.0.2]: https://github.com/nowo-tech/GoogleTranslatePhpBundle/releases/tag/v1.0.2

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

[Unreleased]: https://github.com/nowo-tech/GoogleTranslatePhpBundle/compare/v1.0.2...HEAD
[1.0.1]: https://github.com/nowo-tech/GoogleTranslatePhpBundle/releases/tag/v1.0.1
[1.0.0]: https://github.com/nowo-tech/GoogleTranslatePhpBundle/releases/tag/v1.0.0
