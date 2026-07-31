# Phase 06 - CI

Updated `.github/workflows/ci.yml` so CI creates isolated MariaDB databases and uses the same guarded scripts as local certification:

- `scripts/testing/reset-test-database.sh`
- `scripts/testing/run-mariadb-tests.sh`
- `scripts/testing/run-concurrency-tests.sh`

The CI gate now targets `chipper_farms_test` for MariaDB regression tests and `chipper_farms_concurrency_test` for concurrency tests.

Remote GitHub Actions execution was not available from this workspace and remains an external certification item.
