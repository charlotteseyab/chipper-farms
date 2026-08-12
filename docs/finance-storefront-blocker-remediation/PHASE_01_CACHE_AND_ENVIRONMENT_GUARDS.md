# Phase 01 - Cache And Environment Guards

Updated `scripts/testing/run-production-cache-tests.sh` to fail closed:

- Loads test env intentionally.
- Enforces the safe DB allowlist before any test run.
- Builds config, route, and view caches under testing settings.
- Verifies the cached Laravel config resolves to `testing`, `mysql`, `chipper_farms_test`, `array` cache/session, and `sync` queue.
- Preflights MariaDB before PHPUnit starts.
- Requires a PHPUnit test-count summary.
- Clears caches on every exit.
- Uses a lock file to avoid overlapping production-cache gate runs.

Also added `CF_SKIP_MARIADB_LOCAL_ENV=yes` support to MariaDB helpers so local gitignored overrides can be bypassed intentionally for XAMPP `127.0.0.1:3306` certification.
