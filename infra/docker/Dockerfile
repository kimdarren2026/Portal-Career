# syntax=docker/dockerfile:1
#
# Portal Karir Kampus — production runtime images.
#
# ONE Portal Career release, built from ONE Dockerfile at ONE commit, exposing
# two runtime profiles (DEPLOYMENT_ARCHITECTURE.md §2, SYSTEM_ARCHITECTURE.md §1,
# ADR-017):
#
#   target: app-runtime   PHP 8.4 · PHP-FPM + nginx co-located (one instance)
#     web        : supervisord -> php-fpm -F  +  nginx -g 'daemon off;'
#     worker     : php artisan queue:work --queue=mail,notifications,default --max-time=3600 --tries=1 --backoff=0
#     scheduler  : php artisan schedule:work            (exactly one instance)
#
#   target: ssr-runtime   Node 22 · Inertia SSR renderer only (ADR-017)
#     ssr        : node bootstrap/ssr/ssr.js            (no PHP, no nginx, no DB)
#
# The two targets are built together, in lockstep, from the same source tree by
# infra/docker/compose.production.yaml. org.opencontainers.image.revision is
# stamped on both from the same GIT_REVISION build arg as an audit aid.
#
# Build context is the repository root:
#   docker build -f infra/docker/Dockerfile --target app-runtime -t portal-career-app:<sha> .
#   docker build -f infra/docker/Dockerfile --target ssr-runtime -t portal-career-ssr:<sha> .

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
# Stage 3 — SSR runtime (Node 22, renderer only; ADR-017)
# ----------------------------------------------------------------------------
# vite.config.ts sets `ssr.noExternal: true`, so bootstrap/ssr/ssr.js is a
# single self-contained bundle — its only imports are Node built-ins. No
# node_modules, no PHP, no nginx, no Composer in this image.
FROM node:22-alpine AS ssr-runtime

ARG GIT_REVISION=unknown
LABEL org.opencontainers.image.title="Portal Karir Kampus — SSR renderer" \
      org.opencontainers.image.revision="${GIT_REVISION}"

RUN apk add --no-cache tini

ENV NODE_ENV=production
WORKDIR /var/www/app
COPY --from=frontend /app/apps/api/bootstrap/ssr ./bootstrap/ssr

USER node
EXPOSE 13714
ENTRYPOINT ["/sbin/tini", "--"]
# @inertiajs/vue3/server listens on 0.0.0.0:13714 and answers GET /health.
CMD ["node", "bootstrap/ssr/ssr.js"]

# ----------------------------------------------------------------------------
# Stage 4 — Application runtime (PHP-FPM + nginx co-located, non-root)
# ----------------------------------------------------------------------------
FROM php:8.4-fpm AS app-runtime

ARG GIT_REVISION=unknown
LABEL org.opencontainers.image.title="Portal Karir Kampus — application" \
      org.opencontainers.image.revision="${GIT_REVISION}"

# Debian (glibc), not Alpine (musl): musl's stub resolver has long-standing
# slow/hanging DNS lookups under some container network setups, for BOTH
# compose-internal service names and external hosts — observed in production
# as every dependency check (Postgres, Redis, S3) blocking for tens of
# seconds. glibc's resolver does not share this failure mode.
#
# nginx + supervisor support the co-located web role (DEPLOYMENT_ARCHITECTURE.md
# §2: one "PHP-FPM + Nginx" instance). worker/scheduler override CMD and never
# start nginx or supervisor. $PHPIZE_DEPS (build toolchain) is purged after the
# extensions are built; the -dev libs stay installed so their runtime .so's
# remain available — simpler and safer than tracking Debian's versioned
# runtime package names (e.g. libicu72) across releases.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
      ca-certificates tini nginx supervisor wget procps \
      libicu-dev libpq-dev libonig-dev $PHPIZE_DEPS; \
    docker-php-ext-configure intl; \
    docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql intl bcmath pcntl opcache; \
    apt-get purge -y --auto-remove $PHPIZE_DEPS; \
    rm -rf /var/lib/apt/lists/*

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

# Co-located web role: nginx runs as the same non-root www-data user as php-fpm
# and reaches it over 127.0.0.1:9000. The FROZEN app-tier vhost
# (infra/docker/nginx.conf) is copied byte-for-byte; nginx.main.conf only wraps
# it in http{} and relocates writable paths so no root is needed.
COPY infra/docker/nginx.conf       /etc/nginx/http.d/app.conf
COPY infra/docker/nginx.main.conf  /etc/nginx/nginx.conf
COPY infra/docker/supervisord.conf /etc/supervisord.conf
RUN set -eux; \
    mkdir -p /tmp/nginx/client_body /tmp/nginx/proxy /tmp/nginx/fastcgi \
             /tmp/nginx/uwsgi /tmp/nginx/scgi /var/lib/nginx /var/log/nginx; \
    chown -R www-data:www-data /tmp/nginx /var/lib/nginx /var/log/nginx

WORKDIR /var/www/app
COPY --from=php-deps  /app/apps/api                   ./
COPY --from=frontend  /app/apps/api/public/build      ./public/build
COPY --from=frontend  /app/apps/api/bootstrap/ssr     ./bootstrap/ssr

# Writable runtime state only; code stays read-only and owned by root.
RUN set -eux; \
    mkdir -p storage/framework/cache/data storage/framework/sessions \
            storage/framework/views storage/logs bootstrap/cache; \
    chown -R www-data:www-data storage bootstrap/cache; \
    chmod -R ug+rw storage bootstrap/cache

USER www-data
# 8080 = nginx (the ingress Dokploy/Traefik routes to); 9000 = php-fpm (internal).
EXPOSE 8080 9000
# Debian's tini package installs to /usr/bin/tini (Alpine's is /sbin/tini).
ENTRYPOINT ["/usr/bin/tini", "--"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
