# FrankenPHP worker mode audit (kernel not reset between requests)

| Field | Value |
|-------|-------|
| Package | `nowo-tech/google-translate-php-bundle` (`symfony-bundle`) |
| Audited revision | `v1.0.7`; boundaries re-audited for 1.3.1 (REQ-RUNTIME-002, 2026-11-11) |
| Audit date | 2026-09-24 (Messenger / console boundaries: 2026-11-11) |
| Method | Manual review of every file under `src/` (translator service, DI extension, configuration, request subscriber) plus the upstream `stichoza/google-translate-php` class it extends |
| Remediation (2026-09-23/24) | W-01 fixed (bundle-owned `kernel.request` subscriber resets instantiated profiles on every main request; `translate()` clears `lastDetectedSource`); W-02 fixed (`reset()` restores URL, client, options, token provider; URL/client moved to constructor args); `trans()` gets default timeouts; regression tests simulate consecutive requests on the same container without `reset()` |
| Remediation (2026-11-11, 1.3.1) | W-04 fixed: the same subscriber also resets the profiles at the Messenger message and console command boundaries; one-kernel worker test over HTTP, Messenger and console |
| **Verdict** | ✅ **Viable under scenario B** — every main request, Messenger message and console command starts from the profile defaults whether or not `services_resetter` runs; setter changes are scoped to the current unit of work |

## Table of contents

- [Execution model assumed](#execution-model-assumed)
- [Summary](#summary)
- [Services reviewed](#services-reviewed)
- [Findings](#findings)
  - [W-01 — Per-call settings and `lastDetectedSource` survive when nothing is reset (Medium)](#w-01--per-call-settings-and-lastdetectedsource-survive-when-nothing-is-reset-medium)
  - [W-02 — `reset()` does not restore URL, client, Guzzle options or token provider (Low)](#w-02--reset-does-not-restore-url-client-guzzle-options-or-token-provider-low)
  - [W-03 — Long-lived Guzzle client (Info)](#w-03--long-lived-guzzle-client-info)
  - [W-04 — No reset at the Messenger and console boundaries (Low)](#w-04--no-reset-at-the-messenger-and-console-boundaries-low)
- [Boundary events](#boundary-events)
- [Worker test](#worker-test)
- [Host rule: entity managers](#host-rule-entity-managers)
- [Usage recommendations in worker mode](#usage-recommendations-in-worker-mode)
- [Re-audit triggers](#re-audit-triggers)

## Execution model assumed

FrankenPHP worker mode boots the Symfony kernel once per worker and serves many requests with the same container. This audit assumes the **strict** variant: the kernel is **not** rebooted between requests, so every shared service, static property and PHP global survives from one request to the next. Two scenarios are evaluated:

- **A — kernel not rebooted, `services_resetter` still runs:** services tagged `kernel.reset` (or implementing `ResetInterface`) are reset between requests.
- **B — no reset at all:** nothing is reset; any per-request state kept in a service leaks into the next request.

A bundle that is safe under **B** is safe under **A** and under classic mode / PHP-FPM.

**Precondition, precisely.** `FRANKENPHP_RESET_KERNEL=false` alone does not mean scenario B: with the stock Symfony kernel, `Kernel::handle()` → `boot()` still calls `services_resetter->reset()` before every request after the first. Scenario B (reset-only state leaks) happens where the resetter does not run: a host that removes, replaces or bypasses `services_resetter` (or drives `http_kernel` directly), Messenger consumers started with `messenger:consume --no-reset`, and long-running console processes. This bundle is correct in all of them.

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
| `ResetTranslatorsOnRequestSubscriber` | yes | console nesting depth (bounded) and in-flight message flag, maintained at the boundaries; iterator of already-instantiated profiles, `IGNORE_ON_UNINITIALIZED_REFERENCE`); resets them at every boundary listed in [Boundary events](#boundary-events) | ✅ | ✅ |
| Aliases `GoogleTranslatorInterface::class` (1.4), `GoogleTranslatorInterface $<profile>Translator` (1.4), `WorkerSafeGoogleTranslate::class`, `GoogleTranslate::class` (deprecated 1.4), `nowo_google_translate_php.translator` | point to the default (or named) profile | same instance as above | same | same |
| Copy built by `translateTo()` / `translateManyTo()` (1.4) | no (local to the call) | a `clone` of the profile reset to its defaults, then given the call's languages; shares the Guzzle client and the cache, never stored | ✅ | ✅ (the shared profile is not changed) |

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

### W-04 — No reset at the Messenger and console boundaries (Low)

- **Where:** `src/EventSubscriber/ResetTranslatorsOnRequestSubscriber.php` subscribed only to `kernel.request` (1.0.7 to 1.3.0).
- **Worker impact:** a Messenger consumer has no `kernel.request`. With `messenger:consume --no-reset` (or a host without `services_resetter`), a handler that called `setTarget()`, `setSource()`, `setOptions()` (for example a per-tenant proxy) or `setTokenProvider()` on a shared profile left those settings for the next message; the same between console commands run in one long-lived process. Configuration only, no translated text.
- **Status:** Resolved in 1.3.1 — the subscriber also calls `resetTranslators()` on `WorkerMessageReceivedEvent` (priority 4096), `WorkerMessageHandledEvent` / `WorkerMessageFailedEvent` (priority -4096), `console.command` (4096) and `console.terminate` (-4096). Event names are strings, so `symfony/messenger` and `symfony/console` stay optional. Tests: `tests/Integration/WorkerBoundariesKernelTest.php`, `tests/Unit/Translator/WorkerModeWithoutResetTest.php`.

No other findings. The override of `extractParameters()` fixes the upstream `static $index` counter, which in a long-lived worker would keep growing across calls and requests, and `translate()` logs only target, source and byte length (no source text).

## Usage recommendations in worker mode

- Setter changes last for the current unit of work only: the bundle resets instantiated profiles at the start of each main request, when a Messenger message is received and after it is handled or failed, and when a console command starts and terminates, with or without `services_resetter`.
- Inside **one** console command or Messenger message, settings stay until the next boundary: a long command that walks several tenants must set the target / source again per tenant (or use one profile per tenant).
- Prefer one profile per target / endpoint / proxy combination over calling setters at runtime.
- If you must change settings for a single call inside a request (or in CLI), `clone` the injected service (the clone shares the Guzzle client, which is fine) or use `WorkerSafeGoogleTranslate::trans()`.
- Keep `timeout` / `connect_timeout` low (defaults 10 s / 5 s) and cap waiting requests with FrankenPHP `max_wait_time`; Google rate limits (`RateLimitException`) block a worker thread only for the configured timeout.
- Subclasses of `WorkerSafeGoogleTranslate` must add any new mutable property to `reset()`.

## Boundary events

| Boundary | Event (subscribed by name) | Priority | Action |
|---|---|---|---|
| HTTP main request start | `kernel.request` (main requests only) | 4096 | `reset()` on every instantiated profile |
| Messenger message start | `Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent` | 4096 | same |
| Messenger message end | `WorkerMessageHandledEvent`, `WorkerMessageFailedEvent` | -4096 | same |
| Console command start / end | `console.command` / `console.terminate` | 4096 / -4096 | same, only for the outermost command (depth 0→1 / 1→0) and only while no Messenger message is in flight |
| Messenger worker stopped | `WorkerStoppedEvent` | -4096 | clears the in-flight flag only |
| Services resetter (when the host runs it) | `kernel.reset` tag, method `reset` | — | same (kept, not relied on) |

A command run in-process from another command or from a message handler (`Application::doRun()`) fires `console.command` / `console.terminate` too; the subscriber counts the nesting depth and keeps an in-flight message flag (set on received, unset on handled / failed / worker stopped), so such a nested run never resets the profiles the outer unit configured (audit MI-G-L1; same pattern as BeaconBundle 1.24.0). Tests: `WorkerBoundariesKernelTest::testNestedCommandKeepsTheOuterCommandsSettings`, `testCommandRunFromAMessageHandlerKeepsTheHandlersSettings`.

The subscriber receives an `IteratorArgument` of `IGNORE_ON_UNINITIALIZED_REFERENCE` references, so a boundary never builds a profile that the unit of work did not use.

## Worker test

`tests/Integration/WorkerBoundariesKernelTest.php` boots **one** kernel (`tests/Integration/Fixtures/WorkerTestKernel.php`, FrameworkBundle + this bundle) and never calls `services_resetter`:

- **HTTP:** 500 main `kernel.request` dispatches (no `Kernel::handle()`, so no resetter);
- **Messenger:** 500 messages through a real `Symfony\Component\Messenger\Worker` on the kernel's dispatcher, with no `ResetServicesListener` (as `--no-reset`); every 7th handler fails;
- **Console:** 500 `Symfony\Component\Console\Application::run()` calls on the kernel's dispatcher.

Two tenants alternate (A: `fr`/`de`/proxy A; B: `es`/`it`/proxy B). Each unit asserts that the shared translator starts at the profile defaults, then applies its own settings and leaves them in place. Memory margin: less than 1 MiB growth after a 50-unit warm-up. Without the 1.3.1 boundaries the Messenger and console cases fail (tenant B starts with tenant A's target, source and proxy). `reset()` and `resetTranslators()` are also tested directly (`tests/Unit/Translator/WorkerSafeGoogleTranslateTest.php`, `tests/Unit/Translator/WorkerModeWithoutResetTest.php`).

Static checks: `composer igor` (7 service files, 0 KO, 0 WARN) and PHPStan with the FrankenPHP classic + worker rulesets pass.

## Host rule: entity managers

This bundle uses no Doctrine. A host that disables or bypasses the services resetter must still clear its **own** entity managers at every boundary (main request start or end, each Messenger message, each console command); otherwise the identity map serves stale rows (for example a downgraded permission or a soft-deleted item) across requests (REQ-RUNTIME-002 rule 7).

## Re-audit triggers

Re-run this audit when a change adds: properties or setters to `WorkerSafeGoogleTranslate`, a translation cache, a new `stichoza/google-translate-php` major/minor version (re-check `extractParameters()`, new mutable properties or static state), a custom token provider with state, or runtime calls to `setUrl()` / `setOptions()` from the bundle.
