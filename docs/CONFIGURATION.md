# Configuration

Root key: `nowo_google_translate_php`.

## Table of contents

- [Named profiles](#named-profiles)
- [Profile options](#profile-options)
- [Timeout hierarchy (REQ-RUNTIME-001)](#timeout-hierarchy-req-runtime-001)
- [Example](#example)

## Named profiles

The bundle uses **named profiles** (Nowo REQ-CFG-001):

| Key | Description |
|-----|-------------|
| `default_profile` | Profile name used for the autowired `WorkerSafeGoogleTranslate` / `GoogleTranslate` aliases |
| `profiles` | Map of profile name → options |

If `default_profile` is missing from `profiles`, compilation fails with `UnknownProfileException`.

Each profile is registered as `nowo_google_translate_php.translator.<name>` and tagged with `kernel.reset`.

## Profile options

| Option | Default | Description |
|--------|---------|-------------|
| `target` | `en` | Target language (ISO 639) |
| `source` | `null` | Source language; empty/`null` = auto-detect |
| `timeout` | `10.0` | Guzzle total request timeout (seconds) |
| `connect_timeout` | `5.0` | Guzzle connect timeout (seconds) |
| `client` | `gtx` | Google Translate client param (`gtx` or `webapp`) |
| `url` | `null` | Optional endpoint override (**https:// only**; http/relative rejected) |
| `preserve_parameters` | `false` | `true`, `false`, or custom regex string |
| `guzzle_options` | `[]` | Extra Guzzle options (merged; timeouts from profile win) |

## Timeout hierarchy (REQ-RUNTIME-001)

Innermost → outermost:

| Layer | Typical value | Role |
|-------|---------------|------|
| Profile `timeout` / `connect_timeout` | 10s / 5s | Fires first on hung Google HTTP |
| PHP `max_execution_time` | > profile timeout | Request hard stop |
| Caddy / FrankenPHP write timeout | > PHP | Proxy deadline |
| FrankenPHP `max_wait_time` | e.g. 30s | Caps wait for a free worker thread |

Raise PHP and proxy deadlines in the **same change** when increasing profile timeouts. See also [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md).

## Example

```yaml
nowo_google_translate_php:
    default_profile: default
    profiles:
        default:
            target: es
            source: null
            timeout: 10.0
            connect_timeout: 5.0
            client: gtx
            preserve_parameters: false
        slow:
            target: fr
            timeout: 30.0
            connect_timeout: 10.0
```

Inject a non-default profile by service id:

```yaml
App\Service\FrenchTranslator:
    arguments:
        $translator: '@nowo_google_translate_php.translator.slow'
```
