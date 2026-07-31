#!/usr/bin/env bash
set -euo pipefail

fail() {
  echo "SAFE-DB-GUARD: FAIL: $*" >&2
  exit 64
}

info() {
  echo "SAFE-DB-GUARD: $*"
}

if [[ "${1:-}" == "--help" ]]; then
  cat <<'USAGE'
Usage:
  APP_ENV=testing DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=chipper_farms_test \
    scripts/testing/assert-safe-test-database.sh
USAGE
  exit 0
fi

APP_ENV_VALUE="${APP_ENV:-}"
DB_HOST_VALUE="${DB_HOST:-127.0.0.1}"
DB_PORT_VALUE="${DB_PORT:-3306}"
DB_DATABASE_VALUE="${DB_DATABASE:-}"

[[ "$APP_ENV_VALUE" == "testing" ]] || fail "APP_ENV must be testing; got '${APP_ENV_VALUE:-<empty>}'."
[[ -n "$DB_DATABASE_VALUE" ]] || fail "DB_DATABASE is empty."

case "$DB_HOST_VALUE" in
  127.0.0.1|localhost|::1) ;;
  *) fail "DB_HOST must be an explicitly local test host; got '$DB_HOST_VALUE'." ;;
esac

if [[ "$DB_PORT_VALUE" != "3306" && "${CF_ALLOW_NONSTANDARD_TEST_DB_PORT:-}" != "yes" ]]; then
  fail "DB_PORT must be 3306 unless CF_ALLOW_NONSTANDARD_TEST_DB_PORT=yes; got '$DB_PORT_VALUE'."
fi

case "$DB_DATABASE_VALUE" in
  chipper_farms_test|chipper_farms_concurrency_test|chipper_farms_upgrade_test|chipper_farms_restore_test) ;;
  mysql|information_schema|performance_schema|sys) fail "System database '$DB_DATABASE_VALUE' is never allowed." ;;
  *) fail "Database '$DB_DATABASE_VALUE' is not in the approved Chipper Farms test allowlist." ;;
esac

[[ "$DB_DATABASE_VALUE" =~ ^chipper_farms_(test|concurrency_test|upgrade_test|restore_test)$ ]] \
  || fail "Database '$DB_DATABASE_VALUE' does not match the approved test database pattern."

[[ -f .env ]] || fail "Normal .env is missing; cannot compare selected database against the application database safely."

NORMAL_ENV_DB="$(awk -F= '
  /^[[:space:]]*DB_DATABASE[[:space:]]*=/ {
    value=$0
    sub(/^[^=]*=/, "", value)
    gsub(/^[[:space:]'\''"]+|[[:space:]'\''"]+$/, "", value)
    print value
    exit
  }
' .env)"

[[ -n "$NORMAL_ENV_DB" ]] || fail "Could not determine DB_DATABASE from normal .env safely."
[[ "$NORMAL_ENV_DB" != "$DB_DATABASE_VALUE" ]] \
  || fail "Selected test database matches the normal .env database ('$DB_DATABASE_VALUE')."

info "PASS"
info "Host: $DB_HOST_VALUE"
info "Port: $DB_PORT_VALUE"
info "Database: $DB_DATABASE_VALUE"
