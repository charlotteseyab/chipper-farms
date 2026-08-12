# Phase 07 - Browser Dusk Gate

Updated `tests/DuskTestCase.php` so browser tests only allow the approved disposable database `chipper_farms_upgrade_test`.

Historical documentation that referenced `chipper_farms_dusk` was updated to the approved database name.

Browser execution completed successfully after the `/all-stocks` mobile overflow remediation described below.

Final execution:

- Database: `chipper_farms_upgrade_test` on XAMPP MariaDB `127.0.0.1:3306`.
- Server: `APP_URL=http://127.0.0.1:8899`.
- Session driver: `file`, so Dusk retained authenticated browser state.
- Command: `php artisan dusk --filter=MobileViewportTest` with guarded testing/MariaDB environment variables.
- Result: `PASS Tests\Browser\MobileViewportTest`, 1 test, 1 assertion, 13.82s.

The first valid browser run found horizontal overflow on `/all-stocks`. The issue was remediated by constraining the fixed footer, hard-hiding the native searchable-select element even when page-specific select classes are present, and preserving table wrapper overflow containment. The rerun passed.
