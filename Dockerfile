# syntax=docker/dockerfile:1

FROM node:22-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY public ./public
COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

FROM php:8.4-apache-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev libonig-dev libpq-dev unzip \
    && docker-php-ext-install -j"$(nproc)" curl mbstring opcache pdo_pgsql \
    && a2enmod headers rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .
COPY --from=frontend /app/public/build ./public/build
COPY apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY php-production.ini /usr/local/etc/php/conf.d/production.ini
COPY render-start.sh /usr/local/bin/render-start

RUN composer install \
        --classmap-authoritative \
        --no-dev \
        --no-interaction \
        --no-progress \
        --prefer-dist \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x /usr/local/bin/render-start

EXPOSE 10000

CMD ["render-start"]
