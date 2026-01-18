#!/bin/bash
# deploy.sh - Production deployment script for DigitalOcean Droplet
set -e

export COMPOSER_ALLOW_SUPERUSER=1

cd /var/www/lunch-bot

echo "Starting deployment..."

# Maintenance mode
echo "Enabling maintenance mode..."
php artisan down --retry=60

# Pull latest code
echo "Pulling latest code from main..."
git pull origin main

# Install PHP dependencies (production)
echo "Installing Composer dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# Install & build frontend
echo "Building frontend assets..."
npm install --omit=dev
npm run build

# Run migrations
echo "Running database migrations..."
php artisan migrate --force

# Clear & optimize caches
echo "Optimizing caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Fix permissions
chown -R www-data:www-data /var/www/lunch-bot
chmod -R 775 storage bootstrap/cache

# Restart queue workers
echo "Restarting queue workers..."
supervisorctl restart lunch-bot-worker:*

# Exit maintenance mode
echo "Disabling maintenance mode..."
php artisan up

echo "Deployment completed successfully!"
