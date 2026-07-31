#!/usr/bin/env bash
set -euo pipefail

if [[ -f .env.testing.mariadb.local ]]; then
  set -a
  source .env.testing.mariadb.local
  set +a
fi

export APP_ENV=testing
export DB_CONNECTION=mysql
export DB_HOST="${CF_CONCURRENCY_DB_HOST:-${DB_HOST:-127.0.0.1}}"
export DB_PORT="${CF_CONCURRENCY_DB_PORT:-${DB_PORT:-3306}}"
export DB_DATABASE="${CF_CONCURRENCY_DB_DATABASE:-chipper_farms_concurrency_test}"
export CF_CONCURRENCY_DB_HOST="$DB_HOST"
export CF_CONCURRENCY_DB_PORT="$DB_PORT"
export CF_CONCURRENCY_DB_DATABASE="$DB_DATABASE"
export CF_CONCURRENCY_DB_USERNAME="${CF_CONCURRENCY_DB_USERNAME:-${DB_USERNAME:-chipper_test}}"
export CF_CONCURRENCY_DB_PASSWORD="${CF_CONCURRENCY_DB_PASSWORD:-${DB_PASSWORD:-}}"

scripts/testing/assert-safe-test-database.sh
DB_USERNAME="$CF_CONCURRENCY_DB_USERNAME" DB_PASSWORD="$CF_CONCURRENCY_DB_PASSWORD" php artisan migrate:fresh --force
vendor/bin/phpunit -c phpunit.concurrency.xml "$@"
