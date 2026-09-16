#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."

# The DirectAdmin account defaults to PHP 8.2; this app needs 8.4 (composer.lock resolved
# Symfony packages requiring >=8.4.1). Invoke it explicitly rather than relying on `php`.
PHP=/usr/local/php84/bin/php

$PHP artisan down || true

git pull origin main
$PHP /usr/local/bin/composer install --no-dev --optimize-autoloader --no-interaction

$PHP artisan migrate --force
$PHP artisan db:seed --force
$PHP artisan storage:link || true
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache

$PHP artisan up
