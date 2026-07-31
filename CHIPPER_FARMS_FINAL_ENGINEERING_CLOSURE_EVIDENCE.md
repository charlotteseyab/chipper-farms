# Chipper Farms Final Engineering Closure Evidence

Date: 2026-07-31

## Local Commands

| Command | Result |
| --- | --- |
| `composer validate --no-check-publish` | passed; composer cache directory warning only |
| `composer audit` | passed on retry; no advisories |
| `npm audit --audit-level=low` | passed; 0 vulnerabilities |
| `npm run build` | passed; stale Browserslist data warning |
| `php artisan test` | 1309 passed, 13 skipped, 3739 assertions |
| `bash scripts/testing/test-mariadb-connection.sh` | passed against XAMPP MariaDB 10.4.32 / `chipper_farms_test` |
| `bash scripts/testing/reset-test-database.sh` | fresh MariaDB migration passed through credit-note migration |
| `bash scripts/testing/run-mariadb-tests.sh tests/Feature/Finance/Schema/JournalBalanceTest.php` | 6 passed, 10 assertions |
| `bash scripts/testing/run-mariadb-tests.sh tests/Feature/Finance/SalesReturns/SalesReturnRevenueTest.php tests/Feature/Storefront/OrderHttpTest.php` | 17 passed, 62 assertions |
| `bash scripts/testing/run-concurrency-tests.sh` | 9 passed, 110 assertions |
| `php artisan route:list --except-vendor` | passed; 477 routes |
| `php artisan event:list` | passed |
| `php artisan schedule:list` | passed; expected finance/storefront/notification schedules shown |
| `php artisan config:cache` | passed |
| `php artisan route:cache` | passed |
| `php artisan view:cache` | passed |
| `php artisan optimize:clear` | passed after cache compile |

## Skips

Default suite skips remain 13:

- API token features disabled;
- email verification disabled;
- registration support disabled;
- six SQLite finance check-constraint tests, covered by the MariaDB run above.

## Evidence Limits

The workspace could not independently execute external Paystack sandbox callbacks, remote GitHub Actions, off-host backups, production deployment, or rollback. Those are delegated to `CHIPPER_FARMS_EXTERNAL_CERTIFICATION_RUNBOOK.md`.
