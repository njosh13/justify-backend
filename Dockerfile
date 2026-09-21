# syntax=docker/dockerfile:1

##########################################################
# Stage 1 — PHP dependencies (Composer)
##########################################################
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
# This stage only DOWNLOADS packages (no scripts run here — artisan isn't
# copied yet), so the builder's PHP version/extensions are irrelevant — skip
# the platform check. The autoloader IS generated so `php artisan` works in
# the frontend stage (Wayfinder) and runtime stage.
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --ignore-platform-reqs


##########################################################
# Stage 2 — build the frontend assets with Vite
# Needs PHP: the Wayfinder plugin runs `php artisan wayfinder:generate`
# during the Vite build, so this stage is PHP-based with Node copied in.
##########################################################
FROM php:8.4-cli-bookworm AS frontend

# Node + pnpm (via corepack) from the official Node image.
COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -sf /usr/local/lib/node_modules/corepack/dist/corepack.js /usr/local/bin/corepack \
    && corepack enable \
    && corepack prepare pnpm@10.17.0 --activate

WORKDIR /app

COPY package.json pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile

COPY . .
# vendor must be present for artisan (Wayfinder) — it's excluded by .dockerignore.
COPY --from=vendor /app/vendor ./vendor

# artisan needs a .env to boot; the key is build-time only.
RUN cp .env.example .env \
    && php artisan key:generate --force --no-interaction \
    && pnpm build \
    && rm -f .env


##########################################################
# Stage 3 — production runtime (PHP-FPM + Nginx)
##########################################################
FROM serversideup/php:8.4-fpm-nginx AS app

# Production PHP tuning. AUTORUN is enabled only on the web service (see compose),
# so migrations don't race across the worker/scheduler containers.
ENV PHP_OPCACHE_ENABLE=1 \
    AUTORUN_ENABLED=false

WORKDIR /var/www/html

# curl is used by the container healthcheck. The base image doesn't ship
# pgsql or intl — the app needs both (Postgres + brick/money locale formatting).
USER root
RUN apt-get update \
    && apt-get install -y --no-install-recommends curl \
    && install-php-extensions pgsql pdo_pgsql intl bcmath gd pcntl \
    && rm -rf /var/lib/apt/lists/*
USER www-data

# Application source.
COPY --chown=www-data:www-data . .

# PHP deps from the composer stage + compiled assets from the frontend stage.
COPY --chown=www-data:www-data --from=vendor /app/vendor ./vendor
COPY --chown=www-data:www-data --from=frontend /app/public/build ./public/build

# Finalize the optimized autoloader (runs package:discover), writable runtime dirs,
# and the public storage symlink.
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && composer dump-autoload --optimize --no-dev \
    && php artisan storage:link || true \
    && chown -R www-data:www-data storage bootstrap/cache
