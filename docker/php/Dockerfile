FROM php:8.4-fpm-alpine

COPY --from=ghcr.io/mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN apk add --no-cache git unzip \
    && install-php-extensions pdo_mysql sodium

WORKDIR /var/www/backend
