FROM serversideup/php:8.4-fpm-nginx-bookworm

WORKDIR /var/www/html

ENV WEB_PORT=3000
EXPOSE 3000

USER root

# Install git/unzip for composer
RUN apt-get update && apt-get install -y git unzip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions with single-thread compilation to prevent OOM
# IPE_PROCESSOR_COUNT=1 limits make -j to 1 core (prevents memory exhaustion)
# This is a separate RUN so Docker caches it after first successful build
RUN IPE_PROCESSOR_COUNT=1 install-php-extensions intl gd

# Copy composer from official image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application files
COPY . .

# Create .env for build (excluded by .dockerignore)
RUN cp .env.example .env 2>/dev/null || echo "APP_KEY=base64:$(head -c 32 /dev/urandom | base64)" > .env
RUN php artisan key:generate --force 2>/dev/null || true

# Install dependencies
RUN COMPOSER_MEMORY_LIMIT=-1 composer update --no-interaction --optimize-autoloader --no-dev

# Fix permissions
RUN chown -R www-data:www-data /var/www/html

USER www-data

ENV PHP_MEMORY_LIMIT=512M
ENV PHP_MAX_EXECUTION_TIME=300
ENV PHP_DISPLAY_ERRORS=On
ENV LOG_STDERR=On

# Cache optimization
RUN php artisan config:clear && php artisan cache:clear
