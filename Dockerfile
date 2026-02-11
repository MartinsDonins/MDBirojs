FROM serversideup/php:8.4-fpm-nginx

# Set working directory to standard location for this image
WORKDIR /var/www/html

# Switch to root to install dependencies
USER root

# Install additional dependencies if needed (most are included)
# We add generic ones just in case, but serversideup has curl/wget/zip/pgsql
RUN apt-get update && apt-get install -y \
    git \
    curl \
    wget \
    unzip \
    libpq-dev \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install extensions if missing from base (pdo_pgsql is usually included but we verify)
# serversideup images have docker-php-ext-install available
# Checking documentation: pdo_pgsql is included.
# We can skip explicit install unless we need something exotic.

# Copy composer from official image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application files
COPY . .

# Fix permissions for the web user (www-data is default in this image)
RUN chown -R www-data:www-data /var/www/html

# Switch to non-root user
USER www-data

