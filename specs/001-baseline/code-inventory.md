# Code inventory — Google Translate PHP Bundle (`src/`)

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/google-translate-php-bundle`  
**Last audited**: 2026-07-28

100% inventory of production artifacts under `src/`. Every file maps to at least one semantic `FR-*` in the baseline product spec (no `FR-SRC-*` placeholders).

## Coverage summary

| Category | Mapped units |
| --- | --- |
| Bundle entry | 1 |
| Dependency injection | 2 |
| Exception | 1 |
| Translator | 1 |
| Resources (YAML) | 1 |
| **Total** | **6** |

Audit: `find src -type f | wc -l` → **6** (equals mapped count).

Coverage target: **100%** lines/methods/classes under `src/` (REQ-TEST-003).

## Bundle entry

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `GoogleTranslatePhpBundle.php` | Bundle / DI | FR-BUNDLE-001 |

## Dependency injection

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `DependencyInjection/Configuration.php` | Bundle / DI | FR-CFG-001, FR-TR-004, FR-SEC-001 |
| `DependencyInjection/GoogleTranslatePhpExtension.php` | Bundle / DI | FR-CFG-002, FR-CFG-003, FR-DI-001, FR-TR-004, FR-OBS-001 |

## Exception

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Exception/UnknownProfileException.php` | Bundle / DI | FR-CFG-003 |

## Translator / runtime

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Translator/WorkerSafeGoogleTranslate.php` | Translator / runtime | FR-TR-001, FR-TR-002, FR-TR-003, FR-TR-004, FR-OBS-001 |

## Resources (non-PHP)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/config/services.yaml` | Resources | FR-DI-001 |
