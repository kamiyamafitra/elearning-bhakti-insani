# --- Stage 1: build front-end assets with Vite ---
FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json .npmrc ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# --- Stage 2: PHP 8.4 + nginx + php-fpm (serversideup image, listens on port 8080) ---
FROM serversideup/php:8.4-fpm-nginx

ENV PHP_OPCACHE_ENABLE=1

# mysqldump is needed by the daily app:backup-database job.
USER root
RUN apt-get update \
    && apt-get install -y --no-install-recommends default-mysql-client \
    && rm -rf /var/lib/apt/lists/*
USER www-data

WORKDIR /var/www/html

# Install PHP dependencies first so this layer is cached between deploys.
COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi
