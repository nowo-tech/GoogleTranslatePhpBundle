# Google Translate PHP Bundle

[![CI](https://github.com/nowo-tech/GoogleTranslatePhpBundle/actions/workflows/ci.yml/badge.svg)](https://github.com/nowo-tech/GoogleTranslatePhpBundle/actions/workflows/ci.yml) [![Packagist Version](https://img.shields.io/packagist/v/nowo-tech/google-translate-php-bundle.svg?style=flat)](https://packagist.org/packages/nowo-tech/google-translate-php-bundle) [![Packagist Downloads](https://img.shields.io/packagist/dt/nowo-tech/google-translate-php-bundle.svg)](https://packagist.org/packages/nowo-tech/google-translate-php-bundle) [![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE) [![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php)](https://php.net) [![Symfony](https://img.shields.io/badge/Symfony-6%20%7C%207.4%2B%20%7C%208.0%20%7C%208.1%2B-000000?logo=symfony)](https://symfony.com) [![GitHub stars](https://img.shields.io/github/stars/nowo-tech/GoogleTranslatePhpBundle.svg?style=social&label=Star)](https://github.com/nowo-tech/GoogleTranslatePhpBundle) [![Coverage](https://img.shields.io/badge/Coverage-100%25-brightgreen)](#tests-and-coverage)

> ⭐ **Found this useful?** [Install from Packagist](https://packagist.org/packages/nowo-tech/google-translate-php-bundle) · Give it a **star** on [GitHub](https://github.com/nowo-tech/GoogleTranslatePhpBundle) so more developers can find it.

Symfony bundle wrapping [`stichoza/google-translate-php`](https://github.com/Stichoza/google-translate-php) with **named profiles**, **configurable HTTP timeouts**, and **FrankenPHP worker** compatibility (`ResetInterface`).

![FrankenPHP Friendly Worker Mode](docs/images/frankenphp-friendly.png)

This bundle is **FrankenPHP worker mode friendly**.

## Features

- **Named profiles** (`default_profile` + `profiles`) for target/source languages, Guzzle timeouts, client, URL, and `preserve_parameters`.
- **Worker-safe translator**: clears mutable state between requests under FrankenPHP worker / long-running PHP.
- **Defensive `extractParameters()`**: per-call placeholder counter (no reliance on upstream `static $index`).
- **REQ-RUNTIME-001 timeouts**: `timeout` + `connect_timeout` on every profile.

## Version policy

The Composer package name is [`nowo-tech/google-translate-php-bundle`](https://packagist.org/packages/nowo-tech/google-translate-php-bundle). Source code and issues are in the GitHub repository [`nowo-tech/GoogleTranslatePhpBundle`](https://github.com/nowo-tech/GoogleTranslatePhpBundle).

We follow [Semantic Versioning](https://semver.org/). See [Changelog](docs/CHANGELOG.md) for release notes. Security support by major version is described in the [Security policy](.github/SECURITY.md#supported-versions).

## Quick example

```yaml
# config/packages/nowo_google_translate_php.yaml
nowo_google_translate_php:
    default_profile: default
    profiles:
        default:
            target: es
            timeout: 10.0
            connect_timeout: 5.0
```

```php
use Nowo\GoogleTranslatePhpBundle\Translator\WorkerSafeGoogleTranslate;

final class MyService
{
    public function __construct(
        private readonly WorkerSafeGoogleTranslate $translator,
    ) {
    }

    public function run(string $text): ?string
    {
        return $this->translator->translate($text);
    }
}
```

## Requirements

- PHP >= 8.1, < 8.6
- Symfony 6.0+, 7.4+, 8.0, or 8.1 (see `composer.json`; CI exercises 6.4, 7.0, 7.4, 8.0, and 8.1)
- `stichoza/google-translate-php` ^5.1

## Disclaimer

Upstream scrapes Google Translate (unofficial). Prefer Cloud Translation / DeepL / LibreTranslate for production. See also [`nowo-tech/translation-yaml-tools-bundle`](https://github.com/nowo-tech/TranslationYamlToolsBundle).

## Documentation

- [Installation](docs/INSTALLATION.md)
- [Configuration](docs/CONFIGURATION.md)
- [PSR evaluation (REQ-CS-007)](docs/PSR.md)
- [Usage](docs/USAGE.md)
- [Contributing](docs/CONTRIBUTING.md)
- [Code of Conduct](CODE_OF_CONDUCT.md)
- [Changelog](docs/CHANGELOG.md)
- [Upgrading](docs/UPGRADING.md)
- [Release](docs/RELEASE.md)
- [Security](docs/SECURITY.md)
- [Engram](docs/ENGRAM.md)
- [Spec-driven development](docs/SPEC-DRIVEN-DEVELOPMENT.md)
- [GitHub Spec Kit](docs/SPEC-KIT.md)

### Additional documentation

- [Demo (Symfony 7 & 8)](demo/README.md) — run `make -C demo up-symfony8` from the bundle root.
- [Demo with FrankenPHP (development and production)](docs/DEMO-FRANKENPHP.md)
- [GitHub Actions CI requirements](docs/GITHUB_CI.md)

## Tests and coverage

- Tests: PHPUnit (PHP)
- PHP: 100%
- TS/JS: N/A
- Python: N/A

## License

MIT. See [LICENSE](LICENSE).
