#!/bin/bash
set -eo pipefail

echo "==> Running production startup..."

# Generate APP_KEY if not set
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "" ]; then
    echo "==> Generating APP_KEY..."
    php artisan key:generate --force --no-interaction
else
    echo "==> APP_KEY already set"
fi

# Run normal migrations (SAFE: does NOT wipe data)
echo "==> Running migrate --force..."
php artisan migrate --force --no-interaction

# Seed admin user if needed (seeds should be idempotent)
echo "==> Seeding database..."
php artisan db:seed --force --no-interaction || echo "WARNING: Seeder failed, continuing startup..."

# Cache config/routes/views for production
echo "==> Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Publish Spatie permissions if needed
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --no-interaction 2>/dev/null || true
php artisan filament:upgrade --no-interaction 2>/dev/null || true

echo "==> All ready! Starting services..."

# Execute the main command (supervisord)
exec "$@"
