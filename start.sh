#!/bin/sh

# Run migrations
php artisan migrate --force

# Start Supervisor (This keeps the container alive and manages Nginx, PHP, and Queue Worker)
/usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf
