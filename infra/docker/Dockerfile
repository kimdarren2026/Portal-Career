# syntax=docker/dockerfile:1
#
# Portal Karir Kampus — production application image.
#
# ONE immutable image, run with different commands for the Laravel process
# roles (DEPLOYMENT_ARCHITECTURE.md §2, SYSTEM_ARCHITECTURE.md §1):
#
#   web        : php-fpm                              (fronted by nginx — infra/docker/nginx.conf)
#   worker     : php artisan queue:work --max-time=3600 --tries=1
#   scheduler  : while true; do php artisan schedule:run; sleep 60; done   (exactly one instance)
#
# The Inertia SSR renderer (4th role, ADR-017) is a Node process that runs the
# bundle at apps/api/bootstrap/ssr/ssr.js; it ships in this same build but has
# its own small image profile — see the runbook.
#
# Build context is the repository root:
#   docker build -f infra/docker/Dockerfile -t portal-karir-kampus:<sha> .

# ----------------------------------------------------------------------------
# Stage 1 — PHP dependencies (no dev, optimized autoloader)
# ----------------------------------------------------------------------------
FROM composer:2 AS php-deps
WORKDIR /app/apps/api
COPY apps/api/composer.json apps/api/composer.lock ./
RUN composer install \
      --no-dev --no-interaction --prefer-dist --no-progress --no-scripts \
      --optimize-autoloader --classmap-authoritative
COPY apps/api ./
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

# ----------------------------------------------------------------------------
# Stage 2 — Frontend build (client bundle + SSR bundle, same Vite build)
# ----------------------------------------------------------------------------
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
COPY apps/api/package.json apps/api/
COPY apps/web/package.json apps/web/
RUN npm ci
COPY apps/api ./apps/api
COPY apps/web ./apps/web
RUN npm run build && npm run build:ssr

# ----------------------------------------------------------------------------
# Stage 3 — Runtime (PHP-FPM, no build toolchain, non-root)
# ----------------------------------------------------------------------------
FROM php:8.3-fpm-alpine AS runtime

# Runtime libraries only. Build deps are installed, used, then dropped.
RUN set -eux; \
    apk add --no-cache icu-libs postgresql-libs oniguruma tini; \
    apk add --no-cache --virtual .build-deps \
      $PHPIZE_DEPS icu-dev postgresql-dev oniguruma-dev linux-headers; \
    docker-php-ext-configure intl; \
    docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql intl bcmath pcntl opcache; \
    apk del .build-deps

# Opcache tuned for an immutable image: code never changes at runtime.
RUN { \
      echo 'opcache.enable=1'; \
      echo 'opcache.enable_cli=0'; \
      echo 'opcache.validate_timestamps=0'; \
      echo 'opcache.memory_consumption=192'; \
      echo 'opcache.max_accelerated_files=20000'; \
      echo 'opcache.interned_strings_buffer=16'; \
    } > /usr/local/etc/php/conf.d/opcache.ini
# Transport ceilings sit ABOVE the 10 MiB candidate-document business limit
# (DEPLOYMENT_ARCHITECTURE.md §1). Application validation stays authoritative.
RUN { \
      echo 'upload_max_filesize=12M'; \
      echo 'post_max_size=13M'; \
      echo 'memory_limit=256M'; \
      echo 'expose_php=Off'; \
    } > /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/app
COPY --from=php-deps  /app/apps/api            ./
COPY --from=frontend  /app/apps/api/public/build      ./public/build
COPY --from=frontend  /app/apps/api/bootstrap/ssr     ./bootstrap/ssr

# Writable runtime state only; code stays read-only and owned by root.
RUN set -eux; \
    mkdir -p storage/framework/cache/data storage/framework/sessions \
            storage/framework/views storage/logs bootstrap/cache; \
    chown -R www-data:www-data storage bootstrap/cache; \
    chmod -R ug+rw storage bootstrap/cache

USER www-data
EXPOSE 9000
ENTRYPOINT ["/sbin/tini", "--"]
CMD ["php-fpm"]
