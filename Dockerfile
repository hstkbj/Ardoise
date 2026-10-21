# syntax=docker/dockerfile:1.7
# Image unique (php-fpm + nginx gérés par supervisord) utilisée par les services
# app, queue et scheduler de docker-compose.yml ; seule la commande change.

# --- Étape 1 : assets front (Vite) -------------------------------------------
FROM node:20-alpine AS node
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# --- Étape 2 : dépendances PHP de production ----------------------------------
FROM composer:2 AS composer
WORKDIR /app
COPY composer.json composer.lock ./
# --no-scripts : artisan n'existe pas encore ; les extensions sont fournies par l'image finale
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    --no-scripts --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts --ignore-platform-reqs

# --- Étape 3 : image finale ---------------------------------------------------
FROM php:8.4-fpm-alpine AS app

# mariadb-client fournit mysqldump (backup:run) ; gzip pour les .sql.gz
# pdo_sqlite et sqlite3 sont déjà compilés dans l'image php officielle
RUN apk add --no-cache nginx supervisor mariadb-client gzip \
        icu-libs libzip libpng libjpeg-turbo freetype \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev libpng-dev \
        libjpeg-turbo-dev freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql intl zip gd bcmath opcache \
    && apk del .build-deps \
    && printf '[client]\nskip-ssl\n' > /etc/my.cnf.d/ardoise.cnf

# Vérifie que SQLite est bien présent (le build échoue clairement sinon)
RUN php -m | grep -i pdo_sqlite

RUN cat > /usr/local/etc/php/conf.d/ardoise.ini <<'INI'
opcache.enable=1
opcache.memory_consumption=192
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
upload_max_filesize=10M
post_max_size=12M
memory_limit=256M
expose_php=0
INI

# nginx et supervisord sans privilèges : tout tourne sous www-data, port 8080
RUN rm -f /etc/nginx/nginx.conf && cat > /etc/nginx/nginx.conf <<'NGINX'
pid /tmp/nginx.pid;
worker_processes auto;
error_log /dev/stderr warn;
events { worker_connections 1024; }
http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;
    access_log /dev/stdout;
    sendfile on;
    server_tokens off;
    client_body_temp_path /tmp/nginx-body;
    fastcgi_temp_path /tmp/nginx-fastcgi;
    proxy_temp_path /tmp/nginx-proxy;
    uwsgi_temp_path /tmp/nginx-uwsgi;
    scgi_temp_path /tmp/nginx-scgi;
    include /etc/nginx/http.d/*.conf;
}
NGINX
RUN rm -f /etc/supervisord.conf && cat > /etc/supervisord.conf <<'SUP'
RUN ls -l /etc/supervisord.conf /etc/nginx/nginx.conf
[supervisord]
nodaemon=true
user=www-data
logfile=/dev/null
logfile_maxbytes=0
pidfile=/tmp/supervisord.pid

[program:php-fpm]
command=php-fpm -F
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
autorestart=true

[program:nginx]
command=nginx -g "daemon off;"
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
autorestart=true
SUP
COPY docker/nginx/ardoise.conf /etc/nginx/http.d/ardoise.conf
RUN rm -f /etc/nginx/http.d/default.conf

WORKDIR /var/www/html
COPY --from=composer --chown=www-data:www-data /app ./
COPY --from=node --chown=www-data:www-data /app/public/build ./public/build

RUN mkdir -p storage/app/public storage/framework/cache storage/framework/sessions \
        storage/framework/views storage/logs bootstrap/cache database/tenants \
    && chown -R www-data:www-data storage bootstrap/cache database \
    && php artisan package:discover --ansi \
    && php artisan storage:link --force \
    && chown -h www-data:www-data public/storage

USER www-data
EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s \
    CMD wget -q -O /dev/null http://127.0.0.1:8080/up || exit 1
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
