# Engram

Short facts for AI assistants and maintainers.

- Package: `nowo-tech/google-translate-php-bundle`
- Bundle class: `Nowo\GoogleTranslatePhpBundle\GoogleTranslatePhpBundle`
- Config alias: `nowo_google_translate_php`
- Main service: `WorkerSafeGoogleTranslate` (aliases `GoogleTranslate` + `nowo_google_translate_php.translator`)
- Profiles: `default_profile` + `profiles.*.timeout` / `connect_timeout` (Guzzle)
- FrankenPHP: supported via `ResetInterface` / `kernel.reset`
- No Twig templates / no translation domains / no frontend assets
- Upstream: unofficial Google Translate scraper (`stichoza/google-translate-php`)
- Prefer `translation-yaml-tools-bundle` for official Google Cloud / DeepL / LibreTranslate
