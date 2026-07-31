#!/usr/bin/env bash
set -euo pipefail

if [[ -f .env.testing.mariadb.local ]]; then
  set -a
  source .env.testing.mariadb.local
  set +a
fi

export APP_ENV=testing
export DB_CONNECTION="${DB_CONNECTION:-mysql}"
export DB_HOST="${DB_HOST:-127.0.0.1}"
export DB_PORT="${DB_PORT:-3306}"
export DB_DATABASE="${DB_DATABASE:-chipper_farms_test}"
export CACHE_STORE="${CACHE_STORE:-array}"
export SESSION_DRIVER="${SESSION_DRIVER:-array}"
export QUEUE_CONNECTION="${QUEUE_CONNECTION:-sync}"
export MAIL_MAILER="${MAIL_MAILER:-array}"

scripts/testing/assert-safe-test-database.sh
php -r 'if (getenv("DB_CONNECTION") !== "mysql" || getenv("DB_DATABASE") !== "chipper_farms_test") { fwrite(STDERR, "MariaDB suite refused unsafe DB env\n"); exit(64); } echo "MariaDB suite DB=".getenv("DB_DATABASE")."\n";'
vendor/bin/phpunit -c phpunit.mariadb.xml "$@"
