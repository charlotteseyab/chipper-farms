# Chipper Farms Storefront Pricing/Purchase Final Certification Report

## Document Control

- Audit type: Independent final certification audit
- Branch: `remediation/finance-storefront-final-blockers`
- Commit: `562a5540cc88bcdc501f8697d0a17204ab57645c`
- Implementation range reviewed: `05ebe6e550c64be1c10e10b17573301ec160dd02..562a5540cc88bcdc501f8697d0a17204ab57645c`
- Application source modified during audit: No

## Final Verdict

`STOREFRONT PRICING & PURCHASE FLOW NOT CERTIFIED — DO NOT SMOKE TEST THIS FLOW`

The implementation cannot be released to human smoke testing because the source review found unresolved P0 defects in the core chain:

`Setup Pricing → Stock Source → Listing → Customer Selection → Checkout Snapshot → Hold → Manager Fulfilment → Canonical PigSale → /sales → Finance`

## Repository Baseline

- Dirty state includes a pre-existing tracked deletion of `chipper farms.zip`.
- Numerous untracked historical report and zip artifacts are present.
- New audit artifacts created by this pass are listed separately.

## Architecture Verdict

The implementation made substantial progress: it introduced setup-pricing reads, stock category UI, live/carcass cart lines, holds, manager allocation, piglet materialisation, direct-sale guards, and browser/concurrency test files. However, the final canonical sale and finance chain is not coherent enough to certify.

## P0 Findings

### CF-SPP-P0-001 — Breeding Stock Source Dead-Ends

Breeding Stock appears in the selector and candidates can be loaded from `stock_type = breeding`, but attaching pigs still rejects anything not `standard`. Availability also excludes non-standard stock. The result is a breeding-stock listing shell with no sellable pool.

Evidence:

- `app/Storefront/Services/StorefrontPigPoolService.php:100-104`
- `app/Storefront/Services/StorefrontPigPoolService.php:234-238`
- `app/Storefront/Services/StorefrontPigPoolService.php:361-365`
- `app/Http/Controllers/Storefront/PushToStorefrontController.php:190-205`
- `tests/Feature/Storefront/StorefrontPushCategoryTest.php:193-210`

### CF-SPP-P0-002 — Live/Carcass PigSales Do Not Reliably Carry Correct Weight/Rate/Type

Cart/order snapshots store `requested_kg`, `line_weight_kg`, `price_per_kg_snapshot`, selected listing pig IDs and calculation details. But `StorefrontPigAllocationService::saleDataFor()` ignores those fields and uses legacy `weight_kg` and `price_basis`. For pooled listings, `weight_kg` is the listing estimate, often null, and `price_basis` can remain `fixed`, causing carcass sales to fall back to `live`.

This blocks `/sales` and Finance reconciliation for both live and carcass flows.

Evidence:

- `app/Storefront/Services/CheckoutService.php:224-238`
- `app/Storefront/Services/CheckoutService.php:475-493`
- `app/Storefront/Services/StorefrontPigAllocationService.php:749-789`
- `app/Services/Sales/RecordPigSaleService.php:174-190`

### CF-SPP-P0-003 — Manual Entry Sales Can Disappear From Finance

Manual Entry is allowed, but the finance subscriber only routes pig kinds and FeedType-backed feed lines. Manure/service/manual feed without a real source is skipped/deferred. The existing test explicitly expects `finance_posting_status = skipped` for manure, which contradicts the requirement that Manual Entry reaches finance without a fake PigSale.

Evidence:

- `app/Finance/Subscribers/Sales/StorefrontOrderConfirmedSubscriber.php:189-210`
- `app/Finance/Subscribers/Sales/StorefrontOrderConfirmedSubscriber.php:334-368`
- `tests/Feature/Storefront/FinanceIntegrationTest.php:160-188`

## P1 Findings

### CF-SPP-P1-001 — Push Request Hidden Fields Are Forgeable

The push form hides/removes generic fields visually, but server normalization keeps submitted `kind`, `sale_mode`, `pricing_basis`, and `currency_code` if present. Non-manual source categories should overwrite those fields from trusted category rules, not accept forged payloads.

Evidence:

- `app/Http/Requests/Storefront/PushToStorefrontRequest.php:141-188`
- `app/Http/Requests/Storefront/PushToStorefrontRequest.php:218-228`

### CF-SPP-P1-002 — Empty Stock-Backed Listing Shells Can Be Created

For standard/breeding stock categories, no selected pigs should be a validation error. Current code sets quantity to zero and skips `attachPigs()` entirely if no pool IDs are present, bypassing the empty-selection guard.

Evidence:

- `app/Http/Requests/Storefront/PushToStorefrontRequest.php:153-162`
- `app/Http/Controllers/Storefront/PushToStorefrontController.php:182-207`

## P2 Findings

### CF-SPP-P2-001 — Stock Selector Search Is Narrower Than Required

The requirement names search by ear tag, local ID, breed, gender, age and latest weight. Server search covers ear tag/code/local ID/breed/gender. Client search covers ear tag/breed/gender only.

Evidence:

- `app/Storefront/Services/StorefrontPigPoolService.php:150-159`
- `resources/views/storefront/admin/listings/push.blade.php:241-244`

## Quality Gates

- Focused feature test run: `php artisan test tests/Feature/Storefront/StorefrontPushCategoryTest.php`
- Result: PASS, `23 passed`, `76 assertions`, `8.76s`
- `git diff --check`: PASS
- Full default suite: not run after P0 source blockers were found.
- MariaDB suite: not run.
- Concurrency suite: not run.
- Dusk/Playwright: not run in this final audit.
- Composer/npm/cache gates: not run in this final audit.

## Human Smoke-Test Readiness

No. Human smoke testing should not restart until the P0/P1 blockers are corrected and then proven with source review, database assertions, concurrency tests, and real browser coverage.

## Required Correction Summary

1. Make source category authoritative server-side.
2. Allow Breeding Stock listings to attach and count only breeding pigs; Standard Stock only standard pigs.
3. Reject empty stock-backed pools before listing creation.
4. Carry live/carcass order-line basis correctly from cart to order.
5. Build PigSale payloads from trusted live/carcass snapshots, not legacy listing estimate fields.
6. Allocate carcass kg and money so sums exactly match requested kg and order line total.
7. Implement or restrict Manual Entry finance so no confirmed manual sale disappears from finance and no fake PigSale is created.
8. Add tests that assert final `pig_sales` rows, sale weights, weight type, price/kg, finance posting, and pool availability, not only intermediate UI/listing rows.

## Final Sign-Off

`STOREFRONT PRICING & PURCHASE FLOW NOT CERTIFIED — DO NOT SMOKE TEST THIS FLOW`
