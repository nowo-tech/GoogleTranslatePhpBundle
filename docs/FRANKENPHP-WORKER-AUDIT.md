# FrankenPHP worker mode audit (kernel not reset between requests)

| Field | Value |
|-------|-------|
| Package | `nowo-tech/google-translate-php-bundle` (`symfony-bundle`) |
| Audited revision | `v1.0.7` |
| Audit date | 2026-09-24 |
| Method | Manual review of every file under `src/` (translator service, DI extension, configuration, request subscriber) plus the upstream `stichoza/google-translate-php` class it extends |
| Remediation (2026-09-23/24) | W-01 fixed (bundle-owned `kernel.request` subscriber resets instantiated profiles on every main request; `translate()` clears `lastDetectedSource`); W-02 fixed (`reset()` restores URL, client, options, token provider; URL/client moved to constructor args); `trans()` gets default timeouts; regression tests simulate consecutive requests on the same container without `reset()` |
| **Verdict** | ✅ **Viable under scenario B** — every main request starts from the profile defaults whether or not `services_resetter` runs; setter changes are scoped to the current request |

## Execution model assumed

FrankenPHP worker mode boots the Symfony kernel once per worker and serves many requests with the same container. This audit assumes the **strict** variant: the kernel is **not** rebooted between requests, so every shared service, static property and PHP global survives from one request to the next. Two scenarios are evaluated:

- **A — kernel not rebooted, `services_resetter` still runs:** services tagged `kernel.reset` (or implementing `ResetInterface`) are reset between requests.
- **B — no reset at all:** nothing is reset; any per-request state kept in a service leaks into the next request.

A bundle that is safe under **B** is safe under **A** and under classic mode / PHP-FPM.

## Summary

| Area | Status | Notes |
|------|--------|-------|
| Mutable state in shared services | ✅ | Upstream `GoogleTranslate` keeps `$source`, `$target`, `$pattern`, `$lastDetectedSource`, `$url`, `$options`, `$urlParams`, `$tokenProvider` behind public setters; all are restored at the start of each main request (W-01) and by `reset()` (W-02) |
| Static properties / `static` locals | ✅ | Upstream `static $index` in `extractParameters()` is replaced by a per-call counter (`src/Translator/WorkerSafeGoogleTranslate.php:93-108`); no static properties in the bundle |
| `ResetInterface` / `kernel.reset` coverage | ✅ | Every profile service implements `ResetInterface` and is tagged `kernel.reset`; `reset()` covers every mutable setting (W-02). Correctness no longer depends on it: `ResetTranslatorsOnRequestSubscriber` calls it on each main request (W-01) |
| Request / user / locale captured in services | ✅ | Nothing read from `RequestStack`, the token or the request locale; languages come from config or explicit setters |
| Superglobals, `$_ENV`, `putenv`, `ini_set`, `setlocale`, timezone | ✅ | None |
| Doctrine / EntityManager | ✅ N/A | No persistence |
| Output, headers, `exit`, shutdown functions | ✅ | None |
| Resources (files, sockets, cURL) held open | ✅ Info | One Guzzle `Client` per profile for the life of the worker; its cURL handler may keep keep-alive handles open (bounded, W-03) |
| Memory growth across requests | ✅ | No caches or accumulating arrays; log context has no source text |
| Blocking I/O and timeouts | ✅ | `timeout` (default 10 s) and `connect_timeout` (default 5 s) are always set and merged into every Guzzle call |
| Third-party static state | ✅ | Upstream `static $index` neutralised; `GoogleTokenGenerator` has no static state |
| PHPStan FrankenPHP rulesets | ✅ | `ruleset-classic.neon` + `ruleset-worker.neon` included in `phpstan.neon.dist` |

Worker demo: `demo/symfony8/docker/frankenphp/Caddyfile` has a `worker` block; `Caddyfile.dev` runs classic mode.

## Services reviewed

| Service | Shared | Mutable state | Scenario A | Scenario B |
|---------|--------|---------------|------------|------------|
| `nowo_google_translate_php.translator.<profile>` (`WorkerSafeGoogleTranslate`, one per profile) | yes | inherited: `source`, `target`, `pattern`, `lastDetectedSource`, `url`, `options`, `urlParams`, `tokenProvider`, Guzzle `client`; own: `readonly` defaults + logger | ✅ | ✅ (reset on each main request) |
| `ResetTranslatorsOnRequestSubscriber` | yes | none (`readonly`; iterator of already-instantiated profiles, `IGNORE_ON_UNINITIALIZED_REFERENCE`) | ✅ | ✅ |
| Aliases `WorkerSafeGoogleTranslate::class`, `GoogleTranslate::class`, `nowo_google_translate_php.translator` | point to the default profile | same instance as above | same | same |

`Configuration`, `GoogleTranslatePhpExtension`, the bundle class and `UnknownProfileException` only run at container compile time.

## Findings

### W-01 — Per-call settings and `lastDetectedSource` survive when nothing is reset (Medium)

- **Where:** `src/Translator/WorkerSafeGoogleTranslate.php:55-61` (`reset()`), inherited setters `setTarget()`, `setSource()`, `preserveParameters()` and `$lastDetectedSource` in `vendor/stichoza/google-translate-php/src/GoogleTranslate.php:41-56`, `149-166`, `305-313`, `334-344`.
- **Worker impact:** the documented usage calls fluent setters on the shared service (`docs/USAGE.md:44`, `preserveParameters(true)->translate(…)`), and `docs/USAGE.md:51` relies on `kernel.reset` to restore defaults. Under scenario A, `services_resetter` restores the profile defaults after each request, so this is safe. Under scenario B, a request that called `setTarget('fr')` or `preserveParameters(true)` changes the behaviour of the next request, and `getLastDetectedSource()` can return the previous request's value. `translate()` returns early without touching it when source equals target (`GoogleTranslate.php:274`) or when the response is empty (`:285`). The leaked data is a language code and settings, not translated text, so this is not a cross-user content leak.
- **Recommendation:** keep `kernel.reset` enabled (standard Symfony + FrankenPHP runtime). For code that must also be safe under B, do not mutate the shared service: `clone` it per call, or use `WorkerSafeGoogleTranslate::trans()` (it builds a new `static` instance per call; pass `timeout` / `connect_timeout` in `$options`, because they are not added automatically). Do not call `GoogleTranslate::trans()` on the upstream class: it uses the upstream `static $index` counter. Always call `setTarget()` / `setSource()` right before `translate()` and read `getLastDetectedSource()` only after a successful `translate()` in the same request.
- **Status:** Resolved — new `src/EventSubscriber/ResetTranslatorsOnRequestSubscriber.php` (registered by `GoogleTranslatePhpExtension` with an `IteratorArgument` of `IGNORE_ON_UNINITIALIZED_REFERENCE` references, so unused profiles are not instantiated) calls `reset()` on `kernel.request` for main requests only, priority 4096. `WorkerSafeGoogleTranslate::translate()` sets `lastDetectedSource` to `null` before delegating, so early returns cannot expose a stale value. `WorkerSafeGoogleTranslate::trans()` now adds `DEFAULT_TRANS_OPTIONS` (10 s / 5 s) when missing; its placeholder counter was already per call. The upstream static `GoogleTranslate::trans()` (called on the upstream class name) still uses the upstream counter — documented, not reachable through the bundle's services. Tests: `tests/Unit/Translator/WorkerModeWithoutResetTest.php`.

### W-02 — `reset()` does not restore URL, client, Guzzle options or token provider (Low)

- **Where:** `src/Translator/WorkerSafeGoogleTranslate.php:55-61` resets only `lastDetectedSource`, target, source and `preserveParameters`. `setUrl()` / `setClient()` are applied once as DI method calls (`src/DependencyInjection/GoogleTranslatePhpExtension.php:91-95`), and `$options` is set in the constructor (`:74-86`). The public setters `setUrl()`, `setClient()`, `setOptions()`, `setTokenProvider()` (`GoogleTranslate.php:173-214`) are not undone.
- **Worker impact:** the bundle itself never calls these setters at runtime, so the default setup is not affected. If application code calls one of them on the shared service (for example `setOptions()` with a per-tenant proxy or headers), the change stays for every later request in that worker, in scenario A as well as B. `setOptions([])` would also drop the configured `timeout` / `connect_timeout`.
- **Recommendation:** in the bundle, store the initial URL, `urlParams['client']`, options and token provider and restore them in `reset()`. For integrators: use a separate profile instead of calling these setters, or `clone` the service first.
- **Status:** Resolved — `WorkerSafeGoogleTranslate` stores URL, client, options and token provider after construction and restores them in `reset()`. URL and client are new optional constructor arguments (`$url`, `$client`), wired by the extension instead of `setUrl()` / `setClient()` method calls, so the configured values are the reset defaults.

### W-03 — Long-lived Guzzle client (Info)

- **Where:** upstream constructor `GoogleTranslate.php:135` (`new Client()`), requests at `:435-437` and `:523` with `$this->options`.
- **Worker impact:** the client is created once per profile service and reused. It has no cookie jar (no `cookies` client option), so no session data is shared between requests. Its default cURL handler may reuse keep-alive connections, which is bounded and helps performance. Timeouts from the profile are passed on every request, including `languages()`.
- **Recommendation:** none. Do not put a `CookieJar` or per-user credentials into `guzzle_options`: they would be shared by every request in the worker.
- **Status:** Accepted — bounded, no per-user data.

No other findings. The override of `extractParameters()` fixes the upstream `static $index` counter, which in a long-lived worker would keep growing across calls and requests, and `translate()` logs only target, source and byte length (no source text).

## Usage recommendations in worker mode

- Setter changes last for the current request only: the bundle resets instantiated profiles at the start of each main request, with or without `services_resetter`. Keep `services_resetter` for long-running CLI consumers (Messenger), where there is no `kernel.request`.
- Prefer one profile per target / endpoint / proxy combination over calling setters at runtime.
- If you must change settings for a single call inside a request (or in CLI), `clone` the injected service (the clone shares the Guzzle client, which is fine) or use `WorkerSafeGoogleTranslate::trans()`.
- Keep `timeout` / `connect_timeout` low (defaults 10 s / 5 s) and cap waiting requests with FrankenPHP `max_wait_time`; Google rate limits (`RateLimitException`) block a worker thread only for the configured timeout.
- Subclasses of `WorkerSafeGoogleTranslate` must add any new mutable property to `reset()`.

## Re-audit triggers

Re-run this audit when a change adds: properties or setters to `WorkerSafeGoogleTranslate`, a translation cache, a new `stichoza/google-translate-php` major/minor version (re-check `extractParameters()`, new mutable properties or static state), a custom token provider with state, or runtime calls to `setUrl()` / `setOptions()` from the bundle.
