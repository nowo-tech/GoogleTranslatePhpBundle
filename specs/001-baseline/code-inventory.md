# Code inventory — Google Translate PHP Bundle (`src/`)

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/google-translate-php-bundle`  
**Last audited**: 2026-11-16

100% inventory of production artifacts under `src/`. Every file maps to at least one semantic `FR-*` in the baseline product spec (no `FR-SRC-*` placeholders).

## Coverage summary

| Category | Mapped units |
| --- | --- |
| Bundle entry | 1 |
| Dependency injection | 2 |
| Event subscriber | 1 |
| Exception | 7 |
| Translator / port | 2 |
| Worker runtime | 1 |
| Resources (YAML) | 1 |
| **Total** | **15** |

Audit: `find src -type f | wc -l` → **15** (equals mapped count).

Coverage target: **100%** lines/methods/classes under `src/` (REQ-TEST-003).

## Bundle entry

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `GoogleTranslatePhpBundle.php` | Bundle / DI | FR-BUNDLE-001 |

## Dependency injection

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `DependencyInjection/Configuration.php` | Bundle / DI | FR-CFG-001, FR-TR-004, FR-SEC-001 |
| `DependencyInjection/GoogleTranslatePhpExtension.php` | Bundle / DI | FR-CFG-002, FR-CFG-003, FR-DI-001, FR-TR-004, FR-OBS-001, FR-WORKER-001 |

## Event subscriber

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `EventSubscriber/ResetTranslatorsOnRequestSubscriber.php` | Worker / request boundary | FR-WORKER-001 |

## Exception

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Exception/UnknownProfileException.php` | Bundle / DI | FR-CFG-003 |
| `Exception/TranslationExceptionInterface.php` | Translator / errors | FR-ERR-001 |
| `Exception/TranslationRequestFailedException.php` | Translator / errors (HTTP or transport failure) | FR-ERR-001 |
| `Exception/TranslationRateLimitedException.php` | Translator / errors (HTTP 429 / 503) | FR-ERR-001 |
| `Exception/TranslationTextTooLargeException.php` | Translator / errors (HTTP 413) | FR-ERR-001 |
| `Exception/TranslationDecodingFailedException.php` | Translator / errors (undecodable response) | FR-ERR-001 |
| `Exception/LanguagesRequestFailedException.php` | Translator / errors (supported-languages request) | FR-ERR-001 |

## Translator / runtime

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Translator/WorkerSafeGoogleTranslate.php` | Translator / runtime | FR-TR-001 … FR-TR-006, FR-OBS-001, FR-PORT-001 |
| `Contract/GoogleTranslatorInterface.php` | Translation port (since 1.4) | FR-PORT-001 |

## Worker runtime

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Worker/UnitOfWorkFrames.php` | Outermost request / message / command frame tracking | FR-WORKER-001 |

## Resources (non-PHP)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/config/services.yaml` | Resources | FR-DI-001 |
