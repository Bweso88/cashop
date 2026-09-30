# PHP-FPM 8.3 pour le backend Laravel.
FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache icu-libs libpq libzip libsodium \
 && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev postgresql-dev libzip-dev libsodium-dev linux-headers \
 && docker-php-ext-install -j"$(nproc)" intl pdo_pgsql bcmath zip pcntl opcache sodium \
 && pecl install redis \
 && docker-php-ext-enable redis \
 && apk del .build-deps

COPY infra/docker/php/php.ini /usr/local/etc/php/conf.d/cashop.ini
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html

# ─── Développement : sources montées en volume ────────────────────────────
FROM base AS dev
RUN apk add --no-cache $PHPIZE_DEPS linux-headers && pecl install pcov && docker-php-ext-enable pcov
USER www-data

# ─── Production : image immuable, sans outils de build ────────────────────
FROM base AS prod
COPY --chown=www-data:www-data backend/ /var/www/html/
RUN composer install --no-dev --optimize-autoloader --no-interaction \
 && php artisan event:cache && php artisan route:cache && php artisan view:cache
USER www-data
