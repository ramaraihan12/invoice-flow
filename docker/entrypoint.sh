#!/bin/sh
set -e

# Uploads and exports live on a volume, which starts out owned by root.
chown -R www-data:www-data storage

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

exec "$@"
