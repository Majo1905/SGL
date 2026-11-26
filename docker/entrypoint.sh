#!/bin/sh
set -e
cd /var/www/html

# El puerto lo define la plataforma (Render/Railway/Fly usan $PORT)
PORT="${PORT:-8080}"
sed -ri "s/Listen [0-9]+/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# SQLite: crear el archivo si no existe
if [ "${DB_CONNECTION}" = "sqlite" ]; then
    DB_FILE="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
    [ -f "$DB_FILE" ] || touch "$DB_FILE"
    chown www-data:www-data "$DB_FILE"
fi

php artisan storage:link --force || true
php artisan migrate --force
[ -n "${ADMIN_EMAIL}" ] && [ -n "${ADMIN_PASSWORD}" ] && php artisan db:seed --class=AdminSeeder --force || true

php artisan config:cache
php artisan route:cache
php artisan view:cache

exec apache2-foreground
