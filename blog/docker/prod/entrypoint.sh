#!/usr/bin/env bash
set -e

cd /var/www/html

if [ -z "$APP_KEY" ]; then
    echo "FATAL: APP_KEY is not set. Fill in the server's .env first (see DEPLOY.md)." >&2
    exit 1
fi

# storage/ is the persistent volume: uploaded media, logs, compiled views.
mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
chown -R www-data:www-data storage bootstrap/cache

# public/ is part of the image, so the link to storage/app/public (media,
# served at /storage/...) has to be made again on every start.
php artisan storage:link --force

php artisan config:cache
php artisan route:cache
php artisan view:cache

# Postgres reports healthy a moment before it takes queries; retry briefly.
for attempt in 1 2 3 4 5; do
    if php artisan migrate --force --no-interaction; then
        break
    fi
    if [ "$attempt" = 5 ]; then
        echo "FATAL: migrations failed 5 times." >&2
        exit 1
    fi
    echo "migration attempt ${attempt} failed, retrying..."
    sleep 3
done

# The caches above were written as root.
chown -R www-data:www-data storage bootstrap/cache

exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
