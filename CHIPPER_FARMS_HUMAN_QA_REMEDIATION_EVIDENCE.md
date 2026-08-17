# Chipper Farms Human-QA Remediation Evidence

## Code Changes

- `app/Helpers/NavigationHelper.php`: added Superuser-only Modules & Features link; added permission-aware Storefront section with route checks and Quotes feature gating.
- `app/Http/Controllers/Settings/ModuleFeatureController.php`: added explicit superuser guard to index, update, and preset actions.
- `resources/views/components/storefront/layout.blade.php`: unified public shop shell with Chipper Farms branding and guest-safe navigation.
- `resources/views/storefront/admin/listings/index.blade.php`: added status-aware safe row actions and an actionable empty state.
- `tests/Browser/MobileViewportTest.php`: added storefront listings and public shop, corrected storefront orders URL.
- `app/Models/WeightAlert.php`: fixed PHP 8.4 nullable parameter deprecation surfaced during browser evidence.

## Test Evidence

- `php artisan test tests/Feature/Features/ModuleSettingsScreenTest.php tests/Feature/Navigation/HumanQaSidebarNavigationTest.php tests/Feature/Storefront/StorefrontListingActionsScreenTest.php tests/Feature/Storefront/StorefrontPublicShellTest.php`
  - Passed: 18 tests, 75 assertions.
- `php artisan test`
  - Passed: 1,785 tests, 13 skipped, 5,506 assertions.
- `php artisan test tests/Feature/Weights/WeightAuditTest.php tests/Feature/Weights/WeightStandardsAuditTest.php tests/Feature/Observers/WeightAlertGenerationServiceTest.php`
  - Passed: 10 tests, 44 assertions.
- `APP_URL=http://127.0.0.1:8899 DB_DATABASE=chipper_farms_upgrade_test php artisan dusk tests/Browser/MobileViewportTest.php`
  - Passed: 1 test, 1 assertion.
- `./vendor/bin/phpunit -c phpunit.mariadb.xml tests/Feature/Features/ModuleSettingsScreenTest.php tests/Feature/Navigation/HumanQaSidebarNavigationTest.php tests/Feature/Storefront/StorefrontListingActionsScreenTest.php tests/Feature/Storefront/StorefrontPublicShellTest.php tests/Feature/Weights/WeightAuditTest.php tests/Feature/Weights/WeightStandardsAuditTest.php tests/Feature/Observers/WeightAlertGenerationServiceTest.php`
  - Passed: 28 tests, 119 assertions.
- `./vendor/bin/phpunit -c phpunit.mariadb.xml`
  - Passed: 1,798 tests, 5,516 assertions, 7 skipped.
- `./vendor/bin/phpunit -c phpunit.concurrency.xml`
  - Passed: 15 tests, 270 assertions.
- `npm run build`
  - Passed; Vite built successfully.
- `npm audit --audit-level=high`
  - Passed; 0 vulnerabilities.
- `php artisan optimize`
  - Passed; config, events, routes, and views cached successfully.
- `php artisan config:clear`, `php artisan route:clear`, `php artisan view:clear`
  - Passed after cache-build certification.

## Browser Evidence

- Local server used for Dusk: `APP_URL=http://127.0.0.1:8899`.
- Database used for Dusk: `chipper_farms_upgrade_test`.
- Viewport: 390px by 844px.
- Screens checked: dashboard, finance overview, finance expenses, money-in, contacts, storefront listings, storefront orders, public shop, all stock, sales.
- Result: no page-level horizontal overflow beyond tolerance.

## Human QA Finding Evidence

- QA-001: Sidebar test verifies Superuser sees Modules & Features and Admin does not. Controller test verifies Admin with `manage_feature_flags` is denied on GET, PUT, and preset POST.
- QA-002: Sidebar test verifies authorised farm staff receive Storefront section and unauthorized Data Entry does not.
- QA-003: Public shell test verifies Chipper Farms brand, logo alt text, shop navigation, cart, sign-in, listing visibility, and no back-office link leakage.
- QA-004: Listing actions test verifies Actions column and status-aware controls for draft, published, archived, and read-only users.

## Smoke-Test Mapping

- Shop and checkout: Storefront cart, guest checkout, COD, hold, order lifecycle, finance integration, and public shell tests.
- Money reaches books: finance posting, receipts, money-in, storefront settlement, refunds, and returns tests.
- Broken-area checks: finance overview/sidebar feature gating, failed postings, multi-farm storefront pricing, mobile viewport, duplicate webhook, and returns tests.

## Notes

The first attempt at a full MariaDB run was stopped because Laravel cache files were generated while the suite was already running. It was discarded as noisy evidence. The clean full MariaDB run after cache cleanup passed.
