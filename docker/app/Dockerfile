# syntax=docker/dockerfile:1.7
# Mawred application image (ADR-0017): FrankenPHP in classic mode. The same image runs the web
# app, Horizon, the scheduler, Reverb and Inertia SSR as separate Compose services.

ARG PHP_VERSION=8.4

FROM dunglas/frankenphp:1-php${PHP_VERSION}-bookworm AS base
# pg_dump must match the server's major version (Postgres 18) for backups, so it comes from PGDG.
RUN install-php-extensions pdo_pgsql pgsql redis intl gd bcmath gmp zip pcntl opcache exif sockets \
    && apt-get update && apt-get install -y --no-install-recommends curl ca-certificates \
    && install -d /usr/share/postgresql-common/pgdg \
    && curl -fsSo /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc https://www.postgresql.org/media/keys/ACCC4CF8.asc \
    && echo "deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.asc] https://apt.postgresql.org/pub/repos/apt bookworm-pgdg main" > /etc/apt/sources.list.d/pgdg.list \
    && apt-get update && apt-get install -y --no-install-recommends postgresql-client-18 \
    && rm -rf /var/lib/apt/lists/*
COPY docker/app/php.ini /usr/local/etc/php/conf.d/zz-mawred.ini
COPY --chmod=0755 docker/app/entrypoint.sh /usr/local/bin/mawred-entrypoint
WORKDIR /app

# --- Build: PHP deps, then front-end (Wayfinder needs PHP to generate typed routes) ---
FROM base AS build
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=node:24-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:24-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/corepack/dist/corepack.js /usr/local/bin/corepack \
    && corepack enable pnpm

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY package.json pnpm-lock.yaml pnpm-workspace.yaml .npmrc ./
RUN pnpm install --frozen-lockfile

COPY . .
RUN cp .env.example .env \
    && composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && php artisan package:discover --ansi \
    && php artisan key:generate --force \
    && pnpm run build:ssr \
    && rm -rf node_modules .env

# --- Runtime ---
FROM base AS app
ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    SERVER_NAME=:8080
# Node runs only the Inertia SSR server (careers pages, D87); the bundle is built above.
COPY --from=node:24-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=build --chown=www-data:www-data /app /app
RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs storage/backups bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && setcap -r /usr/local/bin/frankenphp || true
USER www-data
EXPOSE 8080
HEALTHCHECK --interval=15s --timeout=5s --start-period=20s --retries=3 CMD curl -fsS http://127.0.0.1:8080/up || exit 1
ENTRYPOINT ["mawred-entrypoint"]
CMD ["frankenphp", "php-server", "--root", "/app/public", "--listen", ":8080"]
