#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."

# The DirectAdmin account defaults to PHP 8.2; this app needs 8.4 (composer.lock resolved
# Symfony packages requiring >=8.4.1). Invoke it explicitly rather than relying on `php`.
PHP=/usr/local/php84/bin/php

# No `artisan down`/`up`: maintenance mode took the whole site offline for every deploy
# (~10s), visitors included. Without it a deploy only risks errors in the few seconds
# composer install changes packages, and most deploys change none.
git pull origin main
$PHP /usr/local/bin/composer install --no-dev --optimize-autoloader --no-interaction

$PHP artisan migrate --force
$PHP artisan db:seed --force
$PHP artisan storage:link || true
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache
$PHP artisan queue:restart
