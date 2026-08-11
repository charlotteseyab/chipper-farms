#!/usr/bin/env bash
#
# MariaDB gate (blueprint §60: SQLite alone is not acceptable for final
# certification, because lock and concurrency behaviour is money-critical).
#
# IMP-096 — WHY THIS SCRIPT CHECKS ITS OWN OUTPUT
# -----------------------------------------------
# A backgrounded gate run once reported success while proving nothing: the
# 11.4.2 server had been reaped mid-session, so PHPUnit never produced a result,
# and the invocation was piped (`run-mariadb-tests.sh | tail -7`). A pipeline's
# exit status is the LAST command's — `tail` succeeds at printing nothing — so
# the failure was invisible from the outside no matter how carefully the caller
# checked `$?`.
#
# Two consequences, both handled below:
#
#   1. The server is checked BEFORE PHPUnit starts. A dead server now produces
#      one clear sentence instead of ~1700 identical connection errors that bury
#      the cause.
#
#   2. The run is only called a pass if PHPUnit actually printed a test-count
#      summary. "No output" and "everything passed" must never look alike.
#
# The caller may still pipe this script; that is why the checks are here rather
# than in a wrapper.
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

# ---------------------------------------------------------------------------
# Preflight: is the server actually there?
# ---------------------------------------------------------------------------
if ! php -r '
    $dsn = sprintf("mysql:host=%s;port=%s", getenv("DB_HOST"), getenv("DB_PORT"));
    try {
        new PDO($dsn, getenv("DB_USERNAME") ?: "root", getenv("DB_PASSWORD") ?: "");
    } catch (Throwable $e) {
        fwrite(STDERR, "\n  " . $e->getMessage() . "\n");
        exit(1);
    }
' 2>&1; then
  cat >&2 <<EOF

MariaDB gate ABORTED: no server answering on ${DB_HOST}:${DB_PORT}.

Nothing was tested. This is the IMP-096 failure mode — the server is reaped
between sessions and a run against a dead server proves nothing at all.

Start it with:
  sh ~/.local/share/cf-mariadb/start.sh

EOF
  exit 69
fi

# ---------------------------------------------------------------------------
# Run, then verify the run actually happened.
# ---------------------------------------------------------------------------
summary_file="$(mktemp)"
trap 'rm -f "$summary_file"' EXIT

set +e
vendor/bin/phpunit -c phpunit.mariadb.xml "$@" 2>&1 | tee "$summary_file"
phpunit_status="${PIPESTATUS[0]}"
set -e

if ! grep -qE '^(OK|Tests:|FAILURES|ERRORS)' "$summary_file" \
   && ! grep -qE 'Tests: [0-9]+' "$summary_file"; then
  cat >&2 <<EOF

MariaDB gate INCONCLUSIVE: PHPUnit produced no test-count summary.

The suite did not report how many tests it ran, so this run is not evidence of
anything — do not read it as a pass. Check the output above for a crash before
the first test.

EOF
  exit 70
fi

exit "$phpunit_status"
