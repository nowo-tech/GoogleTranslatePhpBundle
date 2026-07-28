# Upgrading

## From unreleased / 0.x

This is the first public line. No migration steps yet.

When upgrading minors:

1. Read [CHANGELOG.md](CHANGELOG.md).
2. Run `composer update nowo-tech/google-translate-php-bundle`.
3. Clear Symfony cache.
4. Confirm `nowo_google_translate_php` profiles still match your app (especially `timeout` / `connect_timeout`).
