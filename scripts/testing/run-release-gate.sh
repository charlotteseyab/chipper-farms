#!/usr/bin/env bash
set -euo pipefail

composer validate --no-check-publish
composer install --no-interaction --prefer-dist
composer audit
npm ci
npm audit --audit-level=low
npm run build
php artisan about
php artisan route:list
php artisan event:list
bash scripts/testing/test-mariadb-connection.sh
bash scripts/testing/run-mariadb-tests.sh
bash scripts/testing/run-production-cache-tests.sh
bash scripts/testing/run-concurrency-tests.sh
php artisan test
