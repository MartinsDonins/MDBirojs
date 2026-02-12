###############################################
# Stage 1: Build frontend assets
###############################################
FROM node:22-alpine AS assets

WORKDIR /app
COPY package.json ./
RUN npm install
COPY vite.config.js ./
COPY resources/ resources/
COPY public/ public/
RUN npm run build

###############################################
# Stage 2: Install PHP dependencies
###############################################
FROM composer:latest AS composer

WORKDIR /app
COPY composer.json ./
RUN composer config platform.php 8.3.30 \
    && composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs \
    && rm -f vendor/composer/platform_check.php

COPY . .
RUN composer dump-autoload --optimize

###############################################
# Stage 3: Production image (PHP-FPM + Nginx)
###############################################
FROM php:8.3-fpm-alpine

# Install system dependencies and PHP extensions using install-php-extensions (faster)
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    bash \
    && install-php-extensions \
    intl \
    gd \
    zip \
    pdo_mysql \
    pdo_pgsql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    opcache

# PHP production configuration
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini "$PHP_INI_DIR/conf.d/99-custom.ini"

# Nginx configuration
COPY docker/nginx.conf /etc/nginx/nginx.conf
RUN mkdir -p /run/nginx

# Supervisor configuration
RUN mkdir -p /var/log/supervisor
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Set working directory
WORKDIR /var/www/html

# Copy application
COPY --from=composer /app/vendor vendor
COPY . .
COPY --from=assets /app/public/build public/build

# Create required directories and set permissions
RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache public \
    && chmod -R 775 storage bootstrap/cache public

# Copy production entrypoint
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh \
    && chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80 3000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
