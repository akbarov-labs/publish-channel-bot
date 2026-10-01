#!/bin/sh
set -e

# The database container may accept connections a moment before it is ready
# for queries, so migrations are retried rather than failing the boot.
for attempt in 1 2 3 4 5; do
    if php artisan migrate --force --no-interaction; then
        break
    fi
    echo "migration attempt ${attempt} failed, retrying..."
    sleep 3
done

php artisan storage:link || true

exec "$@"
