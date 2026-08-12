# Phase 04 - Production Cache Gate

The first production-cache MariaDB attempt was invalidated by an overlapping focused Laravel test run using the same `chipper_farms_test` database. The failure signature was mid-suite missing tables and role insert deadlocks after another process ran `migrate:fresh`.

Remediation:

- Added an exclusive lock to `run-production-cache-tests.sh`.
- Reran the gate in isolation.

Final isolated result:

- `CF_SKIP_MARIADB_LOCAL_ENV=yes DB_PORT=3306 DB_USERNAME=chipper_test DB_PASSWORD= scripts/testing/run-production-cache-tests.sh`
- PHPUnit: 1789 tests, 5461 assertions, 7 skipped.
- Duration: 12:02.447.
- Result: passed.

Current cached storefront/payment slice after final Paystack idempotency and browser remediation edits:

- `CF_SKIP_MARIADB_LOCAL_ENV=yes DB_PORT=3306 DB_USERNAME=chipper_test DB_PASSWORD= scripts/testing/run-production-cache-tests.sh tests/Feature/Storefront/DuplicateWebhookTest.php tests/Feature/Storefront/OrderLifecycleTest.php tests/Feature/Storefront/OrderHttpTest.php tests/Feature/Storefront/RefundExecutionTest.php tests/Feature/Storefront/FinanceIntegrationTest.php`
- PHPUnit: 37 tests, 117 assertions.
- Duration: 01:16.570.
- Result: passed.

During that slice, the runner initially rejected valid `OK (...)` PHPUnit output because ANSI color bytes preceded the summary token. The runner now strips ANSI codes before validating that PHPUnit produced an evidence-grade summary.
