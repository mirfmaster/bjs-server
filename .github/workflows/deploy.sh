#!/bin/env bash
set -euo pipefail

DEPLOY_ENV="${DEPLOY_ENV:-staging}"
echo "🚀 Deployment started for $DEPLOY_ENV …"

# Enter maintenance mode (or continue if already down)
php artisan down --render="errors::503" || true

# Always pull the exact ref that triggered the workflow
git fetch origin
git reset --hard "$GITHUB_SHA"

composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Database migrations (enable when ready)
php artisan migrate --force

php artisan optimize
php artisan queue:restart

php artisan up
echo "✅ Deployment finished for $DEPLOY_ENV!"
