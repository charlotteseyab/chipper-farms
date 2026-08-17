# Storefront Pricing + Purchase Flow Remediation Evidence

## Commands Run

`php artisan test tests/Feature/Storefront/StorefrontPushCategoryTest.php tests/Feature/Storefront/StorefrontFulfilmentStateTest.php tests/Feature/Storefront/FinanceIntegrationTest.php`

Result: 46 passed, 166 assertions.

`php artisan test tests/Feature/Storefront/StorefrontPigCheckoutTest.php tests/Feature/Storefront/StorefrontPigletSaleTest.php`

Result: 17 passed, 59 assertions.

`php artisan test tests/Feature/Storefront`

Result: 400 passed, 1224 assertions.

## Evidence Summary

- Breeding stock can be pooled, listed, and counted.
- Forged stock-backed kind/mode/basis/currency/quantity/price fields are ignored.
- Empty standard/breeding listings are refused without creating a shell listing.
- Live approval writes a real `PigSale` using the customer-selected animal weight snapshot.
- Carcass approval splits requested kg and money across manager-selected pigs and writes `weight_type = carcass`.
- Manual manure/feed/service storefront lines post a real Finance invoice and do not create PigSale or FeedSale unless they are true pig/feed source-backed lines.
- Piglet count listings still materialize at approval; tagged piglet pools sell through the ordinary pig allocation path.

