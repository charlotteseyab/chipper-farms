# Storefront Pricing + Purchase Flow Final Evidence

## Final Verification

Command:

`php artisan test tests/Feature/Storefront`

Result:

400 passed, 1224 assertions.

## Focused Remediation Verification

Command:

`php artisan test tests/Feature/Storefront/StorefrontPushCategoryTest.php tests/Feature/Storefront/StorefrontFulfilmentStateTest.php tests/Feature/Storefront/FinanceIntegrationTest.php`

Result:

46 passed, 166 assertions.

Command:

`php artisan test tests/Feature/Storefront/StorefrontPigCheckoutTest.php tests/Feature/Storefront/StorefrontPigletSaleTest.php`

Result:

17 passed, 59 assertions.

