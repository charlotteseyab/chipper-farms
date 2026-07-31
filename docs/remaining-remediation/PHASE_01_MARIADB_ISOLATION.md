# Phase 01 - MariaDB Isolation

Added:

- `.env.testing.mariadb.example`
- `database/testing/create_isolated_test_databases.sql.example`
- `scripts/testing/assert-safe-test-database.sh`
- `scripts/testing/create-test-databases.sh`
- `scripts/testing/test-mariadb-connection.sh`
- `scripts/testing/reset-test-database.sh`
- `scripts/testing/drop-test-databases.sh`
- `phpunit.mariadb.xml`

Local XAMPP MariaDB proof:

- `/opt/lampp/bin/mysql --version`: MariaDB 10.4.32 client.
- `bash scripts/testing/test-mariadb-connection.sh`: guard passed and selected `chipper_farms_test`.
- `bash scripts/testing/reset-test-database.sh`: fresh migration completed through `2028_09_01_100000_create_credit_notes_tables`.

The guard rejects non-testing environments, non-local hosts, system databases, and any database outside the allowlist.
