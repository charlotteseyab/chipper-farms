#!/usr/bin/env bash
#
# Chipper Farms database restore (CF-P1-022).
#
# Usage:
#   ./deploy/scripts/restore-database.sh <dump.sql.gz> <target-database>
#
# The target database is an explicit REQUIRED argument. It is not read from .env
# on purpose: a restore script that defaults to the live database is one
# mistyped command away from destroying production while trying to rehearse.
#
# Exit codes: 0 success, 1 usage/config error, 2 restore failed, 3 verification failed.

set -euo pipefail

APP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
DUMP_FILE="${1:-}"
TARGET_DB="${2:-}"
ENV_FILE="${APP_ROOT}/.env"

if [[ -z "$DUMP_FILE" || -z "$TARGET_DB" ]]; then
  echo "Usage: $0 <dump.sql.gz> <target-database>" >&2
  echo "The target database MUST be named explicitly — there is no default." >&2
  exit 1
fi

# The name is interpolated into SQL identifiers and a schema predicate.
# Reject quotes/backticks rather than allowing a target to become SQL.
if [[ ! "$TARGET_DB" =~ ^[A-Za-z0-9_]+$ ]]; then
  echo "ERROR: target database must contain only letters, digits and underscores." >&2
  exit 1
fi

if [[ ! -f "$DUMP_FILE" ]]; then
  echo "ERROR: dump file '${DUMP_FILE}' not found." >&2
  exit 1
fi

env_value() {
  local key="$1" line
  line="$(grep -E "^${key}=" "$ENV_FILE" | tail -n 1 || true)"
  line="${line#*=}"
  line="${line%\"}"; line="${line#\"}"
  line="${line%\'}"; line="${line#\'}"
  printf '%s' "$line"
}

DB_HOST="$(env_value DB_HOST)"
DB_PORT="$(env_value DB_PORT)"
DB_USERNAME="$(env_value DB_USERNAME)"
DB_PASSWORD="$(env_value DB_PASSWORD)"
LIVE_DB="$(env_value DB_DATABASE)"

# Refuse to restore over the database this checkout is configured to use unless
# the operator says so out loud. Rehearsals are the common case; overwriting the
# live database is the exception and should feel like one.
if [[ "$TARGET_DB" == "$LIVE_DB" && "${CF_ALLOW_LIVE_RESTORE:-no}" != "yes" ]]; then
  echo "REFUSING: '${TARGET_DB}' is the database configured in .env." >&2
  echo "If this is genuinely intended, re-run with CF_ALLOW_LIVE_RESTORE=yes" >&2
  exit 1
fi

DEFAULTS_FILE="$(mktemp)"
chmod 600 "$DEFAULTS_FILE"
trap 'rm -f "$DEFAULTS_FILE"' EXIT

{
  echo "[client]"
  echo "host=${DB_HOST:-127.0.0.1}"
  echo "port=${DB_PORT:-3306}"
  echo "user=${DB_USERNAME}"
  [[ -n "$DB_PASSWORD" ]] && echo "password=${DB_PASSWORD}"
} > "$DEFAULTS_FILE"

CLIENT_BIN="$(command -v mariadb || command -v mysql)"
if [[ -z "$CLIENT_BIN" ]]; then
  echo "ERROR: neither mariadb nor mysql client is available." >&2
  exit 1
fi

if ! gzip -t "$DUMP_FILE" 2>/dev/null; then
  echo "ERROR: '${DUMP_FILE}' failed a gzip integrity check — do not restore it." >&2
  exit 3
fi

START_EPOCH="$(date +%s)"
echo "Restoring ${DUMP_FILE} into ${TARGET_DB}"

"$CLIENT_BIN" --defaults-extra-file="$DEFAULTS_FILE" \
  -e "DROP DATABASE IF EXISTS \`${TARGET_DB}\`; CREATE DATABASE \`${TARGET_DB}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

if ! gunzip -c "$DUMP_FILE" | "$CLIENT_BIN" --defaults-extra-file="$DEFAULTS_FILE" "$TARGET_DB"; then
  echo "ERROR: restore failed." >&2
  exit 2
fi

DURATION=$(( $(date +%s) - START_EPOCH ))

# Verification. A restore that "worked" but produced an empty schema is the
# failure mode that matters, and it is silent unless checked.
TABLES="$("$CLIENT_BIN" --defaults-extra-file="$DEFAULTS_FILE" -N -B -e \
  "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${TARGET_DB}';")"

MIGRATIONS="$("$CLIENT_BIN" --defaults-extra-file="$DEFAULTS_FILE" -N -B -e \
  "SELECT COUNT(*) FROM \`${TARGET_DB}\`.migrations;" 2>/dev/null || echo 0)"

if [[ "$TABLES" -lt 1 ]]; then
  echo "ERROR: restored database has no tables." >&2
  exit 3
fi

echo "OK  target=${TARGET_DB} tables=${TABLES} migrations=${MIGRATIONS} duration=${DURATION}s"
echo
echo "Now verify the application against it before trusting the restore:"
echo "  php artisan migrate:status    # expect no pending migrations"
echo "  php artisan about"
