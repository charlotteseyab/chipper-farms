# Storefront Module — Phase 3: Orders & Checkout

**Status:** complete and verified.
**Test result:** full application suite green — **1121 tests / 3240 assertions, 0 failures** (storefront now contributes 70; Phase 3 added 22). Migrations run up **and** down. No operational or finance regression.

Builds on Phases 1–2. This README is the deploy/verify runbook for Phase 3 only.

---

## 1. What Phase 3 delivers

- **Cart** — DB-backed, one active cart per signed-in customer. Add/update/remove with price **snapshotting** and live orderable + stock guards. bcmath money throughout.
- **Checkout** — converts the cart to an order in a single transaction: re-validates availability and **re-prices from the listing** at the authoritative moment, snapshots line items, computes totals, captures fulfilment (pickup/delivery) + a delivery-address snapshot, sets the initial status from the payment method, and marks the cart converted.
- **Orders** — full order entity + line items + an append-only **event timeline**, with an explicit status machine.
- **COD flow** — order lands at `pending_confirmation`; staff confirm before acceptance; cash is recorded at collection (Phase 5 fulfilment).
- **Paystack** — initialise → redirect → **server-side verify** → webhook. Verification asserts gateway `status=success` **and** amount + currency match the order (anti-tamper); it is idempotent; and it advances the order to `payment_confirmed` only.
- **The confirmation rule (v1, enforced):** a successful payment **never auto-accepts** an order. Paystack success → `payment_confirmed`; a human still confirms. COD → `pending_confirmation`. `confirm()` is the *only* path to `confirmed`, and it reserves inventory.
- **Inventory effects on confirm** — pig-backed listings transition to `reserved`; product listings (feed/manure) have `quantity_available` decremented (hitting zero → `sold_out`). Cancelling a confirmed order reverses the effect. Applied exactly once, guarded by the status machine.
- **Back-office order workspace** — list/filter, detail with payments + timeline, and confirm / cancel / mark-ready-or-dispatched / re-verify-payment actions, split across `view` / `manage` / `confirm` / `cancel` permissions.
- **Customer order area** — "My orders" list + detail with status, payments and timeline. Real **Add to cart** on listing detail (sign-in required to buy).
- **Tests** — cart totals + guards; COD vs Paystack checkout; Paystack verify (success → payment_confirmed not confirmed; amount-mismatch rejected + payment failed; idempotent); staff confirm reserves a pig / decrements a product; cancel releases; illegal transitions blocked; webhook signature valid/invalid; route access control.

**Out of scope for Phase 3 (later):** finance posting (Phase 4 — the `invoice_id`/`receipt_id`/`finance_posting_status` columns are already on `storefront_orders`, ready); fulfilment scheduling, COD cash receipt at completion, refunds, quote→order conversion (Phase 5); hardening (Phase 6).

---

## 2. Key decisions (Phase 3)

| # | Decision | Rationale |
|---|----------|-----------|
| D14 | **Payment success never auto-accepts.** Paystack verified → `payment_confirmed`; COD → `pending_confirmation`. `confirm()` is the only path to `confirmed`. | The core v1 commercial rule: pigs are real, finite stock; a human confirms availability before the sale is accepted and inventory reserved. |
| D15 | **Verify server-side, always.** The browser callback and the webhook both re-verify via the Paystack API; the webhook body is HMAC-validated first and its amount is never trusted. | Redirects and webhook bodies are forgeable; the verify endpoint + amount/currency match is the only safe source of truth. |
| D16 | **Checkout re-prices from the listing**, not the cart snapshot. | The cart snapshot protects the *display*; the order must bind the authoritative current price at purchase time. |
| D17 | **Sign-in required to buy** (cart/checkout are authenticated); quotes remain open to guests. | Every order ties to a real AR counterparty, which makes Phase 4 finance posting clean. Guest browsing/quoting is unaffected. |
| D18 | **Inventory effect is a side effect of confirmation, reversed on cancel, applied once.** Pig → `reserved`; product → quantity decrement. | Keeps the public shop honest without double-reserving; the status machine guarantees once-only application. |
| D19 | **Orders post nothing to finance in Phase 3.** Finance-link columns are added now (additive) for Phase 4 subscribers. | Preserves the event-driven finance boundary; avoids a later ALTER on a live orders table. |
| D20 | **`AdminOrderController::fulfil()` not `dispatch()`** | The base controller's `DispatchesJobs` trait already defines `dispatch()`; the method was renamed to avoid a fatal signature clash. (Caught during route registration.) |

---

## 3. Schema (additive — 6 new tables)

- `storefront_carts`, `storefront_cart_items` — one active cart per user; line price snapshot; FKs cascade.
- `storefront_orders` — header: status + payment_status + method + fulfilment + delivery snapshot + money + **finance-link columns** (`invoice_id`, `receipt_id`, `finance_posting_status`); soft deletes.
- `storefront_order_items` — line snapshot incl. soft listing link + source snapshot; decimal(18,4) qty/weight, decimal(18,2) money.
- `storefront_order_payments` — payment transactions; unique `(provider, provider_reference)` blocks duplicate webhooks; `raw_response` kept for audit.
- `storefront_order_events` — append-only audit/timeline.

No operational/finance table is touched. Migrations are reversible (verified up + down).

---

## 4. Files

**Added:** 3 migrations; 6 models (`StorefrontCart`, `StorefrontCartItem`, `StorefrontOrder`, `StorefrontOrderItem`, `StorefrontOrderPayment`, `StorefrontOrderEvent`); 5 services (`CartService`, `CheckoutService`, `OrderService`, `PaymentService`, `PaystackService`) + 4 exceptions + `CheckoutData`; 3 Paystack classes (`PaystackClient`, `HttpPaystackClient`, `PaystackSignature`); 5 controllers (`CartController`, `CheckoutController`, `PaystackWebhookController`, `CustomerOrderController`, `AdminOrderController`) + `CheckoutRequest`; `StorefrontOrderPermissionsSeeder`; 6 views; 4 tests + a `FakePaystackClient` double.

**Modified (additive):** `config/services.php` (paystack block), `bootstrap/app.php` (webhook CSRF exemption), `routes/web.php` (16 routes), `DatabaseSeeder` (order-perms seeder), `StorefrontServiceProvider` (binds `PaystackClient`), public `detail.blade.php` (real Add to cart), storefront layout + account/admin dashboards (cart/orders links), `StorefrontTestCase` (helpers).

---

## 5. Deployment

```bash
php artisan migrate                       # 3 additive Phase 3 migrations
php artisan db:seed --class=Database\\Seeders\\StorefrontOrderPermissionsSeeder
npm install && npm run build              # cart/checkout/order views
php artisan optimize:clear
```

**Environment (Paystack):**
```
PAYSTACK_SECRET_KEY=sk_live_xxx
PAYSTACK_PUBLIC_KEY=pk_live_xxx
PAYSTACK_CURRENCY=GHS
# optional: PAYSTACK_BASE_URL, PAYSTACK_CALLBACK_URL
```
In the Paystack dashboard set the **webhook URL** to `https://<your-app>/shop/paystack/webhook`. That exact path is CSRF-exempt (see `bootstrap/app.php`) and authenticated by HMAC signature.

New permissions: `view_storefront_orders`, `manage_storefront_orders`, `confirm_storefront_orders`, `cancel_storefront_orders` (admin/superuser/salesperson get all; view also to manager/accountant).

---

## 6. Rollback

```bash
php artisan migrate:rollback --step=3     # drops carts + orders + payments/events only
```

Modified files are additive; restore them from the previous commit to fully revert. New permission rows are inert if unused. No operational/finance data affected.

---

## 7. Verification checklist

- [x] `migrate` + `migrate:rollback` succeed (up + down verified).
- [x] Full suite green: 1121 passed, 0 failures.
- [x] Storefront suite green: 70 passed.
- [ ] Signed-in customer can add an orderable listing to the cart, see correct totals, and check out.
- [ ] COD checkout creates a `pending_confirmation` order; the customer sees it under **My orders**.
- [ ] Paystack checkout redirects to the gateway; on return the order is `payment_confirmed` (not `confirmed`).
- [ ] Staff **Confirm** reserves a pig listing / decrements a product's quantity; **Cancel** reverses it.
- [ ] Paystack webhook with a valid signature verifies the payment; an invalid signature is rejected (401).
- [ ] A customer cannot open another customer's order; a customer cannot reach the back-office orders screen.

---

## 8. Next: Phase 4 — Finance integration

Wire storefront order/payment domain events into the existing transactional-outbox → subscriber → `PostingEngine` spine (mirroring the pig/feed-sale path; **never post on order creation**). COD and Paystack finance flows, cancellation/refund updates, and customer-ledger linkage — populating the `invoice_id` / `receipt_id` / `finance_posting_status` columns already present on `storefront_orders`. Same delivery bar: recon, build, full-suite green, README + delta zip.
