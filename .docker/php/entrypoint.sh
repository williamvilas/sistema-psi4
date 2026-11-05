#!/bin/sh
set -e

cd /var/www/html

# Em produção o código é somente-leitura (build assado na imagem); em local o
# diretório é montado a partir do host, então o sync de assets não se aplica.
if [ "$APP_ENV" = "production" ] && [ -d /var/www/html-public-src ]; then
    rm -rf /var/www/html/public/*
    cp -a /var/www/html-public-src/. /var/www/html/public/
fi

if [ "$APP_ENV" = "production" ]; then
    echo "opcache.validate_timestamps = 0" > /usr/local/etc/php/conf.d/zz-opcache-runtime.ini
else
    echo "opcache.validate_timestamps = 1" > /usr/local/etc/php/conf.d/zz-opcache-runtime.ini
fi

mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache/data storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache

if [ -f .env ] && ! grep -q '^APP_KEY=.\+' .env; then
    php artisan key:generate --force --ansi
fi

if [ "$APP_ENV" = "production" ]; then
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
    php artisan event:clear

    if [ "$AUTO_MIGRATE" = "true" ]; then
        php artisan migrate --force
    fi

    php artisan config:cache
    php artisan route:cache
    php artisan event:cache
    php artisan view:cache
fi

exec "$@"
