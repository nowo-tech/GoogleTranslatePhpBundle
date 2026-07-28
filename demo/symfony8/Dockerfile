# FrankenPHP - PHP 8.5 Alpine (Symfony 8.1 requires PHP >= 8.4)
FROM dunglas/frankenphp:1-php8.5-alpine

RUN apk add --no-cache git unzip bash
# intl: use native Normalizer/Grapheme so we can replace symfony/polyfill-intl-* (avoids broken Resources/stubs in partial installs).
RUN install-php-extensions intl pcov zip
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN git config --global --add safe.directory /app && \
    git config --global --add safe.directory /var/google-translate-php-bundle

WORKDIR /app
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV PATH="/app/vendor/bin:${PATH}"
ENV SERVER_NAME=:80

# Custom Caddyfile: HTTP on port 80. Caddyfile.dev used when APP_ENV=dev.
COPY docker/frankenphp/Caddyfile /etc/frankenphp/Caddyfile
COPY docker/frankenphp/Caddyfile.dev /etc/frankenphp/Caddyfile.dev

# Entrypoint: ensure var dirs, when APP_ENV=dev copy Caddyfile.dev (no worker), then run FrankenPHP
COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
