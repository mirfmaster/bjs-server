#!/bin/bash
set -e

echo "Deployment started ..."

# Enter maintenance mode or return true
# if already is in maintenance mode
(php artisan down) || true

# git pull origin dev/raspi-with-api-l9
git fetch && git pull

composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Run database migrations
# NOTE: currently disabled
php artisan migrate --force

# Recreate cache
php artisan optimize
php artisan queue:restart

# Exit maintenance mode
php artisan up

echo "Deployment finished!"
