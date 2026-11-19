# syntax=docker/dockerfile:1.7
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --no-interaction \
        --prefer-dist \
        --ignore-platform-reqs

FROM node:22-alpine AS assets

WORKDIR /build

COPY package.json package-lock.json* ./
RUN npm ci --no-audit --no-fund || npm install --no-audit --no-fund

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
COPY app ./app
COPY --from=vendor /app/vendor ./vendor

RUN npm run build

# ---------------------------------------------------------------------------
# PWA stage: export the Expo shooter app as a static web bundle so Laravel's
# nginx can serve it at /app. The bundle is a self-contained SPA (routes
# resolved client-side by Expo Router). experiments.baseUrl=/app in
# apps/mobile/app.json prefixes every generated asset URL with /app so the
# JS chunks load from /var/www/html/public/app/_expo/... on production.
# This stage must stay separate from `assets` — different lockfile, different
# toolchain, and Expo pulls a large dep tree we don't want in the Vite cache.
# ---------------------------------------------------------------------------
FROM node:22-alpine AS pwa

WORKDIR /build

COPY apps/mobile/package.json apps/mobile/package-lock.json* ./
RUN npm ci --no-audit --no-fund || npm install --no-audit --no-fund

COPY apps/mobile ./

# Expo inlines every EXPO_PUBLIC_* env var into the JS bundle at export
# time. Empty EXPO_PUBLIC_API_URL tells apps/mobile/src/api/client.ts to
# issue relative /api/v1/* URLs — the browser resolves those against the
# current origin (shootingsports.co.za), so the PWA hits Laravel on the
# same host with no CORS. HOME_PERSONALISED=1 mirrors the production
# profile in apps/mobile/eas.json so a web shooter and a native shooter
# see the same personalised home.
ENV EXPO_PUBLIC_API_URL=""
ENV EXPO_PUBLIC_HOME_PERSONALISED="1"
RUN npx expo export --platform web

FROM php:8.5-fpm-bookworm AS runtime

ENV DEBIAN_FRONTEND=noninteractive \
    COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        libpq-dev \
        libzip-dev \
        libicu-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libwebp-dev \
        libxml2-dev \
        libonig-dev \
        unzip \
        git \
        curl \
        ca-certificates \
        tzdata \
        poppler-utils \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        bcmath \
        intl \
        zip \
        gd \
        exif \
    && (docker-php-ext-enable opcache || true) \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=assets /build/public/build ./public/build
# Mount the exported Expo PWA at /app. Nginx (see docker/nginx/default.conf)
# falls back to public/app/index.html for client-side routes.
COPY --from=pwa /build/dist ./public/app

# Strip the Expo source that COPY . . dragged in. The runtime image only
# serves public/app/* (populated above) — the React Native source itself
# is useless to PHP-FPM and inflates the image by ~400MB when node_modules
# aren't present and ~1GB+ when a dev build happened before the Docker run.
RUN rm -rf apps \
    && composer dump-autoload -o --no-dev \
    && mkdir -p storage/framework/{cache,sessions,views,testing} storage/logs storage/app/media bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-www.conf
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisor/supervisord.conf /etc/supervisor/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint \
    && ln -sf /dev/stdout /var/log/nginx/access.log \
    && ln -sf /dev/stderr /var/log/nginx/error.log

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint"]
CMD ["app"]
