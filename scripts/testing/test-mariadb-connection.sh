#!/usr/bin/env bash
set -euo pipefail

if [[ -f .env.testing.mariadb.local ]]; then
  set -a
  source .env.testing.mariadb.local
  set +a
fi

export APP_ENV="${APP_ENV:-testing}"
export DB_CONNECTION="${DB_CONNECTION:-mysql}"
export DB_HOST="${DB_HOST:-127.0.0.1}"
export DB_PORT="${DB_PORT:-3306}"
export DB_DATABASE="${DB_DATABASE:-chipper_farms_test}"
export DB_USERNAME="${DB_USERNAME:-chipper_test}"
export DB_PASSWORD="${DB_PASSWORD:-}"

scripts/testing/assert-safe-test-database.sh

MYSQL_CLIENT="${MYSQL_CLIENT:-}"
if [[ -z "$MYSQL_CLIENT" ]]; then
  if [[ -x /opt/lampp/bin/mysql ]]; then
    MYSQL_CLIENT=/opt/lampp/bin/mysql
  elif command -v mysql >/dev/null 2>&1; then
    MYSQL_CLIENT="$(command -v mysql)"
  elif command -v mariadb >/dev/null 2>&1; then
    MYSQL_CLIENT="$(command -v mariadb)"
  else
    echo "No mysql/mariadb client found." >&2
    exit 69
  fi
fi

ARGS=(
  --host="$DB_HOST"
  --port="$DB_PORT"
  --user="$DB_USERNAME"
  --database="$DB_DATABASE"
  --execute="SELECT VERSION() AS mariadb_version, DATABASE() AS selected_database;"
)

if [[ -n "$DB_PASSWORD" ]]; then
  ARGS+=(--password="$DB_PASSWORD")
fi

"$MYSQL_CLIENT" "${ARGS[@]}"
