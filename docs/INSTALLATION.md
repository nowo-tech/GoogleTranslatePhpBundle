# Installation

## Requirements

- PHP >= 8.1, < 8.6
- Symfony 6.0+ / 7.4+ / 8.0 / 8.1 (see root `composer.json`)
- Composer 2

## Composer

```bash
composer require nowo-tech/google-translate-php-bundle
```

With Symfony Flex, the recipe registers the bundle and copies `config/packages/nowo_google_translate_php.yaml`.

Without Flex, enable the bundle:

```php
// config/bundles.php
return [
    // ...
    Nowo\GoogleTranslatePhpBundle\GoogleTranslatePhpBundle::class => ['all' => true],
];
```

Then add configuration (see [CONFIGURATION.md](CONFIGURATION.md)).

## Verify

```bash
php bin/console debug:container WorkerSafeGoogleTranslate
```
