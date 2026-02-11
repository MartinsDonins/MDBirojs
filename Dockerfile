FROM serversideup/php:8.4-fpm-nginx-bookworm

WORKDIR /var/www/html

ENV WEB_PORT=3000
EXPOSE 3000

USER root

# Install system dependencies and PHP extension build dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libicu-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Build extensions one at a time with single-thread to avoid OOM
# intl extension
RUN docker-php-ext-install -j1 intl

# gd extension
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j1 gd

# zip and pdo_pgsql are already pre-installed in this image
RUN install-php-extensions zip pdo_pgsql

# Copy composer from official image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application files
COPY . .

# Create minimal .env for build if not present (excluded by .dockerignore)
RUN if [ ! -f .env ]; then \
    cp .env.example .env 2>/dev/null || echo "APP_KEY=base64:$(head -c 32 /dev/urandom | base64)" > .env; \
    fi

# Generate APP_KEY if not set
RUN php artisan key:generate --force 2>/dev/null || true

# Update composer lock file for PHP 8.4 compatibility, then install
RUN COMPOSER_MEMORY_LIMIT=-1 composer update --no-interaction --optimize-autoloader --no-dev

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
RUN php artisan config:clear && php artisan cache:clear
