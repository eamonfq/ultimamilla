#!/bin/bash
set -e

cd /var/www/ultimamilla

echo "-> Pull"
git pull origin main

echo "-> Composer"
composer install --no-dev --optimize-autoloader --no-interaction

echo "-> NPM"
npm ci
npm run build

echo "-> Migrations"
php artisan migrate --force

echo "-> Cache"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "-> Restart Horizon"
sudo supervisorctl restart ultimamilla-horizon

echo "-> Reload PHP-FPM (clear opcache)"
sudo systemctl reload php8.3-fpm

echo "-> Health check"
php artisan ultimamilla:health

echo "Deploy completo"
