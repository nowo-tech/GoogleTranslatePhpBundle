# Spec-driven development

GoogleTranslatePhpBundle follows Nowo’s Spec Kit baseline ([REQ-SPECKIT-001](https://github.com/nowo-tech/GoogleTranslatePhpBundle/blob/main/docs/SPEC-KIT.md) … [REQ-SPECKIT-003](SPEC-KIT.md)).

## Table of contents

- [Three layers](#three-layers)
- [Product intent](#product-intent)
- [User stories](#user-stories)
- [Traceability](#traceability)
- [Workflow (incremental features)](#workflow-incremental-features)

## Three layers

1. **Product baseline** — `specs/001-baseline/spec.md` (user scenarios + domain `FR-*`) and `code-inventory.md` (100% of `src/`).
2. **Operator manuals** — this document + [`SPEC-KIT.md`](SPEC-KIT.md) (Specify CLI, Cursor skills, constitution).
3. **Validation** — PHPUnit 100% coverage, PHPStan (incl. FrankenPHP classic/worker), CS-Fixer, demos.

## Product intent

Provide a Symfony-native, FrankenPHP worker-safe wrapper around `stichoza/google-translate-php` with named profiles and explicit HTTP timeouts.

## User stories

See [`specs/001-baseline/spec.md`](../specs/001-baseline/spec.md) (US-01 … US-05: translate, profiles, unknown profile, worker reset, timeouts).

## Traceability

| Artifact | Path |
|----------|------|
| Baseline spec | `specs/001-baseline/spec.md` |
| Code inventory | `specs/001-baseline/code-inventory.md` |
| Spec Kit manual | `docs/SPEC-KIT.md` |
| Configuration | `docs/CONFIGURATION.md` |
| Usage | `docs/USAGE.md` |
| Engram | `docs/ENGRAM.md` |

## Workflow (incremental features)

1. Specify / clarify (`/speckit-specify`, `/speckit-clarify`).
2. Plan (`/speckit-plan`).
3. Tasks (`/speckit-tasks`).
4. Implement (`/speckit-implement`).
5. **Converge** (`/speckit-converge`) — append remaining work if the codebase still lags the baseline.
6. Analyze / checklist as needed.

When adding features: update the baseline `FR-*` + inventory row(s), keep coverage at 100%, and document config/timeouts.
