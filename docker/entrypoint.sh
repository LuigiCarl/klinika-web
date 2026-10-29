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

# Ensure APP_KEY is populated
if [ -z "${APP_KEY}" ]; then
    echo "APP_KEY is not set. Generating key..."
    export APP_KEY=$(php artisan key:generate --show --no-interaction)
fi

# Wait for PostgreSQL database to accept connections
if [ -n "${DB_HOST}" ] || [ -n "${DATABASE_URL}" ] || [ -n "${DB_URL}" ]; then
    echo "Waiting for database connection to be ready..."
    for i in $(seq 1 30); do
        if php artisan db:monitor --max=100 >/dev/null 2>&1 || php artisan migrate:status >/dev/null 2>&1; then
            echo "Database connection established!"
            break
        fi
        echo "Database not ready yet... waiting (attempt $i/30)"
        sleep 2
    done
fi

# Run database migrations if enabled
if [ "${RUN_MIGRATIONS:-false}" = "true" ] || [ "${RUN_MIGRATIONS:-false}" = "1" ]; then
    echo "Running database migrations..."
    php artisan migrate --force --no-interaction || echo "Warning: migrations encountered an issue."
fi

# Run initial database seeding if requested
if [ "${RUN_SEEDER:-false}" = "true" ] || [ "${RUN_SEEDER:-false}" = "1" ]; then
    echo "Running initial database seeding..."
    php artisan db:seed --force --no-interaction || echo "Warning: seeding encountered an issue."
fi

# Production cache optimizations
if [ "${APP_ENV}" = "production" ]; then
    echo "Caching Laravel configuration, routes, and views..."
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
fi

echo "Starting Supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf

