#!/bin/sh
set -e

PORT="${PORT:-10000}"
echo "Configuring Nginx to listen on port ${PORT}..."
sed -i "s/PORT_PLACEHOLDER/${PORT}/g" /etc/nginx/http.d/default.conf

# Ensure storage and cache directories exist with proper permissions
mkdir -p /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/framework/cache \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Production cache optimizations
if [ "${APP_ENV}" = "production" ]; then
    echo "Caching Laravel configuration and routes..."
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
fi

# Run database migrations if enabled
if [ "${RUN_MIGRATIONS:-false}" = "true" ] || [ "${RUN_MIGRATIONS:-false}" = "1" ]; then
    echo "Running database migrations..."
    php artisan migrate --force --no-interaction
fi

# Run initial database seeding if requested
if [ "${RUN_SEEDER:-false}" = "true" ] || [ "${RUN_SEEDER:-false}" = "1" ]; then
    echo "Running initial database seeding..."
    php artisan db:seed --force --no-interaction
fi

echo "Starting Supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf

