# Phase 03 - Paystack Refunds

Implemented authenticated refund settlement handling in `PaystackWebhookController`.

Covered events:

- `refund.processed`: marks the order refunded, marks payment refunded, stores provider status, records a payment event.
- `refund.failed`: keeps the order in refund-pending triage state and records a safe failure reason.
- unknown refund references are logged and ignored without mutating orders.

Existing refund submission behavior remains:

- gateway refund is submitted before internal reversal;
- pending Paystack refunds do not mark the order refunded;
- retries reuse the same idempotency key.

Evidence:

- `php artisan test tests/Feature/Storefront/OrderHttpTest.php`: 11 passed / 38 assertions.
- `php artisan test tests/Feature/Storefront/RefundExecutionTest.php`: included in focused and full runs.

External Paystack sandbox webhook replay remains pending.
