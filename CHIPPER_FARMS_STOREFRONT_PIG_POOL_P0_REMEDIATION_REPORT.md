# Chipper Farms Storefront Pig Pool P0 Remediation Report

## Final Status

STOREFRONT PIG-SALE FLOW REMEDIATION COMPLETE — CERTIFIED FOR HUMAN SMOKE TEST

## Scope

This remediation addresses the P0 pooled-pig storefront defect where a partial storefront sale from a multi-pig listing could incorrectly remove the remaining sellable pigs from the public shop. The review covered the storefront order lifecycle, pig allocation, `/sales` direct sale integration, standard stock reconciliation, MariaDB behavior, concurrency, browser flow, cache, frontend build, and dependency audit gates.

## Original P0

A pooled pig listing was treated like a legacy single-pig listing during confirmation and completion. When an order consumed only part of the pool, the listing could transition to `reserved` or `sold_out`, causing the remaining Standard Stock pigs to disappear from the public shop even though they were still sellable.

## Root Cause

`OrderService::applyInventoryEffect()` called the legacy `reservePigListing()` path for pig-backed listings without separating single-pig listings from pooled pig listings. `OrderService::markListingsSoldOut()` also closed pig-backed listings without recalculating the underlying pool. Public visibility and ordering require `published`, so a pooled listing in `reserved` or premature `sold_out` became unavailable.

## Remediation Summary

- `StorefrontPigPoolService::syncListingAvailability()` now recalculates pooled listing availability from still-sellable linked Standard Stock pigs and restores pooled listings from legacy `reserved` to `published` when inventory remains.
- `StorefrontPigPoolService::availableCount()` now excludes stale rows whose pigs are sold, dead, soft-deleted, non-standard, cross-organization, or otherwise ineligible.
- `OrderService` now routes pooled pig listings through pool availability synchronization instead of legacy single-pig reservation.
- `OrderService::markListingsSoldOut()` now closes pooled listings only when the recalculated pool is exhausted.
- COD completion now settles every `PigSale` created for pooled allocations instead of relying on a single order-item posted-sale reference.
- `StorefrontPigAllocationService` marks allocation-created sales as trusted storefront allocation sales.
- `RecordPigSaleService` now blocks direct `/sales` writes that would consume a pig already allocated to an order or undercut active storefront holds.
- `PigSaleController` now returns a user-facing validation error for direct-sale conflicts instead of surfacing an exception.
- `ListingDetailPresenter` now presents pooled listing attributes without reporting a missing single source record.

## Files Modified For This Remediation

- `app/Storefront/Services/StorefrontPigPoolService.php`
- `app/Storefront/Services/OrderService.php`
- `app/Storefront/Services/StorefrontPigAllocationService.php`
- `app/Services/Sales/RecordPigSaleService.php`
- `app/Http/Controllers/PigSaleController.php`
- `app/Storefront/Support/ListingDetailPresenter.php`
- `tests/Feature/Storefront/PigPoolConfirmationTest.php`
- `tests/Feature/Storefront/PigPoolAllocationTest.php`
- `tests/Feature/Storefront/PigPoolVisibilityTest.php`
- `tests/Browser/PigPoolFlowTest.php`

## Important Non-Remediation Worktree Items

The worktree also contains pre-existing/user changes outside this remediation, including storefront public layout/view edits and a deleted `chipper farms.zip`. Those were not reverted or claimed as part of the pig-pool lifecycle fix.

## Remediation Decision

The original P0 is fixed. Partial pooled listings remain published, public, orderable, and quantity-correct while sellable pigs remain. The listing sells out only when the final linked sellable pig is sold. Direct `/sales` remains canonical and synchronizes with storefront availability while refusing active storefront commitments.
