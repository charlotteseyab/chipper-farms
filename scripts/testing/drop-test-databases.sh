#!/usr/bin/env bash
set -euo pipefail

if [[ "${CF_CONFIRM_DROP_TEST_DATABASES:-}" != "yes" ]]; then
  echo "Refusing to drop test databases without CF_CONFIRM_DROP_TEST_DATABASES=yes." >&2
  exit 64
fi

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
  --host="${DB_HOST:-127.0.0.1}"
  --port="${DB_PORT:-3306}"
  --user="${DB_ADMIN_USERNAME:-root}"
  --execute="DROP DATABASE IF EXISTS chipper_farms_test; DROP DATABASE IF EXISTS chipper_farms_concurrency_test; DROP DATABASE IF EXISTS chipper_farms_upgrade_test; DROP DATABASE IF EXISTS chipper_farms_restore_test;"
)
if [[ -n "${DB_ADMIN_PASSWORD:-}" ]]; then
  ARGS+=(--password="${DB_ADMIN_PASSWORD}")
fi

"$MYSQL_CLIENT" "${ARGS[@]}"
echo "Dropped only the four approved Chipper Farms test databases."
