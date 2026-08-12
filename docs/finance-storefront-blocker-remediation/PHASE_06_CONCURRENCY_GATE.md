# Phase 06 - Concurrency Gate

The concurrency suite remains isolated from `phpunit.xml` and is run explicitly through:

- `scripts/testing/run-concurrency-tests.sh`

The script targets the approved disposable database `chipper_farms_concurrency_test` and now supports `CF_SKIP_MARIADB_LOCAL_ENV=yes` for deliberate XAMPP certification.

Final execution evidence:

- First run exposed a real duplicate Paystack webhook idempotency defect: one charge produced three payment timeline events.
- `PaymentService::markPaystackVerified()` now locks and rereads the payment row inside the transaction before writing payment state or order events.
- Rerun command: `CF_SKIP_MARIADB_LOCAL_ENV=yes DB_PORT=3306 DB_USERNAME=chipper_test DB_PASSWORD= scripts/testing/run-concurrency-tests.sh`
- Result: OK, 15 tests, 270 assertions.
