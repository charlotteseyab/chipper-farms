#!/usr/bin/env bash
#
# Chipper Farms database backup (CF-P1-022).
#
# Reads connection details from .env so the backup cannot drift from what the
# application actually uses — a backup of the wrong database is the classic way
# to discover you have no backup at all, during the restore.
#
# Usage:
#   ./deploy/scripts/backup-database.sh [output-directory]
#
# Exit codes:
#   0 success, 1 configuration error, 2 dump failed, 3 verification failed.

set -euo pipefail

APP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
OUTPUT_DIR="${1:-${CF_BACKUP_DIR:-/var/backups/chipper-farms}}"
ENV_FILE="${APP_ROOT}/.env"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "ERROR: ${ENV_FILE} not found. Run from a deployed checkout." >&2
  exit 1
fi

# Read a key from .env without sourcing it — .env may contain values with
# spaces, '#' or shell metacharacters that `source` would mangle or execute.
env_value() {
  local key="$1"
  local line
  line="$(grep -E "^${key}=" "$ENV_FILE" | tail -n 1 || true)"
  line="${line#*=}"
  line="${line%\"}"; line="${line#\"}"
  line="${line%\'}"; line="${line#\'}"
  printf '%s' "$line"
}

DB_CONNECTION="$(env_value DB_CONNECTION)"
DB_HOST="$(env_value DB_HOST)"
DB_PORT="$(env_value DB_PORT)"
DB_DATABASE="$(env_value DB_DATABASE)"
DB_USERNAME="$(env_value DB_USERNAME)"
DB_PASSWORD="$(env_value DB_PASSWORD)"

if [[ "$DB_CONNECTION" != "mysql" && "$DB_CONNECTION" != "mariadb" ]]; then
  echo "ERROR: this script backs up MySQL/MariaDB; DB_CONNECTION is '${DB_CONNECTION}'." >&2
  exit 1
fi

if [[ -z "$DB_DATABASE" ]]; then
  echo "ERROR: DB_DATABASE is empty in .env." >&2
  exit 1
fi

mkdir -p "$OUTPUT_DIR"
chmod 700 "$OUTPUT_DIR"   # dumps contain customer data and password hashes

TIMESTAMP="$(date -u +%Y%m%dT%H%M%SZ)"
DUMP_FILE="${OUTPUT_DIR}/chipper-farms-${DB_DATABASE}-${TIMESTAMP}.sql.gz"

# The password is passed via a temporary defaults file rather than --password=,
# which would expose it in `ps` output to every user on the host.
DEFAULTS_FILE="$(mktemp)"
chmod 600 "$DEFAULTS_FILE"
cleanup() { rm -f "$DEFAULTS_FILE"; }
trap cleanup EXIT

{
  echo "[client]"
  echo "host=${DB_HOST:-127.0.0.1}"
  echo "port=${DB_PORT:-3306}"
  echo "user=${DB_USERNAME}"
  [[ -n "$DB_PASSWORD" ]] && echo "password=${DB_PASSWORD}"
} > "$DEFAULTS_FILE"

DUMP_BIN="$(command -v mariadb-dump || command -v mysqldump)"
if [[ -z "$DUMP_BIN" ]]; then
  echo "ERROR: neither mariadb-dump nor mysqldump is available." >&2
  exit 1
fi

echo "Backing up '${DB_DATABASE}' to ${DUMP_FILE}"

# --single-transaction  consistent snapshot without locking writers (InnoDB).
# --routines --triggers --events  a schema-only dump silently loses these.
# --hex-blob  binary data survives a round trip.
# NOT --lock-tables: it would block the application for the whole dump.
if ! "$DUMP_BIN" \
      --defaults-extra-file="$DEFAULTS_FILE" \
      --single-transaction \
      --routines --triggers --events \
      --hex-blob \
      --default-character-set=utf8mb4 \
      "$DB_DATABASE" 2>"${DUMP_FILE}.err" | gzip -9 > "$DUMP_FILE"; then
  echo "ERROR: dump failed. See ${DUMP_FILE}.err" >&2
  exit 2
fi

# A dump that "succeeded" but is empty or truncated is worse than none, because
# it satisfies a monitoring check while being unrestorable.
if [[ ! -s "$DUMP_FILE" ]]; then
  echo "ERROR: dump file is empty." >&2
  exit 3
fi

if ! gzip -t "$DUMP_FILE" 2>/dev/null; then
  echo "ERROR: dump file failed gzip integrity check." >&2
  exit 3
fi

if ! gunzip -c "$DUMP_FILE" | tail -5 | grep -q "Dump completed"; then
  echo "ERROR: dump does not end with a completion marker — it is truncated." >&2
  exit 3
fi

TABLE_COUNT="$(gunzip -c "$DUMP_FILE" | grep -c '^CREATE TABLE' || true)"
SIZE="$(du -h "$DUMP_FILE" | cut -f1)"

chmod 600 "$DUMP_FILE"
rm -f "${DUMP_FILE}.err"

echo "OK  file=${DUMP_FILE} size=${SIZE} tables=${TABLE_COUNT}"
echo "Record this filename in the deployment ticket."

# Retention: keep 30 days locally. Offsite copies are handled separately — a
# backup on the same host as the database is not a backup.
find "$OUTPUT_DIR" -name 'chipper-farms-*.sql.gz' -mtime +30 -delete 2>/dev/null || true
