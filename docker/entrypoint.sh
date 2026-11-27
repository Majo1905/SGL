#!/bin/sh
set -eu
cd /var/www/html

# El puerto lo define la plataforma (Render/Railway/Fly usan $PORT)
PORT="${PORT:-8080}"
case "$PORT" in
    ''|*[!0-9]*) echo "PORT debe ser un número entero válido." >&2; exit 1 ;;
esac
sed -ri "s/Listen [0-9]+/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Render entrega el dominio público al iniciar el contenedor.
APP_URL="${APP_URL:-${RENDER_EXTERNAL_URL:-http://localhost:${PORT}}}"
export APP_URL
: "${APP_KEY:?Define APP_KEY con una clave generada para producción.}"
: "${ADMIN_EMAIL:?Define ADMIN_EMAIL para crear el administrador inicial.}"
: "${ADMIN_PASSWORD:?Define ADMIN_PASSWORD para crear el administrador inicial.}"

mkdir -p storage/app/private storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# SQLite solo conserva datos cuando su ruta está montada en un disco persistente.
if [ "${DB_CONNECTION:-}" = "sqlite" ]; then
    DB_FILE="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
    [ -f "$DB_FILE" ] || touch "$DB_FILE"
    chown www-data:www-data "$DB_FILE"
fi

php artisan storage:link --force
php artisan migrate --force
php artisan db:seed --class=AdminSeeder --force

php artisan config:cache
php artisan route:cache
php artisan view:cache

exec apache2-foreground
