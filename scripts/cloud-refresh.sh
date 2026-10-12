#!/bin/bash
set -euo pipefail
cd /workspace/jmaccr
# The prepared cloud snapshot retains /workspace/toolchain (PHP/MariaDB/Composer).
source /workspace/toolchain/activate
bash scripts/cloud-services.sh
php /workspace/toolchain/composer.phar install --no-interaction --prefer-dist
npm ci --cache=/workspace/toolchain/npm-cache
npm run build
php artisan migrate --force
php artisan db:seed --force
php artisan jmac:diagnostics
