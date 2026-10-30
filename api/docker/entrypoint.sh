#!/bin/sh
set -e
cd /var/www/html

# Copy built public assets (Filament, favicon) into the volume served by nginx.
if [ -d /var/www/public-shared ]; then
    cp -a public/. /var/www/public-shared/
fi

if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
    php artisan migrate --force
    php artisan storage:link 2>/dev/null || true
    php artisan config:cache && php artisan route:cache && php artisan view:cache
    # administrator from ADMIN_EMAIL / ADMIN_PASSWORD (created once, password not changed afterwards)
    [ -n "${ADMIN_EMAIL:-}" ] && php artisan site:admin "$ADMIN_EMAIL" || true
    # content snapshot of tourism.gov.ua; a new snapshot (other checksum) is imported once more,
    # records edited in the admin are kept
    sum="$(cat database/seed-data/*.json 2>/dev/null | md5sum | cut -c1-12)"
    if ls database/seed-data/*.json >/dev/null 2>&1 && [ ! -f "storage/app/.imported-$sum" ]; then
        php artisan site:import >> storage/logs/import.log 2>&1 && touch "storage/app/.imported-$sum"
    fi
fi
# artisan above runs as root; php-fpm (www-data) must be able to write the file cache, views and logs it created
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

exec "$@"
