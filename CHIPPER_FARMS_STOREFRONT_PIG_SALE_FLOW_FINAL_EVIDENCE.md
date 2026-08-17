# Chipper Farms Storefront Pig-Sale Flow Final Evidence

## Baseline Commands

```bash
git status --short
git branch --show-current
git rev-parse HEAD
git log --oneline --decorate -40
git diff --stat 8a55c03..HEAD
git diff --name-status 8a55c03..HEAD
```

Observed:

- Branch: `remediation/finance-storefront-final-blockers`
- HEAD: `05ebe6e550c64be1c10e10b17573301ec160dd02`
- Implementation range: `8a55c03..HEAD`
- Changed files: 32
- Diff size: 5174 insertions, 14 deletions

## Material Files Reviewed

- `app/Storefront/Services/StorefrontPigPoolService.php`
- `app/Storefront/Services/StorefrontPigAllocationService.php`
- `app/Storefront/Services/OrderService.php`
- `app/Services/Sales/RecordPigSaleService.php`
- `app/Finance/Subscribers/Sales/StorefrontOrderConfirmedSubscriber.php`
- `app/Http/Controllers/Storefront/AdminOrderController.php`
- `app/Http/Controllers/Storefront/PushToStorefrontController.php`
- `app/Models/Storefront/StorefrontListing.php`
- `app/Models/Storefront/StorefrontListingPig.php`
- `app/Models/Storefront/StorefrontOrderItemPigAllocation.php`
- `database/migrations/2028_09_22_100000_create_storefront_listing_pigs_table.php`
- `database/migrations/2028_09_22_100100_create_storefront_order_item_pig_allocations_table.php`
- `routes/web.php`
- Pig pool feature, browser, and concurrency tests added in the implementation range

## Key Source Evidence

`app/Storefront/Services/OrderService.php`:

- `confirm()` calls `assertPigSelectionComplete()`, then `applyInventoryEffect()`, then `StorefrontPigAllocationService::approveAllocationsForOrder()`.
- `applyInventoryEffect()` calls `reservePigListing()` for any pig-backed listing.
- `reservePigListing()` transitions a pig-backed listing from `published` to `reserved`.
- `markListingsSoldOut()` transitions pig-backed listings from `reserved` or `published` to `sold_out` at completion.

`app/Models/Storefront/StorefrontListing.php`:

- `scopeVisible()` requires `status = published`.
- `isOrderable()` requires `status = published` and fixed sale mode.
- `usesPigPool()` exists, but `OrderService::applyInventoryEffect()` and `markListingsSoldOut()` do not use it.

`app/Storefront/Services/StorefrontPigPoolService.php`:

- Pool quantity is correctly derived from sellable pool rows.
- `syncListingAvailability()` flips `published -> sold_out` when availability is zero and `sold_out -> published` when availability is positive.
- It does not recover `reserved -> published` after a partial pooled confirmation.

## Executed Test Evidence

Command:

```bash
php artisan test tests/Feature/Storefront/PigPoolAllocationTest.php tests/Feature/Storefront/PigPoolConfirmationTest.php tests/Feature/Storefront/PigPoolCreationScreenTest.php tests/Feature/Storefront/PigPoolVisibilityTest.php tests/Feature/Storefront/PigSelectionScreenTest.php tests/Feature/Operations/PigLocalIdTest.php
```

Result:

- PASS
- 44 tests
- 120 assertions
- Duration: 11.31s

Coverage limitation:

- No executed test asserted that a partial pooled sale leaves the listing `published`.
- No executed test asserted that a partial pooled sale leaves the listing visible in public `scopeVisible()`.
- No executed test asserted that a partial pooled sale leaves `isOrderable()` true while pool animals remain.

## Not Executed After Blocker

The full certification gates below were not executed after the P0 blocker was found:

- Full `php artisan test`
- Full MariaDB suite
- Concurrency suite
- Browser/Dusk or Playwright workflow
- NPM build/audit
- Production cache checks

Reason: certification became impossible once the source-level P0 blocker was established.

## Final Evidence Conclusion

The implementation contains substantial correct work around pig allocation, sale creation, and finance duplicate prevention. However, the listing lifecycle still contains a legacy single-pig assumption that breaks the pooled listing model after a partial sale.

Final decision:

STOREFRONT PIG-SALE FLOW NOT CERTIFIED — DO NOT SMOKE TEST THIS FLOW
