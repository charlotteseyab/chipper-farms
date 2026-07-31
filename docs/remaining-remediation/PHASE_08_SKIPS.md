# Phase 08 - Skips

Default `php artisan test` still reports 13 skips:

- API token feature disabled;
- email verification disabled;
- registration support disabled;
- six SQLite-only skips in `Finance\Schema\JournalBalanceTest`.

The SQLite skips were covered by MariaDB:

`bash scripts/testing/run-mariadb-tests.sh tests/Feature/Finance/Schema/JournalBalanceTest.php`

Result: 6 tests / 10 assertions passed.

The feature-flag skips are expected unless those features are enabled for the product release.
