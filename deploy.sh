#!/bin/bash
# Deploy en VPS / hosting con SSH (sin Docker). Ejecutar en la raíz del proyecto.
set -e
[ -f .env ] || { echo "Falta .env (copia .env.production.example)"; exit 1; }

composer install --no-dev --optimize-autoloader
npm ci --ignore-scripts && npm run build

grep -q '^APP_KEY=.\+' .env || php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --class=AdminSeeder --force   # solo crea/actualiza el admin
php artisan storage:link || true

php artisan config:cache
php artisan route:cache
php artisan view:cache

chmod -R ug+rwx storage bootstrap/cache
echo "Listo. Apunta el document root a la carpeta public/"
