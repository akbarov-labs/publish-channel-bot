FROM php:8.4-cli

# intl is required by Filament; pdo_pgsql by the blog's own database.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libicu-dev libpq-dev libzip-dev libpng-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" intl pdo_pgsql zip gd bcmath opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# The bot uploads Telegram media up to 20 MB; PHP's default cap is 2 MB.
RUN printf 'upload_max_filesize = 25M\npost_max_size = 26M\n' > "$PHP_INI_DIR/conf.d/uploads.ini"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /srv

COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize

RUN chmod +x docker-entrypoint.sh

EXPOSE 8001

ENTRYPOINT ["./docker-entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8001"]
