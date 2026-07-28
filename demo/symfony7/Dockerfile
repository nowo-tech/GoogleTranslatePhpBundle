# FrankenPHP - PHP 8.2 Alpine
FROM dunglas/frankenphp:1-php8.2-alpine

RUN apk add --no-cache git unzip bash
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
