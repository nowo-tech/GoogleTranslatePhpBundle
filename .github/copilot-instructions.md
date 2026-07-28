# Copilot / AI contribution instructions — GoogleTranslatePhpBundle

## Scope

- This is a **Symfony bundle** wrapping `stichoza/google-translate-php` (`nowo-tech/google-translate-php-bundle` on Packagist).
- Namespace: `Nowo\GoogleTranslatePhpBundle`
- Config alias: `nowo_google_translate_php` (named profiles + Guzzle timeouts)
- Main service: `WorkerSafeGoogleTranslate` (`ResetInterface` for FrankenPHP worker)

## Rules

- English only for docs and comments (REQ-DOCS-016).
- Keep PHPUnit coverage at 100% for `src/`.
- Do not commit secrets or `.env` files.
- Do not add Cursor co-author trailers to commits (REQ-GIT-001).
- Prefer `use` imports over leading-backslash FQCN (REQ-CS-001).

## References

- `docs/ENGRAM.md`, `docs/CONFIGURATION.md`, `docs/USAGE.md`
- `specs/001-baseline/`
