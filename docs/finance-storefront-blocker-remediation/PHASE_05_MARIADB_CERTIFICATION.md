# Phase 05 - MariaDB Certification

XAMPP MariaDB is available at `127.0.0.1:3306`.

Guarded connection evidence:

- `CF_SKIP_MARIADB_LOCAL_ENV=yes DB_PORT=3306 DB_USERNAME=chipper_test DB_PASSWORD= scripts/testing/test-mariadb-connection.sh`
- Result: guard passed and selected `chipper_farms_test` on MariaDB `10.4.32`.

The repository also contains a gitignored `.env.testing.mariadb.local` pointing at a supported MariaDB 11.4.2 instance on port 3399, but that server was not running during this remediation session.

Full non-cached MariaDB gate:

- `CF_SKIP_MARIADB_LOCAL_ENV=yes DB_PORT=3306 DB_USERNAME=chipper_test DB_PASSWORD= scripts/testing/run-mariadb-tests.sh`
- PHPUnit: 1789 tests, 5461 assertions, 7 skipped.
- Duration: 11:56.054.
- Result: passed.
