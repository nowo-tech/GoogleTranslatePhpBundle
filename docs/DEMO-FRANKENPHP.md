# Demo with FrankenPHP

Demos under `demo/symfony7` and `demo/symfony8` run on **FrankenPHP** with Caddy.

## Table of contents

- [FRANKENPHP_MODE (REQ-DEMO-010)](#frankenphp_mode-req-demo-010)
- [Worker compatibility](#worker-compatibility)
- [Timeouts (REQ-RUNTIME-001)](#timeouts-req-runtime-001)
- [Run](#run)

## FRANKENPHP_MODE (REQ-DEMO-010)

In `.env.example` / Compose:

```dotenv
FRANKENPHP_MODE=worker
```

| Value | Behaviour |
|-------|-----------|
| `worker` (default) | `php_server { worker ... }` — app stays in memory; bundle resets translator via `ResetInterface` |
| `classic` | Classic `php_server` without worker |

Switch mode by changing `.env` and recreating containers (`docker compose up -d`); no image rebuild required.

See `docker/entrypoint.sh` and `docker/frankenphp/Caddyfile` / `Caddyfile.dev`.

## Worker compatibility

`WorkerSafeGoogleTranslate` implements `ResetInterface` and is tagged `kernel.reset`, so target/source/`lastDetectedSource`/`preserve_parameters` do not leak across requests.

## Timeouts (REQ-RUNTIME-001)

Demo profile defaults: `timeout: 10`, `connect_timeout: 5`. Keep PHP `max_execution_time` and Caddy write timeouts **greater** than the profile timeout. Documented in [CONFIGURATION.md](CONFIGURATION.md).

## Run

From the bundle root:

```bash
make -C demo up-symfony8
```

Open the URL printed by `make up` (`Demo started at: http://localhost:<PORT>`).
