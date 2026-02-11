FROM serversideup/php:8.3-fpm-nginx-alpine

# Set working directory to standard location for this image
WORKDIR /var/www/html

# Configure image to listen on port 3000 (matches Coolify default)
ENV WEB_PORT=3000
EXPOSE 3000

# Switch to root to install dependencies
USER root

# Install additional dependencies
# git and unzip are needed for composer
# Build dependencies for PHP extensions: icu-dev, libpng-dev, libzip-dev, postgresql-dev
RUN apk add --no-cache \
    git \
    curl \
    wget \
    unzip \
    icu-dev \
    libpng-dev \
    libzip-dev \
    postgresql-dev

# Install PHP extensions manually
# Use -j1 for intl to prevent "mkdir ... File exists" race condition during compilation
RUN docker-php-ext-configure intl \
    && docker-php-ext-install -j1 intl \
    && docker-php-ext-install gd zip pdo_pgsql

# Checking documentation: pdo_pgsql is included.
# We can skip explicit install unless we need something exotic.

# Copy composer from official image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application files
COPY . .

# Install PHP dependencies
RUN composer install --no-interaction --optimize-autoloader --no-dev

# Fix permissions for the web user (www-data is default in this image)
RUN chown -R www-data:www-data /var/www/html

# Switch to non-root user
USER www-data

# Increase PHP memory limit and execution time for heavy tasks
ENV PHP_MEMORY_LIMIT=512M
ENV PHP_MAX_EXECUTION_TIME=300
ENV PHP_DISPLAY_ERRORS=On
ENV LOG_STDERR=On

# Run optimization commands 
# Note: In production, these should ideally be run as part of the build or entrypoint script.
# serversideup image executes /etc/s6-overlay/s6-rc.d/init-laravel-automations/run 
# so we can hook into that or just let it be.
# But manually caching config helps catch errors early.
RUN php artisan config:clear && php artisan cache:clear

