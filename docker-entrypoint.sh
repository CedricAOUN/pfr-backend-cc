#!/bin/sh
set -e

cd /app

if [ "${APP_ENV:-production}" = "local" ] && [ -f /app/.env ]; then
    sed -i "s|^DB_HOST=.*|DB_HOST=${DB_HOST:-}|" /app/.env
    sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${DB_DATABASE:-}|" /app/.env
    sed -i "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USERNAME:-}|" /app/.env
    sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD:-}|" /app/.env
fi

exec php artisan serve --host=0.0.0.0 --port=8080
