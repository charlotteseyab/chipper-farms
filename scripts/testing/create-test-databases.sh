#!/usr/bin/env bash
set -euo pipefail

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

SQL_FILE="${1:-database/testing/create_isolated_test_databases.sql}"
if [[ ! -f "$SQL_FILE" ]]; then
  echo "Create a local SQL file first, for example:" >&2
  echo "  cp database/testing/create_isolated_test_databases.sql.example $SQL_FILE" >&2
  echo "  edit $SQL_FILE and replace __LOCAL_TEST_PASSWORD__" >&2
  exit 66
fi

ARGS=(--host="${DB_HOST:-127.0.0.1}" --port="${DB_PORT:-3306}" --user="${DB_ADMIN_USERNAME:-root}")
if [[ -n "${DB_ADMIN_PASSWORD:-}" ]]; then
  ARGS+=(--password="${DB_ADMIN_PASSWORD}")
fi

"$MYSQL_CLIENT" "${ARGS[@]}" < "$SQL_FILE"
echo "Created/verified isolated Chipper Farms test databases."
