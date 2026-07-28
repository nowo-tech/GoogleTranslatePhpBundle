# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [[Unreleased]](#unreleased)
- [[1.0.0] - 2026-07-28](#100---2026-07-28)

## [Unreleased]

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

[Unreleased]: https://github.com/nowo-tech/GoogleTranslatePhpBundle/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/nowo-tech/GoogleTranslatePhpBundle/releases/tag/v1.0.0
