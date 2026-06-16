# Storefront Module — Phase 4: Finance Integration

**Status:** complete and verified.
**Test result:** full application suite green — **1127 tests / 3263 assertions, 0 failures** (storefront now contributes 76; Phase 4 added 6). No operational or finance regression — the one change to a core operational service (`RecordPigSaleService`) is backward-compatible and all existing pig-sale tests still pass.

Builds on Phases 1–3. This README is the deploy/verify runbook for Phase 4 only.

---

## 1. What Phase 4 delivers

Storefront orders now feed the general ledger — **by reusing the existing sale spine, not by posting independently.** A confirmed order dispatches one domain event; a single orchestrator subscriber routes each line into the same `RecordFeedSaleService` / `RecordPigSaleService` write paths that operations already use, so revenue/AR + COGS/inventory post through the same battle-tested subscribers. **The storefront writes nothing to the GL directly — zero drift.**

Concretely:
- **`OrderService::confirm()`** dispatches a new **`StorefrontOrderConfirmed`** domain event *inside its transaction* (transactional outbox — the event rolls back if confirmation fails). Never on order creation.
- **`StorefrontOrderConfirmedSubscriber`** consumes it and routes each line:
  - **feed sold in kg** → `RecordFeedSaleService` with **`channel='storefront'`** → `FeedSaleRevenueSubscriber` (Cr Feed Revenue **4120 storefront**) + `FeedSaleCogsSubscriber` (COGS at WAC `value_delta`).
  - **live_pig / breeding_stock priced per-kg with a weight** → `RecordPigSaleService` with the **agreed storefront price** → auto-invoice (Dr AR / Cr Revenue) + pig COGS; the pig is soft-deleted by the spine as usual.
- **Settlement (D24):** a Paystack-verified order books the underlying sale as **`paid`** (cash sale — no AR); a COD order books **`pending`** (AR now, cash collected at fulfilment in Phase 5). This rides the spine's existing cash-vs-AR logic; no separate receipt call.
- **`RecordPigSaleService`** gained a guarded **explicit-price path** (`price_source='explicit'`) so storefront pig sales use the price the customer agreed/paid instead of being re-priced from `Setting`. Default (operations-recorded) behaviour is unchanged.
- The order's **`finance_posting_status`** is written back: `posted` (all postable lines routed), `skipped` (nothing postable), or `posted` with a `finance_posting_error` listing any deferred lines. A `system` order-event records the outcome on the timeline.

**Production-safe deferral:** lines that cannot map *exactly* onto the kg×rate spine — fixed-price pigs without a weight, feed not sold in kg, and manure/service/piglet-lot lines — are **deferred with a recorded reason, not posted with wrong numbers.** They surface in `finance_posting_error` for follow-up (a manual invoice or a later phase), never as a silent mis-post.

**Out of scope (Phase 5+):** COD cash receipt at collection; cancellation/refund reversal (credit note + COGS/inventory restore); quote→order conversion; finance for the deferred line kinds.

---

## 2. Key decisions (as approved — D21–D26)

| # | Decision | How it's implemented |
|---|----------|----------------------|
| D21 | **Reuse the sale spine**, don't post independently. | Orchestrator subscriber calls `RecordFeedSaleService` / `RecordPigSaleService`; the existing revenue/COGS subscribers do all GL posting. |
| D22 | **Recognise at confirmation.** | Event dispatched from `OrderService::confirm()`, never on creation. |
| D23(a) | **Pig agreed-price path.** | `RecordPigSaleService` honours `price_source='explicit'`; storefront passes the agreed `price_per_kg`/`sale_price`. Settings reprice path untouched for operations. |
| D24 | **Settle Paystack at confirmation.** | Verified Paystack order → underlying sale `payment_status='paid'` (cash sale); COD → `pending` (AR). Spine books cash-vs-AR. |
| D25 | **Outbox event + one subscriber.** | `StorefrontOrderConfirmed` + `StorefrontOrderConfirmedSubscriber`, registered in `FinanceServiceProvider::registerSubscribers()`. |
| D26 | **Forward-only.** | Phase 4 posts forward and writes `finance_posting_status`. Reversal/refund is Phase 5. |

Additional decision recorded during build:
- **D27 — Exact-map-or-defer.** Because both spines are `quantity × rate` (kg-based), only lines that map exactly (per-kg pig with weight; feed in kg) are auto-posted; the rest are deferred with a reason rather than posted with fabricated kg/rate figures. This protects inventory and COGS integrity.

---

## 3. Posting outcomes (what hits the GL)

| Order line | Settlement | GL effect (via existing subscribers) |
|---|---|---|
| Feed (kg), COD | pending | Dr AR 1120 / Cr Feed Revenue 4120 · Dr Cost of Feed Sold / Cr Finished Feed (WAC) |
| Feed (kg), Paystack | paid | Dr Cash/Bank / Cr Feed Revenue 4120 · COGS as above |
| Per-kg pig, COD | pending | Auto-invoice Dr AR / Cr Revenue · pig COGS |
| Per-kg pig, Paystack | paid | Invoice booked as cash-settled · pig COGS |
| Manure / service / lot / fixed pig / non-kg feed | — | **Deferred** (recorded reason; no GL) |

The storefront `order.total` always equals the sum of the routed sale totals for posted lines (line totals are passed through unchanged).

---

## 4. Files

**Added (3):** `app/Domain/Events/Sales/StorefrontOrderConfirmed.php`; `app/Finance/Subscribers/Sales/StorefrontOrderConfirmedSubscriber.php`; `tests/Feature/Storefront/FinanceIntegrationTest.php`.

**Modified (3, additive/backward-compatible):**
- `app/Services/Sales/RecordPigSaleService.php` — guarded `price_source='explicit'` path (default behaviour unchanged).
- `app/Storefront/Services/OrderService.php` — injects `DispatchDomainEvent`; `confirm()` dispatches `StorefrontOrderConfirmed` inside its transaction.
- `app/Providers/FinanceServiceProvider.php` — registers the new subscriber.

No new migrations — the `finance_posting_status` / `finance_posting_error` columns shipped on `storefront_orders` back in Phase 3.

---

## 5. Deployment

```bash
php artisan optimize:clear
# Ensure the outbox processor is scheduled (already part of the finance module):
#   ProcessPendingDomainEventsJob runs every minute via the scheduler.
php artisan finance:check-outbox-wiring   # should list: storefront_order.confirmed → 1 subscriber(s)
```

No migration, no seeder. The Chart of Accounts must already contain the storefront feed-revenue account (**4120**) and the standard AR / revenue / COGS / inventory accounts the feed/pig spine resolves — these are part of the existing finance module, not new here.

**Processing flow at runtime:** confirm → `storefront_order.confirmed` (pending) → next scheduler tick: orchestrator creates the underlying `FeedSale`/`PigSale` (which dispatch their own events) → following tick: feed/pig revenue + COGS subscribers post the GL. Eventual-consistency, identical to the rest of the system.

---

## 6. Rollback

Remove the subscriber registration in `FinanceServiceProvider` (the event then drains as "no subscriber → skipped", posting nothing), or restore the three modified files from the previous commit. No schema or data changes to revert. Already-posted underlying `FeedSale`/`PigSale` rows are normal finance records and are unaffected.

---

## 7. Verification checklist

- [x] Full suite green: 1127 passed, 0 failures (existing pig-sale tests unaffected by the explicit-price change).
- [x] Storefront suite green: 76 passed.
- [x] `finance:check-outbox-wiring` lists `storefront_order.confirmed → 1 subscriber`.
- [ ] Confirm a feed-in-kg order → a `FeedSale` with `channel='storefront'` appears; after the next scheduler tick, Feed Revenue 4120 + COGS post; `order.finance_posting_status='posted'`.
- [ ] Confirm a per-kg pig order → a `PigSale` at the **agreed** price (not the settings rate); pig soft-deleted; invoice posts.
- [ ] Paystack order books the sale as `paid` (cash); COD books `pending` (AR).
- [ ] A manure/non-kg line leaves `finance_posting_status='skipped'` with a reason and posts nothing.
- [ ] Re-running the orchestrator on the same order does not double-post (idempotent).

---

## 8. Next: Phase 5 — Fulfilment & sales ops

Pickup/delivery scheduling and completion (→ listing `sold_out`; **COD cash receipt** via the spine's payment-status-changed path); **cancellation/refund reversal** (credit note + COGS/inventory restore) — including reversing a Phase-4 posting; quote→order conversion (`orders.quote_id` ready); order notes/comms; reports & dashboards. Same delivery bar: recon, build, full-suite green, README + delta zip.
