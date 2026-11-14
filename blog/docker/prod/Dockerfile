# Production image for the blog: nginx + PHP-FPM + the queue worker under
# supervisor, in one container. Built by GitHub Actions (build context:
# blog/), pushed to GHCR as <repo>/blog, run on the server by
# docker-compose.prod.yml. Local development keeps blog/Dockerfile.

# ---- Stage 1: PHP dependencies ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist --ignore-platform-reqs
COPY . .
# Scripts (package discovery) need the runtime's PHP extensions, so they run
# in the last stage instead.
RUN composer dump-autoload --optimize --no-dev --no-scripts

# ---- Stage 2: runtime ----
FROM php:8.4-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx supervisor \
        libicu-dev libpq-dev libzip-dev libpng-dev libjpeg-dev libwebp-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" intl pdo_pgsql zip gd bcmath exif opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/* \
    && rm -f /etc/nginx/sites-enabled/default

# php.ini-production: errors go to the log, never into a response body.
# Uploads: the bot sends Telegram media up to Telegram's own 20 MB cap; PHP's
# default of 2 MB would reject most photos and every video.
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && sed -i \
        -e 's/^;\?log_errors\s*=.*/log_errors = On/' \
        -e 's#^;\?error_log\s*=.*#error_log = /dev/stderr#' \
        -e 's/^;\?cgi.fix_pathinfo\s*=.*/cgi.fix_pathinfo = 0/' \
        -e 's/^;\?expose_php\s*=.*/expose_php = Off/' \
        -e 's/^upload_max_filesize\s*=.*/upload_max_filesize = 25M/' \
        -e 's/^post_max_size\s*=.*/post_max_size = 26M/' \
        "$PHP_INI_DIR/php.ini"

# The image is immutable - code changes only with a new image - so OPcache
# never needs to re-check file timestamps.
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.enable_cli=0'; \
        echo 'opcache.memory_consumption=96'; \
        echo 'opcache.interned_strings_buffer=16'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.validate_timestamps=0'; \
    } > "$PHP_INI_DIR/conf.d/opcache.ini"

WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html

# Without the package manifest Laravel would not register Filament at all.
# Filament's CSS, JS and fonts are published into public/ (git-ignored, so a
# clean CI checkout has none); without them the admin renders as bare HTML.
# The test fails the build instead of shipping an unstyled admin.
RUN php artisan package:discover --ansi \
    && php artisan filament:assets \
    && test -f public/css/filament/filament/app.css \
    && test -f public/js/filament/filament/app.js \
    && test -f public/fonts/filament/filament/inter/index.css

COPY docker/prod/nginx.conf /etc/nginx/sites-enabled/default.conf
COPY docker/prod/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/prod/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh \
    && rm -f docker-entrypoint.sh Dockerfile \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/app/public storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD curl -fsS -o /dev/null http://127.0.0.1/up || exit 1

ENTRYPOINT ["entrypoint.sh"]
