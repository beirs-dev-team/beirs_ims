#!/usr/bin/env bash
set -e

echo "Running composer install (already done in Dockerfile, but safe)..."
composer install --no-dev --optimize-autoloader

echo "Clearing Config..."
php artisan config:clear

echo "Clearing Cache..."
php artisan cache:clear

echo "Optimized Clearing..."
php artisan optimize:clear

echo "Running Dump Auto-load..."
composer dump-autoload

echo "Running migrations..."
php artisan migrate:fresh --seed

echo "Running Server"
php artisan serve --host=0.0.0.0 --port=$PORT
