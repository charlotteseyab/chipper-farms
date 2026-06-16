# Storefront Module — Phase 6: Hardening & Refunds (FINAL)

**Status:** complete and verified. This is the final phase — the storefront module (Phases 1–6) is now feature-complete.
**Test result:** full application suite green — **1138 tests / 3295 assertions, 0 failures** (storefront 87; Phase 6 added 5). Migration runs up **and** down. No operational or finance regression.

This README is both the Phase 6 runbook and the **consolidated deploy/verification guide for the whole module** (see §6–§7).

---

## 1. What Phase 6 delivers

**Refunds.** A treasurer-grade refund for storefront orders (`StorefrontOrderRefundService`), gated by a new **`refund_storefront_orders`** permission granted only to **admin/superuser — deliberately not salesperson** (financially-binding reversals sit above the operational sales role). The refund:
- **Reverses the customer-facing financial legs correctly** via the audited `JournalReversalService`: the **revenue** batch (for a cash sale this single balanced reversal also returns the cash) and any settled **cash-receipt** batch. These legs have no inventory-subledger coupling, so the reversal is drift-free.
- **Restores storefront operational availability** — product listings get their quantity returned and are republished so stock is sellable again.
- **Hands the goods-return accounting to finance.** COGS / inventory / biological-asset reversal and pig restoration are **not** auto-reversed — there is no drift-free sales-return primitive (confirmed by recon), so attempting it would risk the GL↔subledger drift this project forbids. Instead the order records a precise **`finance_reversal_note`** stating exactly which legs were reversed automatically and which require the finance team's sales-return process. The order ends `refunded` with full audit (`refunded_at` / `refunded_by_user_id` / `refund_reason`).
- If the order was never posted to finance (`finance_posting_status = pending`), the refund is **clean** — there is simply nothing to reverse.

**Hardening.**
- **Rate limiting** on the two unauthenticated POST surfaces: the public **quote** submission (`throttle:storefront-quote`, 5/min/IP) and the **Paystack webhook** (`throttle:storefront-webhook`, 60/min/IP). Limiters are registered in `StorefrontServiceProvider::boot()`.
- **Permission tightening** — the refund permission is the only new grant, and it is intentionally restricted.

---

## 2. The refund scope boundary (why it stops where it does)

| Leg | Handled how | Why |
|-----|-------------|-----|
| Revenue (feed) | **Auto-reversed** via `JournalReversalService` (reverses the linked `revenue_journal_batch_id`). | Pure GL reversal, no subledger coupling → drift-free. For a cash sale this also returns the cash. |
| Cash receipt (settled COD) | **Auto-reversed** (locates the `auto_feed_sale_receipt` batch and reverses it). | Returns collected cash; pure GL. |
| COGS / inventory (feed) | **Handed to finance** (noted, not reversed). | No drift-free sales-return primitive; reversing COGS GL without restoring the inventory subledger would drift. |
| Pig revenue (invoice) / COGS / biological asset / pig un-delete | **Handed to finance** (noted, not reversed). | Pig revenue is invoice-based and the pig is soft-deleted by the sale; correct restoration is a dedicated sales-return design. |
| Storefront stock availability | **Auto-restored** for products (qty + republish). | Storefront-side state, not the finance subledger — safe to restore. |

This mirrors the project's standing rule: never post a reversal that could desynchronise the GL and a subledger. What the customer experiences — money returned, receivable cleared, order marked refunded — is handled correctly and automatically.

---

## 3. Decisions (Phase 6 — D32–D35)

- **D32** — Refund reverses only the drift-free customer-facing legs automatically (revenue + receipt); COGS/inventory/biological-asset go to finance via a recorded note.
- **D33** — `refund_storefront_orders` is treasurer-grade (admin/superuser only, not salesperson).
- **D34** — Unposted orders refund cleanly (no GL); posted orders reverse via the audited `JournalReversalService` (which refuses double-reversal, so the action is idempotent at the batch level).
- **D35** — Throttle the two public POST endpoints (quote, webhook); keep limits configurable in the provider.

---

## 4. Files

**Added:** `app/Storefront/Services/StorefrontOrderRefundService.php`; `database/seeders/StorefrontRefundPermissionsSeeder.php`; `database/migrations/2028_03_29_100000_add_storefront_refund_fields.php`; `tests/Feature/Storefront/RefundHardeningTest.php`.

**Modified (additive/backward-compatible):** `AdminOrderController` (`refund()` action, permission-gated); `StorefrontServiceProvider` (named rate limiters); `DatabaseSeeder` (calls the refund seeder); `StorefrontOrder` model (refund fillable/casts); `routes/web.php` (refund route + throttle middleware); admin order show view (refund form, treasurer-gated).

---

## 5. Deployment (Phase 6)

```bash
php artisan migrate                                  # 1 additive migration (refund columns)
php artisan db:seed --class=StorefrontRefundPermissionsSeeder
php artisan optimize:clear
```

The Chart of Accounts must already contain the accounts the feed revenue/receipt batches use (existing finance config). Rate limiting uses the configured cache store — ensure a shared cache (redis/database) in production so limits hold across workers.

**Rollback:** `php artisan migrate:rollback --step=1` (drops the refund columns); revoke `refund_storefront_orders` if desired. Reversals already posted are normal audited finance records and are unaffected.

---

## 6. Whole-module deploy guide (Phases 1–6)

Run once, in order:

```bash
php artisan migrate                                  # all storefront migrations (additive)
php artisan db:seed --class=StorefrontPermissionsSeeder
php artisan db:seed --class=StorefrontQuotePermissionsSeeder
php artisan db:seed --class=StorefrontOrderPermissionsSeeder
php artisan db:seed --class=StorefrontRefundPermissionsSeeder
php artisan optimize:clear
php artisan finance:check-outbox-wiring              # expect the three storefront/feed events below
```

`finance:check-outbox-wiring` should list:
- `storefront_order.confirmed → 1 subscriber` (Phase 4 orchestrator)
- `feed_sale.payment_status_changed → 1 subscriber` (Phase 5 feed settlement)

Ensure the scheduler runs `ProcessPendingDomainEventsJob` every minute (it drains the outbox) and `storefront:sync-availability` hourly (listing availability). Both are wired by the providers.

**Provider registration:** `StorefrontServiceProvider` (bootstrap/providers.php) and the finance subscriber registrations in `FinanceServiceProvider`.

---

## 7. End-to-end verification checklist (whole module)

- [ ] Push a pig and a feed type to the storefront → both listings publish with traceability.
- [ ] A customer favourites, requests a quote, and the quote is answered/accepted → converts to an order (Phase 5).
- [ ] Add to cart → checkout (COD and Paystack) → order created, **no finance yet**.
- [ ] Staff **confirm** → `storefront_order.confirmed` posts; next scheduler tick records the underlying feed/pig sale and posts revenue/COGS (Phase 4). Paystack books cash; COD books AR.
- [ ] Staff **complete** a COD order → underlying sale settles (Dr Cash / Cr AR), pig listing → sold_out (Phase 5).
- [ ] Sales report shows correct status/method/revenue aggregates.
- [ ] **Refund** a completed order as admin → revenue/receipt reversed, product restocked, `finance_reversal_note` records the finance hand-off; salesperson is **403**.
- [ ] Hammer the quote/webhook endpoints → throttled (429) past the limit.
- [x] Full suite green: **1138 passed, 0 failures**.

---

## 8. Module complete

Phases 1–6 deliver the v1 storefront end-to-end: catalogue & traceable listings → favourites & quotes → cart & checkout (Paystack + COD) → staff-confirmed finance integration (reusing the sale spine, zero GL drift) → fulfilment, COD settlement & quote conversion → refunds & hardening.

**Known boundaries carried forward (by design, documented):** auctions are out of v1; the **goods-return (COGS/inventory/biological-asset) accounting for refunds** is a dedicated finance sales-return feature, intentionally not built into the storefront to protect ledger integrity; non-kg feed / fixed-price-pig / manure order lines are recognised manually (finance defers them rather than mis-post). Each is recorded at its decision point for a future, dedicated effort.
