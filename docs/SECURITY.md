# Security

## Reporting a vulnerability

Please report security issues privately via GitHub Security Advisories on [`nowo-tech/GoogleTranslatePhpBundle`](https://github.com/nowo-tech/GoogleTranslatePhpBundle) or email the maintainers listed in `composer.json`. Do not open a public issue for undisclosed vulnerabilities.

Also see [`.github/SECURITY.md`](../.github/SECURITY.md).

## Threat model (this bundle)

| Risk | Mitigation |
|------|------------|
| Unofficial Google Translate scraping may break or rate-limit | Documented disclaimer; prefer official APIs for production |
| Hung outbound HTTP under FrankenPHP worker | Profile `timeout` + `connect_timeout` (REQ-RUNTIME-001) |
| Mutable translator state across worker requests | `ResetInterface` / `kernel.reset` |
| SSRF via profile `url` override | Config tree allows only `https://` URLs (or null/empty default) |
| Secrets in config | No API keys required by upstream; do not commit `.env` secrets |
| PII in logs | Outbound translate logs metadata only (target/source/byte length); never source text |

## Logging (REQ-OBS-001)

`WorkerSafeGoogleTranslate` uses the app `logger` service when available (otherwise `NullLogger`):

- **debug** — request start / success with `bundle`, `action`, `target`, `source`, `bytes`
- **warning** — request failure with the same context plus `exception` class name

Source/translated strings, tokens, and cookies are **not** logged. Integrators should keep log level ≥ `info` in production if debug noise is undesirable.

## Supported versions

Security fixes are provided for the latest minor of the current major release line. See [`.github/SECURITY.md`](../.github/SECURITY.md#supported-versions).

## Release security checklist (12.4.1)

Before tagging a release, confirm:

| Item | Notes |
|------|-------|
| **`docs/SECURITY.md`** | This document is current and matches bundle behavior. |
| **`.github/SECURITY.md`** | Public policy present and product name is correct. |
| **No committed secrets** | No real API keys, tokens, or private `.env` values in tracked files. |
| **Recipe / demo config** | Demos ship placeholders only; `.env` gitignored; `.env.example` / `.env.test` committed. |
| **Input / output** | Locale and text inputs validated at the application boundary where untrusted. |
| **Dependencies** | `composer audit` run and findings triaged. |
| **Logging** | No source text dumps or secrets in production logs by default. |
| **Cryptography** | N/A (no crypto primitives in this bundle). |
| **Permissions / exposure** | No public HTTP routes shipped by the bundle. |
| **Limits / DoS** | Profile timeouts documented; avoid unbounded payload sizes in host app. |
| **Release notes** | Security-relevant changes reflected in `CHANGELOG.md` / `UPGRADING.md` when needed. |

Recommended commands:

```bash
composer audit
make release-check
```
