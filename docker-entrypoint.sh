#!/bin/sh
set -e

cd /app

# A reused cache volume may contain providers from development-only packages.
# Remove manifests before booting Artisan, which cannot clear a broken manifest.
php -r 'foreach (["bootstrap/cache/packages.php", "bootstrap/cache/services.php"] as $file) { if (is_file($file) && !unlink($file)) { exit(1); } }'
php artisan package:discover --no-interaction

if [ "${APP_ENV:-production}" = "local" ] && [ -f /app/.env ]; then
    sed -i "s|^DB_HOST=.*|DB_HOST=${DB_HOST:-}|" /app/.env
    sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${DB_DATABASE:-}|" /app/.env
    sed -i "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USERNAME:-}|" /app/.env
    sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD:-}|" /app/.env
fi

exec php artisan serve --host=0.0.0.0 --port=8080
