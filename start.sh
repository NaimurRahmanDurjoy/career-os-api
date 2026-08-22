#!/bin/sh

# Run migrations
php artisan migrate --force

# Start php-fpm in background
php-fpm -D

# Start Laravel queue worker in background
php artisan queue:work --tries=3 --timeout=90 &

# Start NGINX in foreground
nginx -g "daemon off;"
