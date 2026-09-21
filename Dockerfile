# syntax=docker/dockerfile:1

##########################################################
# Stage 1 — PHP dependencies (Composer)
##########################################################
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
# This stage only DOWNLOADS packages (no scripts/autoloader run here), so the
# builder's PHP version/extensions are irrelevant — skip the platform check.
# The real platform (PHP 8.4 + ext-pcntl/gd) is provided by the runtime stage.
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader --ignore-platform-reqs


##########################################################
# Stage 2 — build the frontend assets with Vite
##########################################################
FROM node:22-bookworm-slim AS frontend

WORKDIR /app

# pnpm via corepack (matches the project's package manager)
RUN corepack enable && corepack prepare pnpm@10.17.0 --activate

COPY package.json pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile

COPY . .
# resources/js/app.js imports Ziggy from ../../vendor/tightenco/ziggy, so the PHP
# vendor dir must be present for Vite to resolve it (it's excluded by .dockerignore).
COPY --from=vendor /app/vendor ./vendor
RUN pnpm build


##########################################################
# Stage 3 — production runtime (PHP-FPM + Nginx)
##########################################################
FROM serversideup/php:8.4-fpm-nginx AS app

# Production PHP tuning. AUTORUN is enabled only on the web service (see compose),
# so migrations don't race across the worker/scheduler containers.
ENV PHP_OPCACHE_ENABLE=1 \
    AUTORUN_ENABLED=false

WORKDIR /var/www/html

# curl is used by the container healthcheck; qpdf decrypts uploaded bank
# statement PDFs (see App\Services\BankStatements\CoopBankStatementParser).
USER root
RUN apt-get update \
    && apt-get install -y --no-install-recommends curl qpdf \
    && rm -rf /var/lib/apt/lists/*
USER www-data

# Application source.
COPY --chown=www-data:www-data . .

# PHP deps from the composer stage + compiled assets from the frontend stage.
COPY --chown=www-data:www-data --from=vendor /app/vendor ./vendor
COPY --chown=www-data:www-data --from=frontend /app/public/build ./public/build

# Finalize the optimized autoloader (runs package:discover), writable runtime dirs,
# and the public storage symlink.
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && php artisan storage:link || true \
    && chown -R www-data:www-data storage bootstrap/cache
