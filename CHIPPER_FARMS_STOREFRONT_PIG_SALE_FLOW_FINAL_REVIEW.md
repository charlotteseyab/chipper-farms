# Chipper Farms Storefront Pig-Sale Flow Final Review

## Final Decision

STOREFRONT PIG-SALE FLOW NOT CERTIFIED — DO NOT SMOKE TEST THIS FLOW

## Audit Scope

This was a read-only independent review of the Storefront -> Standard Stock -> Pig Sales implementation. Application source was not modified. Only the required audit artifacts were created.

## Repository Baseline

- Branch: `remediation/finance-storefront-final-blockers`
- HEAD: `05ebe6e550c64be1c10e10b17573301ec160dd02`
- Implementation range reviewed: `8a55c03..HEAD`
- Diff reviewed: 32 files, 5174 insertions, 14 deletions
- Dirty pre-existing working tree items observed:
  - `D chipper farms.zip`
  - `M resources/views/components/storefront/layout.blade.php`
  - `M resources/views/storefront/public/home.blade.php`
  - `M resources/views/storefront/public/shop.blade.php`
  - `M tests/Feature/Storefront/StorefrontPublicShellTest.php`
  - Multiple pre-existing untracked audit/report artifacts and zip files

## Source Material Reviewed

- `CHIPPER_FARMS_STOREFRONT_PIG_SALE_FLOW_IMPLEMENTATION_REPORT.md`
- `CHIPPER_FARMS_FINANCE_STOREFRONT_FINAL_ARCHITECTURE_AND_IMPLEMENTATION_BLUEPRINT.md`
- `CHIPPER_FARMS_HUMAN_QA_REMEDIATION_REPORT.md`
- `CHIPPER_FARMS_HUMAN_QA_REMEDIATION_EVIDENCE.md`
- `SMOKE_TEST_END_TO_END.md`
- Changed implementation files in `8a55c03..HEAD`, including models, migrations, services, controllers, routes, views, and tests for the pig pool flow

Implementation reports were treated as claims only. Current source and executable evidence were treated as authoritative.

## Blocking Finding

### P0-001: Partial pooled pig sale removes the remaining pool from the public shop

The pooled pig flow still routes every pig-backed listing through the legacy single-pig listing reservation logic during order confirmation.

Evidence:

- `OrderService::confirm()` calls `applyInventoryEffect()` before approving selected pig allocations.
- `applyInventoryEffect()` treats any pig-backed listing as single-head inventory and calls `reservePigListing()`.
- `reservePigListing()` transitions the listing from `published` to `reserved`.
- `StorefrontListing::visible()` and `StorefrontListing::isOrderable()` require `status === published`.
- `StorefrontPigPoolService::syncListingAvailability()` only restores `sold_out` to `published`; it does not restore `reserved` pooled listings to `published`.
- `OrderService::complete()` later calls `markListingsSoldOut()` for any pig-backed listing in `reserved` or `published`, which can close the entire pooled listing even when unsold pigs remain.

Impact:

If a listing contains 5 Standard Stock pigs and a customer orders 3, confirmation can correctly sell the 3 chosen pigs through `/sales`, but the listing status becomes `reserved`. The remaining 2 pigs can still exist in Standard Stock and still count as pool-available internally, yet the listing is no longer public or orderable. On completion the same pooled listing can be moved to `sold_out` even though remaining pigs are unsold.

This violates the required storefront behavior: a listing should represent the pool's remaining sellable animals, not become a single reserved animal after the first order.

## Positive Findings

- Storefront pooled approval creates one real `PigSale` per selected pig through `App\Services\Sales\RecordPigSaleService`.
- Checkout/payment does not create `PigSale` before staff approval.
- Exact pig selection is enforced before confirmation for pooled lines.
- Wrong selection count is refused.
- Cross-organization pig pooling and cross-organization order selection are guarded in the reviewed paths.
- Direct `/sales` sale path is integrated with pool status reconciliation.
- Finance subscriber recognizes sold pooled lines and avoids creating duplicate pig sales for them.
- Focused feature tests for the new flow passed.

## Test Evidence Executed

Command:

```bash
php artisan test tests/Feature/Storefront/PigPoolAllocationTest.php tests/Feature/Storefront/PigPoolConfirmationTest.php tests/Feature/Storefront/PigPoolCreationScreenTest.php tests/Feature/Storefront/PigPoolVisibilityTest.php tests/Feature/Storefront/PigSelectionScreenTest.php tests/Feature/Operations/PigLocalIdTest.php
```

Result:

- Passed: 44 tests
- Assertions: 120
- Duration: 11.31s

Important limitation:

The focused tests assert that unselected pigs remain unsold and that internal pool availability is reduced, but they do not assert that a partially sold pooled listing remains `published`, public, and orderable.

## Certification Gates

- `/sales` single source of truth: PASS for selected pig sale creation, FAIL overall because pooled listing lifecycle breaks storefront continuity after partial sale.
- Standard Stock movement: PASS for selected pigs soft-deleted through `RecordPigSaleService`.
- Storefront order approval flow: FAIL because partial pooled confirmation can remove remaining stock from the public storefront.
- Finance duplicate prevention: PASS in reviewed source and focused test.
- Authorization: PASS for reviewed selection route middleware and org guards.
- Concurrency: NOT FULLY EXECUTED in this audit after P0 blocker was found.
- Browser workflow: NOT EXECUTED in this audit after P0 blocker was found.
- Full default suite: NOT EXECUTED in this audit after P0 blocker was found.
- MariaDB fresh/upgrade suite: NOT EXECUTED in this audit after P0 blocker was found.
- NPM build/audit: NOT EXECUTED in this audit after P0 blocker was found.

## Required Remediation Target

Pooled listings must not use the legacy single-pig `reserved`/`sold_out` lifecycle for partial pool sales. The listing's public state must be derived from remaining pool availability:

- After selecting and approving N of M pigs where M > N, the listing must remain `published`, visible, and orderable with quantity `M - N`.
- Only when the final available pool pig is sold should the listing become `sold_out`.
- Completion of an order must not close a pooled listing while unsold pool pigs remain.
- Regression tests must assert public visibility and orderability after a partial pooled sale, not only internal pool counts.

## Final Verdict

STOREFRONT PIG-SALE FLOW NOT CERTIFIED — DO NOT SMOKE TEST THIS FLOW
