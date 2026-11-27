# ---------- 1) Assets (Vite) ----------
FROM node:22-alpine AS assets
WORKDIR /app
COPY package*.json ./
RUN npm ci --ignore-scripts
COPY . .
RUN npm run build

# ---------- 2) Dependencias PHP ----------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts

# ---------- 3) Imagen final ----------
FROM php:8.4-apache
RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev libpq-dev libsqlite3-dev libonig-dev libxml2-dev unzip \
    && docker-php-ext-install mbstring xml pdo_mysql pdo_pgsql pdo_sqlite zip bcmath opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/*.conf \
    && sed -ri "s!<Directory /var/www/>!<Directory ${APACHE_DOCUMENT_ROOT}/>!" /etc/apache2/apache2.conf \
    && sed -ri '/<Directory \/var\/www\/html\/public\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/php-upload.ini /usr/local/etc/php/conf.d/zz-upload.ini

RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p storage/app/private storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache database

EXPOSE 8080
ENTRYPOINT ["entrypoint.sh"]
