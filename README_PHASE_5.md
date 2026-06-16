# Storefront Module — Phase 5: Fulfilment & Sales Ops

**Status:** complete and verified (forward sales-ops scope — see §2 for what's deliberately deferred).
**Test result:** full application suite green — **1133 tests / 3282 assertions, 0 failures** (storefront now 82; Phase 5 added 6). Migration runs up **and** down. No operational or finance regression.

Builds on Phases 1–4. This README is the deploy/verify runbook for Phase 5 only.

---

## 1. What Phase 5 delivers

- **Fulfilment completion.** `confirmed → ready_for_pickup / out_for_delivery → completed` via `OrderService::complete()`. On completion, pig-backed listings move to **`sold_out`** and the order is stamped `completed_at` / `completed_by_user_id`.
- **COD cash receipt on completion — through the spine, no shortcuts.** Completing a cash-on-delivery order flips each linked underlying sale to `paid`, which posts the cash receipt (Dr Cash / Cr AR):
  - **pigs** reuse the existing `UpdatePigSalePaymentStatusService` → `pig_sale.payment_status_changed`;
  - **feed** uses a **new feed payment-status spine built this phase** — `FeedSalePaymentStatusChanged` event + `FeedSalePaymentReceivedSubscriber` (Dr Cash 1030 / Cr AR 1120) + `UpdateFeedSalePaymentStatusService`. This closes a real gap in the core finance module (feed previously had no event-driven settlement for a pending AR), built to mirror the proven pig pattern.
  Paystack orders are already paid and are not re-settled.
- **Durable finance linkage.** Each order item now records the `FeedSale` / `PigSale` it posted (`posted_sale_type` / `posted_sale_id`), written by the Phase-4 orchestrator. Completion settles the *exact* underlying sale; lines with no posted sale yet are noted, never silently skipped.
- **Quote → order conversion.** An **accepted** quote converts to a `pending_confirmation` order via `QuoteService::convertToOrder()`, linking `quote_id ↔ converted_order_id` and marking the quote `converted`. Idempotent. Converted (free-text) lines are treated by finance as manually-invoiced — safe by design.
- **Order notes / staff comms.** Staff can add notes to an order; each appears on the timeline and is appended to a rolling `staff_notes`.
- **Storefront sales report.** Read-only report (orders by status / payment method, realised revenue from completed orders, open-order pipeline) over a date range, gated by `view_storefront_reports`. Linked from the back-office dashboard.

---

## 2. Scope boundary — what is **deferred**, and why

**Full sale reversal** — reversing a *posted* storefront sale (credit-note revenue reversal + COGS/inventory restore + un-soft-deleting a sold pig) — is **not** in Phase 5.

Recon established that the available primitives don't compose into a correct one-call reversal: `RefundService` only reverses the **cash leg** (Dr AR / Cr Cash), and feed AR posts as a **raw GL balance**, not an invoice a receipt/credit-note can target. A correct full reversal needs its own design (credit notes against revenue, inventory/biological-asset restoration, pig un-delete, listing re-publish). Shipping a half-correct GL reversal is exactly what this project forbids, so it is called out as its own work item rather than rushed in here.

What still works safely today: **cancelling an unfulfilled confirmed order** already releases inventory (Phase 3 `cancel()`), and completion is forward-only. Post-completion refunds are the deferred item.

---

## 3. Key decisions (Phase 5 — D28–D31)

| # | Decision | Rationale |
|---|----------|-----------|
| D28 | **COD settles at completion, through the sale spine** (flip underlying sale → `paid`), not via a bespoke storefront posting. | Reuses the audited receipt path; the storefront never writes the GL directly. |
| D29 | **Build the missing feed payment-status spine** rather than special-case feed COD in the storefront. | Fixes the gap correctly for the whole system, mirroring the pig pattern; feed COD now settles like any feed sale. |
| D30 | **Persist the order-item → underlying-sale link.** | Completion (and any future reversal) must act on the exact posted sale; re-deriving it is fragile. |
| D31 | **Defer full sale reversal** to a dedicated effort; keep cancellation-of-unfulfilled (inventory release) working. | Correct reversal accounting needs its own design; a wrong reversal corrupts the ledger. |

---

## 4. Files

**Added (6):** `FeedSalePaymentStatusChanged` event; `FeedSalePaymentReceivedSubscriber`; `UpdateFeedSalePaymentStatusService`; `StorefrontReportController`; the linkage migration; `FulfilmentAndConversionTest`. Plus the sales-report view.

**Modified (additive/backward-compatible):** `OrderService` (inject the two payment-update services; `complete()` + COD settlement + sold-out); `QuoteService` (`convertToOrder()`); `StorefrontOrderConfirmedSubscriber` (write the sale link); `FinanceServiceProvider` (register the feed receipt subscriber); `AdminOrderController` (`complete`, `addNote`); `AdminQuoteController` (`convert`); `StorefrontOrder` / `StorefrontOrderItem` models (new fillable/casts); `routes/web.php`; admin order/quote/dashboard views.

---

## 5. Deployment

```bash
php artisan migrate                  # 1 additive Phase 5 migration (columns only)
php artisan optimize:clear
php artisan finance:check-outbox-wiring   # expect: feed_sale.payment_status_changed → 1 subscriber
```

No new seeder. `view_storefront_reports` already exists (Phase 1). The Chart of Accounts must contain the feed cash + AR accounts the feed receipt subscriber resolves (`feed_sales.default_cash_account_code` 1030, `ar_account_code` 1120) — part of the existing finance config.

**Runtime:** completing a COD order flips the underlying sale(s) to paid, which dispatches the payment-status-changed event(s); the scheduled outbox processor posts the cash receipt on the next tick — same eventual-consistency model as the rest of the module.

---

## 6. Rollback

```bash
php artisan migrate:rollback --step=1     # drops the linkage + completion columns
```

Restore the modified files from the previous commit to fully revert. Unregistering the feed receipt subscriber makes `feed_sale.payment_status_changed` drain as "no subscriber → skipped" (posts nothing). Already-posted sales/receipts are normal finance records and are unaffected.

---

## 7. Verification checklist

- [x] Full suite green: 1133 passed, 0 failures.
- [x] Storefront suite green: 82 passed.
- [x] `finance:check-outbox-wiring` lists `feed_sale.payment_status_changed → 1 subscriber`.
- [ ] Complete a COD feed order → underlying `FeedSale` becomes `paid`; next tick posts Dr Cash / Cr AR; order `completed`, `payment_status=paid`.
- [ ] Complete a COD pig order → `PigSale` paid; listing `sold_out`.
- [ ] Complete a Paystack order → no re-settlement; order `completed`.
- [ ] Accept a quote → **Convert to order** creates a linked `pending_confirmation` order; quote shows `converted`; converting again returns the same order.
- [ ] Add an order note → appears on the timeline and in staff notes.
- [ ] Sales report renders correct status/method/revenue aggregates for a date range.

---

## 8. Next: Phase 6 — Hardening (and the deferred reversal)

Audit coverage, permission tightening, validation/idempotency review, webhook/quote spam hardening (throttle/captcha), UX polish, and deploy/verification docs — plus the **dedicated design + build of post-completion refund/sale reversal** (credit-note revenue reversal + COGS/inventory restore + pig un-delete) called out in §2. Same delivery bar: recon, build, full-suite green, README + delta zip.
