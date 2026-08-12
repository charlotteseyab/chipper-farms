# Chipper Farms Finance + Storefront Blocker Remediation Evidence

Date: 2026-08-12

## Baseline

- Branch created: `remediation/finance-storefront-final-blockers`.
- Baseline tag created: `pre-finance-storefront-final-blocker-remediation`.
- Baseline commit: `3ed95b263bbcdd1be00b099cfa63c709e33ab51c`.

## Passing Evidence

- `php artisan test`: 1776 passed, 13 skipped, 5451 assertions, 253.53s.
- `php artisan test tests/Feature/AuthenticationTest.php`: 3 passed, 5 assertions.
- `php artisan test tests/Feature/Features/ModuleSettingsScreenTest.php`: 9 passed, 20 assertions.
- `php artisan test tests/Feature/Finance/Expenses/QuickExpenseAttachmentTest.php`: 9 passed, 14 assertions.
- Focused finance skip-removal slice: 146 passed, 433 assertions, 37.81s.
- `npm audit fix`: changed 2 packages, found 0 vulnerabilities.
- `npm ci`: passed, found 0 vulnerabilities.
- `npm audit --audit-level=low`: found 0 vulnerabilities.
- `npm run build`: passed, output assets generated under `public/build`.
- Guarded XAMPP MariaDB probe: `10.4.32-MariaDB`, selected `chipper_farms_test`.
- Production-cache MariaDB gate: 1789 tests, 5461 assertions, 7 skipped, 12:02.447.
- Current cached MariaDB storefront/payment slice: 37 tests, 117 assertions, 01:16.570.
- Full non-cached MariaDB gate: 1789 tests, 5461 assertions, 7 skipped, 11:56.054.
- Concurrency gate after remediation: 15 tests, 270 assertions.
- Browser/Dusk mobile viewport gate: `MobileViewportTest` passed against `chipper_farms_upgrade_test`, 1 test, 1 assertion, 13.82s.

## Invalidated Evidence

- One production-cache run failed after an overlapping focused test process ran against the same database. It is invalid evidence and was rerun in isolation.
- One focused finance slice failed with deadlocks during that overlap. It passed when rerun alone.
- One frontend build failed while `npm ci` was concurrently replacing `node_modules`. It passed when rerun alone.
- One Dusk mobile viewport run failed on `/all-stocks` horizontal overflow. It passed after footer, searchable-select, and table wrapper remediation.
- One cached storefront/payment slice passed at PHPUnit level but was rejected by the runner because ANSI-colored `OK (...)` output was not parsed. The runner parser was fixed and the same slice passed as accepted evidence.

## External Evidence Not Available Locally

- Live Paystack sandbox charge/refund/webhook replay.
- Runtime supervisor and scheduler operation on staging/production infrastructure.
- Production storage and signed-download verification on deployed infrastructure.
