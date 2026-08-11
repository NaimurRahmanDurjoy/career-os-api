#!/bin/sh

# Run migrations
php artisan migrate --force

# Start php-fpm in background
php-fpm -D

# Start NGINX in foreground
nginx -g "daemon off;"
