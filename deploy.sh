#!/usr/bin/env bash
set -euo pipefail

APP_DIR="/var/www/attendance-web"

cd "$APP_DIR"

echo "== whoami =="
whoami
echo "== pwd =="
pwd

echo "== node/npm =="
node -v
npm -v

echo "== composer/php =="
php -v
composer -V

# Node build
echo "== npm ci =="
npm ci

echo "== npm run build =="
npm run build

# PHP deps (本番想定)
echo "== composer install =="
composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader

# Laravel optimize
echo "== artisan optimize =="
php artisan optimize

echo "== DONE =="
