FROM serversideup/php:8.4-fpm-nginx-alpine

WORKDIR /var/www/html

ENV WEB_PORT=3000
EXPOSE 3000

USER root

# Install additional dependencies via apk
# git and unzip are needed for composer
RUN apk add --no-cache \
    git \
    unzip

# Install PHP extensions using the built-in helper
# install-php-extensions handles dependencies and binaries automatically
# We use this instead of apk to ensure extensions are linked to the correct PHP binary
RUN install-php-extensions intl gd zip pdo_pgsql

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
# Note: Manually caching config helps catch errors early.
RUN php artisan config:clear && php artisan cache:clear

