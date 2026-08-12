#!/usr/bin/env bash
set -euo pipefail

if [[ "${CF_SKIP_MARIADB_LOCAL_ENV:-}" != "yes" && -f .env.testing.mariadb.local ]]; then
  set -a
  source .env.testing.mariadb.local
  set +a
fi

export APP_ENV=testing
export DB_CONNECTION="${DB_CONNECTION:-mysql}"
export DB_HOST="${DB_HOST:-127.0.0.1}"
export DB_PORT="${DB_PORT:-3306}"
export DB_DATABASE="${DB_DATABASE:-chipper_farms_test}"
export DB_USERNAME="${DB_USERNAME:-chipper_test}"
export DB_PASSWORD="${DB_PASSWORD:-}"
export CACHE_STORE="${CACHE_STORE:-array}"
export SESSION_DRIVER="${SESSION_DRIVER:-array}"
export QUEUE_CONNECTION="${QUEUE_CONNECTION:-sync}"
export MAIL_MAILER="${MAIL_MAILER:-array}"
export BCRYPT_ROUNDS="${BCRYPT_ROUNDS:-4}"
export PULSE_ENABLED="${PULSE_ENABLED:-false}"
export TELESCOPE_ENABLED="${TELESCOPE_ENABLED:-false}"

mkdir -p storage/framework
exec 9>storage/framework/testing-production-cache.lock
if ! flock -n 9; then
  echo "Production-cache suite refused to start: another production-cache run is active." >&2
  exit 75
fi

cleanup() {
  php artisan optimize:clear >/dev/null 2>&1 || true
}
trap cleanup EXIT

scripts/testing/assert-safe-test-database.sh
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

php -r '
require getcwd() . "/vendor/autoload.php";
$app = require getcwd() . "/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$env = config("app.env");
$connection = config("database.default");
$database = config("database.connections.mysql.database");
$cache = config("cache.default");
$session = config("session.driver");
$queue = config("queue.default");

if ($env !== "testing"
    || $connection !== "mysql"
    || $database !== "chipper_farms_test"
    || $cache !== "array"
    || $session !== "array"
    || $queue !== "sync") {
    fwrite(STDERR, "Production-cache suite refused cached config: "
        . json_encode(compact("env", "connection", "database", "cache", "session", "queue"))
        . PHP_EOL);
    exit(64);
}

echo "Production-cache suite cached DB={$database}; cache={$cache}; session={$session}; queue={$queue}" . PHP_EOL;
'

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

Production-cache suite ABORTED: no MariaDB server answering on ${DB_HOST}:${DB_PORT}.

Nothing was tested. Start the local MariaDB test server and rerun this script.

EOF
  exit 69
fi

summary_file="$(mktemp)"
trap 'status=$?; rm -f "$summary_file"; cleanup; exit "$status"' EXIT

set +e
vendor/bin/phpunit -c phpunit.mariadb.xml "$@" 2>&1 | tee "$summary_file"
phpunit_status="${PIPESTATUS[0]}"
set -e

if ! perl -pe 's/\e\[[0-9;]*[mK]//g' "$summary_file" \
    | grep -qE '^(OK|Tests:|FAILURES|ERRORS)|Tests: [0-9]+'; then
  cat >&2 <<EOF

Production-cache suite INCONCLUSIVE: PHPUnit produced no test-count summary.

The suite did not report how many tests it ran, so this run is not evidence.

EOF
  exit 70
fi

exit "$phpunit_status"
