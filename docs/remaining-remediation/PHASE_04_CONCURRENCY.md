# Phase 04 - Concurrency

The concurrency suite now defaults to:

- host: `127.0.0.1`
- port: `3306`
- database: `chipper_farms_concurrency_test`
- user: `chipper_test` locally, overrideable in CI

Added hard guards in `tests/Concurrency/ConcurrencyTestCase.php` and `scripts/testing/run-concurrency-tests.sh`.

Evidence:

`bash scripts/testing/run-concurrency-tests.sh`

Result:

- guard passed against `chipper_farms_concurrency_test`;
- fresh migration completed through the credit-note migration;
- PHPUnit: 9 tests / 110 assertions passed.
