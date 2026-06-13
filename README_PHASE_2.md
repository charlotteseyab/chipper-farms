# Storefront Module — Phase 2: Product / Listing Commerce Layer

**Status:** complete and verified.
**Test result:** full application suite green — **1099 tests / 3190 assertions, 0 failures** (storefront now contributes 48; Phase 2 added 21). Migrations run up **and** down. No operational or finance regression.

Builds directly on Phase 1. This README is the deploy/verify runbook for Phase 2 only.

---

## 1. What Phase 2 delivers

- **Public listing detail pages** — rich, source-aware. Pig-backed listings surface traceability + husbandry (ear tag, breed, sex, class, DOB/age, weight + weigh date, location, health-record count); product listings surface SKU/pack size. A media gallery, fulfilment badges, and CTAs (Request a quote always; Buy now stubbed for Phase 3; Save for signed-in customers). View counts increment atomically.
- **Saved items / favourites** — idempotent toggle backed by a unique `(user_id, listing_id)` index; a "Saved items" tab in the account area.
- **Quote-request flow** — public, works for guests (contact details) and signed-in customers (auto-linked to their AR counterparty). From a listing or as a bulk multi-line request. Creates `storefront_quotes` + items, notifies sales staff via the existing notifications panel, and gives the customer a "My quotes" tab. Back-office workspace to triage → price → accept/decline with a guarded status machine.
- **Listing availability sync** — `storefront:sync-availability` command (scheduled hourly via `StorefrontServiceProvider`) demotes any published listing whose operational source pig was sold through the non-storefront path. It only ever demotes, never publishes, and never touches operational data.
- **Back-office listing editor + media management** — edit content/pricing/fulfilment (the source link, kind and audit columns are deliberately non-editable); upload images or add image URLs; set primary; remove (with automatic primary promotion).
- **Search/filter/sort polish** — added price-range and delivery filters to the shop; listing cards now link through to detail pages.
- **Tests** — favourites, quote service + transitions, availability sync (sold pig → demoted), listing editor + media promotion, detail-page access/visibility/view-count, quote request validation, back-office access control.

**Out of scope for Phase 2 (later phases):** cart/checkout/orders, COD, Paystack (Phase 3); finance posting integration (Phase 4); fulfilment scheduling, quote→order conversion, reports (Phase 5); hardening (Phase 6).

---

## 2. Key decisions (Phase 2)

| # | Decision | Rationale |
|---|----------|-----------|
| D8 | **Quotes post nothing to finance.** They are a pre-sales artifact with their own status machine; conversion to an order (which does feed finance) is Phase 5. | Keeps the finance boundary clean — nothing financial happens until there's an actual order. |
| D9 | **Guest quotes carry contact details; customer quotes link the counterparty** (via `CustomerAccountService`). | A guest can request a quote frictionlessly; an authenticated customer's quote is already AR-linked, so Phase 5 conversion → invoice is trivial. |
| D10 | **Availability sync only demotes, runs on a schedule, and reads operational data read-only.** | The storefront never hears about an operational (non-storefront) sale directly; a periodic sweep is the safe, non-invasive way to keep the public shop honest. Operational truth is never written. |
| D11 | **The listing editor restricts editable fields** (source link / kind / audit columns are immutable). | Re-sourcing a listing must go through the push wizard so provenance stays intact; editing is for presentation/commerce only. |
| D12 | **Media accepts uploads *or* external URLs; exactly one primary, auto-promoted on removal.** | Real production media handling without forcing a storage migration; URL support covers video/CDN. |
| D13 | **Blade: no inline-nested `@if … @if … @endif … @endif` on one line.** | Blade's regex directive parser mis-nests these (produces a stray `endif`); multi-line conditionals or a computed `@php` variable are used instead. (Caught and fixed during Phase 2 testing.) |

---

## 3. Schema (additive — 3 new tables)

- `storefront_favourites` — `(user_id, listing_id)` unique; FK → listings (cascade).
- `storefront_quotes` — request header + triage/pricing + nullable `counterparty_id`/`customer_user_id`; `quoted_total` decimal(18,2); soft deletes.
- `storefront_quote_items` — lines; soft listing link; `quantity`/`target_unit_price` decimal(18,4); FK → quotes (cascade).

No operational/finance table is touched. Migrations are reversible (verified up + down).

---

## 4. Files

**Added (33):** 2 migrations; 3 models (`StorefrontFavourite`, `StorefrontQuote`, `StorefrontQuoteItem`); 7 services/support (`FavouriteService`, `QuoteService`, `ListingAvailabilitySyncService`, `ListingEditorService`, `InvalidQuoteTransitionException`, `QuoteRequestData`, `ListingDetailPresenter`); the `SyncListingAvailabilityCommand`; `StorefrontServiceProvider`; `StorefrontQuoteRequestedNotification`; 4 controllers + 2 form requests; `StorefrontQuotePermissionsSeeder`; 8 views; 5 tests.

**Modified (additive — 12):**
`StorefrontListing` (favourites relation), `StorefrontController` (detail + filters), `CustomerAccountController` (saved/quotes), `bootstrap/providers.php` (provider), `DatabaseSeeder` (quote-perms seeder), `routes/web.php` (Phase 2 routes), and 5 Phase-1 views/tests (card→detail link, dashboard links, edit link, test helpers). `QuoteRequestRequest` strips blank bulk rows before validation.

---

## 5. Deployment

```bash
php artisan migrate                       # 2 additive Phase 2 migrations
php artisan db:seed --class=Database\\Seeders\\StorefrontQuotePermissionsSeeder
npm install && npm run build              # detail/quote/editor views
php artisan optimize:clear
```

**Scheduling:** ensure Laravel's scheduler runs (`* * * * * php artisan schedule:run`). `storefront:sync-availability` is registered hourly. Run on demand any time with `php artisan storefront:sync-availability [--organization=ID]`.

New permissions: `view_storefront_quotes`, `manage_storefront_quotes`, `manage_storefront_media` (granted to admin/superuser/salesperson; view also to manager/accountant).

---

## 6. Rollback

```bash
php artisan migrate:rollback --step=2     # drops favourites + quotes/quote_items only
```

Modified files are additive; restore the 12 from the previous commit to fully revert. New permission rows are inert if unused. No operational/finance data affected.

---

## 7. Verification checklist

- [x] `migrate` + `migrate:rollback` succeed (up + down verified).
- [x] Full suite green: 1099 passed, 0 failures.
- [x] Storefront suite green: 48 passed.
- [ ] A published listing's detail page renders with pig traceability/husbandry; a draft listing 404s publicly.
- [ ] A guest can submit a quote from `/shop/quote`; a salesperson sees a notification and the quote in the back-office workspace.
- [ ] Pricing a quote moves it to `quoted`; accept/decline behave; illegal transitions are blocked.
- [ ] A signed-in customer can Save a listing and see it under **Saved items**; their quote appears under **My quotes**.
- [ ] Selling a listing's pig through operations, then running `storefront:sync-availability`, flips the listing to `sold_out`.
- [ ] The listing editor saves content/price changes and adds/sets-primary/removes media.

---

## 8. Next: Phase 3 — Orders & Checkout

Cart, checkout, order entities + status machine, addresses at checkout, COD, **Paystack** (initialise → server-side verify → webhook signature validation), and the internal-confirmation workflow (no auto-accept on payment). Same delivery bar: recon, build, full-suite green, README + delta zip.
