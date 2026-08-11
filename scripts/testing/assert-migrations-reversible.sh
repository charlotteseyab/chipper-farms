#!/usr/bin/env bash
#
# Prove the schema can be rolled back and re-applied (IMP-134).
#
# WHY THIS IS A SCRIPT AND NOT A TEST
# -----------------------------------
# The only honest check is the real thing: migrate up, roll all the way back to
# zero, and migrate up again, against MariaDB. That takes minutes and needs a
# database it is allowed to destroy, so it does not belong in the per-commit
# suite. It belongs in the release checklist, next to the MariaDB gate.
#
# WHAT IT CATCHES THAT UNIT TESTS CANNOT
# --------------------------------------
# `down()` methods are almost never exercised. This application's were run only
# on SQLite, which rebuilds a table on a column drop and therefore tolerates
# dropping a column that still carries a foreign key or an index. MariaDB
# refuses. Two migrations were broken that way and nobody could have known,
# because nothing ever rolled the schema back on the engine production uses:
#
#   - `extend_activity_logs_for_audit_trail` dropped `organization_id` while its
#     foreign key and index still existed;
#   - `add_decision_fields_to_feed_purchase_requests` (which, despite the name,
#     adds `users.last_seen`) dropped a column a LATER migration had already
#     dropped, aborting the whole rollback.
#
# The consequence was not cosmetic: a bad deploy had no way back.
#
# USAGE
#   scripts/testing/assert-migrations-reversible.sh
#
# Override the target with MIGRATION_TEST_DB. It must be a disposable database —
# this script DROPS it.
set -uo pipefail

cd "$(dirname "$0")/../.." || exit 1

DB_NAME="${MIGRATION_TEST_DB:-chipper_farms_migration_test}"
DB_HOST_V="${MIGRATION_TEST_DB_HOST:-127.0.0.1}"
DB_PORT_V="${MIGRATION_TEST_DB_PORT:-3306}"
DB_USER_V="${MIGRATION_TEST_DB_USERNAME:-root}"

case "$DB_NAME" in
  *test*|*dusk*) ;;
  *)
    echo "REVERSIBILITY: refusing to run against '${DB_NAME}' — this script DROPS the database." >&2
    echo "               Its name must contain 'test' or 'dusk'." >&2
    exit 64
    ;;
esac

echo "REVERSIBILITY: using ${DB_NAME} on ${DB_HOST_V}:${DB_PORT_V}"

mysql --protocol=TCP -h"$DB_HOST_V" -P"$DB_PORT_V" -u"$DB_USER_V" \
  -e "DROP DATABASE IF EXISTS \`${DB_NAME}\`; CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null \
  || { echo "REVERSIBILITY: could not reach the database server." >&2; exit 69; }

export DB_CONNECTION=mysql
export DB_HOST="$DB_HOST_V"
export DB_PORT="$DB_PORT_V"
export DB_USERNAME="$DB_USER_V"
export DB_DATABASE="$DB_NAME"

run_stage() {
  local label="$1"; shift
  local out
  out="$("$@" 2>&1)"
  local ran fails
  ran="$(grep -c 'DONE' <<<"$out")"
  fails="$(grep -c 'FAIL' <<<"$out")"

  printf '  %-22s ran=%-4s failures=%s\n' "$label" "$ran" "$fails"

  if [[ "$fails" != "0" ]]; then
    echo
    echo "REVERSIBILITY: FAILED during ${label}." >&2
    grep -m1 'FAIL' <<<"$out" >&2
    grep -m1 'SQLSTATE' <<<"$out" >&2
    echo >&2
    echo "A migration whose down() cannot run means this schema has no rollback path." >&2
    return 1
  fi

  if [[ "$ran" == "0" ]]; then
    echo "REVERSIBILITY: ${label} did nothing at all — treating that as a failure rather than a pass." >&2
    return 1
  fi

  return 0
}

echo
run_stage "1. migrate up"      php artisan migrate --force        || exit 1
run_stage "2. reset to zero"   php artisan migrate:reset --force  || exit 1
run_stage "3. re-apply"        php artisan migrate --force        || exit 1

echo
echo "REVERSIBILITY: PASS — the schema rolls back to zero and comes back up."
