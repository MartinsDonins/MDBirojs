FROM serversideup/php:8.3-fpm-nginx-bookworm

# Set working directory to standard location for this image
WORKDIR /var/www/html

# Configure image to listen on port 3000 (matches Coolify default)
ENV WEB_PORT=3000
EXPOSE 3000

# Switch to root to install dependencies
USER root

# Install additional dependencies
# git and unzip are needed for composer
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions using the built-in helper
# We specify 'intl' here - on Debian Bookworm (stable), this should fetch pre-built binaries
# avoiding the compilation race conditions and memory issues seen on Trixie/Alpine.
RUN install-php-extensions intl gd zip pdo_pgsql


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

