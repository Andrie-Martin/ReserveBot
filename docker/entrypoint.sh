#!/bin/sh
# Runs each time the app container starts, before Apache.
set -e
cd /var/www/html

# Profile photos live in a Docker volume: make sure Apache (www-data) can save them
mkdir -p storage/app/private/photos
chown -R www-data:www-data storage/app/private/photos

if php artisan migrate:status > /dev/null 2>&1; then
    # Database already built: only add tables/columns from new migration files
    php artisan migrate --force
else
    # First start (empty database): build every table, then load the BSCpE data and test accounts
    php artisan migrate --force --seed
fi

exec "$@"
