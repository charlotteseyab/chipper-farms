# Chipper Farms Storefront Pig Pool P0 Remediation Evidence

## Source Evidence

- `OrderService::applyInventoryEffect()` now calls `StorefrontPigPoolService::syncListingAvailability()` for pooled pig listings and leaves `reservePigListing()` for legacy single-pig listings only.
- `OrderService::markListingsSoldOut()` now recalculates pooled availability instead of blindly transitioning a partial pool to `sold_out`.
- `StorefrontPigPoolService::availableCount()` derives commercial availability from eligible Standard Stock pigs, not stale pool-row status alone.
- `StorefrontPigPoolService::assertPigMayBeSoldDirectly()` locks active allocations, pool rows, listings, and holds before allowing a direct sale.
- `RecordPigSaleService` keeps `/sales` as the canonical sale writer and invokes the direct-sale guard for non-storefront allocation sales.
- `StorefrontPigAllocationService` continues to create real `PigSale` records for each selected pig and passes allocation context to the canonical sale service.
- `ListingDetailPresenter` shows pooled listing facts instead of a single-source missing-record warning.

## Automated Evidence

| Gate | Command | Result |
|---|---|---|
| Focused pig-pool feature subset | `php artisan test tests/Feature/Storefront/PigPoolAllocationTest.php tests/Feature/Storefront/PigPoolConfirmationTest.php tests/Feature/Storefront/PigPoolVisibilityTest.php tests/Feature/Storefront/PigSelectionScreenTest.php` | PASS: 41 passed, 138 assertions, 13.60s |
| Expanded storefront/stock slice | `php artisan test tests/Feature/Storefront/PigPoolAllocationTest.php tests/Feature/Storefront/PigPoolConfirmationTest.php tests/Feature/Storefront/PigPoolCreationScreenTest.php tests/Feature/Storefront/PigPoolVisibilityTest.php tests/Feature/Storefront/PigSelectionScreenTest.php tests/Feature/Operations/PigLocalIdTest.php` | PASS: 51 passed, 162 assertions, 14.42s |
| Full MariaDB suite | `./vendor/bin/phpunit -c phpunit.mariadb.xml` | PASS: Tests 1849, Assertions 5679, Skipped 7, 13:56.029 |
| Concurrency suite | `./vendor/bin/phpunit -c phpunit.concurrency.xml` | PASS: 19 tests, 324 assertions, 01:09.476 |
| Focused browser pooled flow | `APP_URL=http://127.0.0.1:8899 DB_DATABASE=chipper_farms_upgrade_test php artisan dusk tests/Browser/PigPoolFlowTest.php` | PASS: 5 passed, 24 assertions, 43.20s |
| Full browser suite | `APP_URL=http://127.0.0.1:8899 DB_DATABASE=chipper_farms_upgrade_test php artisan dusk` | PASS: 6 passed, 25 assertions, 66.18s |
| Full default suite | `php artisan test` | PASS: 1836 passed, 13 skipped, 5669 assertions, 316.85s |
| Diff whitespace | `git diff --check` | PASS |
| Composer manifest | `composer validate --no-check-publish` | PASS: composer.json is valid |
| Composer audit | `composer audit` | PASS: No security vulnerability advisories found |
| NPM audit | `npm audit` | PASS: found 0 vulnerabilities |
| Frontend production build | `npm run build` | PASS: Vite build completed |
| Laravel production cache | `php artisan optimize` | PASS: config, events, routes, views cached |

## New Regression Tests

- `test_partial_pooled_confirmation_keeps_remaining_pigs_public_and_orderable`
- `test_completing_a_partial_pooled_order_does_not_close_the_remaining_pool`
- `test_the_listing_sells_out_only_after_the_final_pool_pigs_are_sold`
- `test_storefront_and_direct_sales_keep_a_partial_pool_published_until_the_last_pig_is_sold`
- `test_stale_pool_rows_do_not_keep_dead_pigs_publicly_available`
- `test_a_direct_sale_cannot_consume_pigs_already_covered_by_active_storefront_holds`
- `test_the_sale_service_refuses_an_allocated_storefront_pig_even_if_the_form_is_bypassed`
- `test_partial_pool_sale_stays_public_until_the_final_pigs_are_sold`

## Notes

The initial sandboxed MariaDB run was interrupted after environment-level DB access failures. It was rerun with the required database permissions and passed. `composer audit` initially failed in the sandbox because DNS/cache access was restricted, then passed with approved network/cache access.
