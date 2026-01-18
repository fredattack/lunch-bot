#!/bin/bash
# deploy.sh - Production deployment script for DigitalOcean Droplet
set -e

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
npm ci
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

# Restart queue workers
echo "Restarting queue workers..."
sudo supervisorctl restart lunch-bot-worker:*

# Exit maintenance mode
echo "Disabling maintenance mode..."
php artisan up

echo "Deployment completed successfully!"
