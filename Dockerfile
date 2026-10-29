# Stage 1: Build frontend assets
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

# Stage 2: Install Composer dependencies
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# Stage 3: Production runtime
FROM php:8.4-fpm-alpine AS production

# Install system dependencies & PHP extensions
RUN apk add --no-cache \
    nginx \
    supervisor \
    postgresql-dev \
    libzip-dev \
    icu-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        bcmath \
        opcache \
        zip \
        intl \
        pcntl \
        gd

# Copy configurations
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/99-custom-php.ini
COPY docker/opcache.ini /usr/local/etc/php/conf.d/99-custom-opcache.ini
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Install Composer CLI into runtime for autoload dump and artisan tasks
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy application source code
COPY . .

# Copy built frontend assets and composer vendor dependencies
COPY --from=frontend /app/public/build /var/www/html/public/build
COPY --from=vendor /app/vendor /var/www/html/vendor

# Optimize Composer autoloader
RUN composer dump-autoload --optimize --no-dev --no-interaction

# Set directory permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache \
    && mkdir -p /var/log/supervisor /var/run \
    && chown -R root:root /var/log/supervisor /var/run

EXPOSE 10000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

