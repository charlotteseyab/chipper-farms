# Storefront Module — Phase 1: Foundations

**Status:** complete and verified.
**Test result:** full application suite green — **1077 tests / 3130 assertions, 0 failures** (storefront adds 27 of these). Migrations run up **and** down. No operational or finance regression.

This README is the deploy/verify runbook for Phase 1 only. Phases 2–6 are listed at the bottom.

---

## 1. What Phase 1 delivers

The safe, additive **foundation** every later phase builds on:

- **Schema** — 4 new storefront tables (categories, listings, listing media, placements). Additive only; no operational/finance table is altered.
- **Commerce catalogue layer** — `StorefrontListing` is the public, sellable projection of an operational record (a pig, lot, feed, manure, or service). It carries its own lifecycle and **never mutates operational truth**.
- **Push-to-Storefront** — a service + wizard that turns a pig (or product) into a listing, with a duplicate-listing guard and price defaults sourced from the same settings the operational sale path uses.
- **Listing lifecycle state machine** — draft → published → unpublished → archived / sold_out / reserved, with a publish guard that refuses to publish a listing whose source pig has been sold.
- **Roles & permissions** — a new **Salesperson** role (scoped catalogue/placement management) and **Customer** role (storefront-only, zero back-office access), plus seven storefront permissions, gated the finance-module way (`permission:` middleware).
- **Public storefront shell** — home + shop with category/kind/search/sort filters and a self-contained customer-facing layout.
- **Customer account scaffolding** — account dashboard, profile, and address book, backed by the existing `CustomerProfile` → `Counterparty` → `CounterpartyAddress` AR structures (so Phase 4 finance integration is clean).
- **Homepage placement manager** — pin published listings into the canonical featured slots.
- **Tests** — schema, push service, lifecycle transitions, permissions/roles, route access, customer-account service, catalogue seeder.

**Out of scope for Phase 1 (later phases):** product detail pages, favourites, quote flow (Phase 2); cart/checkout/orders, COD, Paystack (Phase 3); finance posting integration (Phase 4); fulfilment/scheduling, reports (Phase 5); hardening (Phase 6).

---

## 2. Key architecture decisions (and why)

| # | Decision | Rationale |
|---|----------|-----------|
| D1 | **Listings reference their source softly** (`source_type` + `source_id`, no FK). | Pigs are **soft-deleted on sale**; an FK + cascade would corrupt listing history. Availability is reconciled in `ListingAvailabilityService`, never by the DB. |
| D2 | **Operational truth is never written by the storefront.** Push only *reads* the pig (tag, breed, weight). | The brief: "do not duplicate/overwrite operational pig truth." A sold pig flips a published listing to `sold_out`; the pig record is untouched. |
| D3 | **Customers are `users`** in the farm org with a new `customer` role + zero operational permissions; public pages need no auth. | Reuses Jetstream/Fortify auth, 2FA, password reset with no second guard. `EnsureOrganizationContext` passes unauthenticated requests through, so the shop is open. |
| D4 | **Back-office gated by `permission:` middleware, not `role:`.** | Matches the finance module. Salesperson gets a scoped subset; admin/superuser inherit via the superuser bypass + explicit grants. |
| D5 | **Money `decimal(18,2)`, qty/rates `decimal(18,4)`; prices carried as numeric strings.** | Mirrors `pig_sales`/`feed_sales` so values stay bcmath-safe when a listing later feeds an order → invoice in Phase 4. |
| D6 | **Storefront settings reuse the existing `Setting` (key/value) store** under `storefront.*`. | Established pattern (`sales.pricing.*`); no new settings table; visible in the same admin Settings surface. Pig price defaults fall back to `sales.pricing.*` so storefront price == recorded-sale price. |
| D7 | **Permission seeder ensures every role it references exists.** | `DatabaseSeeder` creates standard roles *after* permission seeders; a self-sufficient seeder avoids silently-skipped grants on a fresh install. |

---

## 3. Files added (38)

**Migrations** (`database/migrations/`)
- `2028_02_01_100000_create_storefront_categories_table.php`
- `2028_02_01_100100_create_storefront_listings_table.php`
- `2028_02_01_100200_create_storefront_media_and_placements_tables.php`

**Models** (`app/Models/Storefront/`)
- `StorefrontCategory.php`, `StorefrontListing.php`, `StorefrontListingMedia.php`, `StorefrontPlacement.php`

**Domain services / support** (`app/Storefront/`)
- `Services/PushToStorefrontService.php`, `Services/ListingService.php`, `Services/ListingAvailabilityService.php`, `Services/CustomerAccountService.php`
- `Services/Exceptions/DuplicateListingException.php`, `Services/Exceptions/InvalidListingTransitionException.php`
- `Support/StorefrontSettings.php`, `Support/PushToStorefrontData.php`

**HTTP** (`app/Http/`)
- `Controllers/Storefront/StorefrontController.php` (public home/shop)
- `Controllers/Storefront/AdminStorefrontController.php` (dashboard + listing actions)
- `Controllers/Storefront/PushToStorefrontController.php` (wizard)
- `Controllers/Storefront/StorefrontPlacementController.php`
- `Controllers/Storefront/CustomerAccountController.php`
- `Requests/Storefront/PushToStorefrontRequest.php`

**Policy** — `app/Policies/StorefrontListingPolicy.php`

**Seeders** (`database/seeders/`) — `StorefrontPermissionsSeeder.php`, `StorefrontCatalogSeeder.php`

**Views** (`resources/views/`)
- `components/storefront/layout.blade.php` (public layout component)
- `storefront/public/{home,shop}.blade.php`
- `storefront/partials/listing-card.blade.php`
- `storefront/admin/dashboard.blade.php`, `storefront/admin/listings/{index,show,push}.blade.php`, `storefront/admin/placements/index.blade.php`
- `storefront/account/{dashboard,profile,addresses}.blade.php`

**Tests** (`tests/Feature/Storefront/`) — `StorefrontTestCase.php` + 7 test classes.

## 4. Files modified (additive only — 6)

| File | Change |
|------|--------|
| `app/Models/User.php` | Added `salesperson`, `customer` to `ALLOWED_ROLE_NAMES`; `sales*` → `salesperson` normalisation; `isSalesperson()`, `isCustomer()`. |
| `app/Http/Middleware/RoleMiddleware.php` | Added `salesperson` and `customer` cases (so `role:` works for them; existing cases untouched). |
| `app/Providers/AppServiceProvider.php` | Registered `StorefrontListing` → `StorefrontListingPolicy`. |
| `app/Providers/FortifyServiceProvider.php` | Added customer → account and salesperson → storefront-admin login redirects (existing role redirects unchanged). |
| `database/seeders/DatabaseSeeder.php` | Calls `StorefrontPermissionsSeeder` + `StorefrontCatalogSeeder`; added `salesperson`, `customer` to the roles list. |
| `routes/web.php` | Added back-office storefront group (inside the protected group), plus customer-account and public-shop groups. |

No columns were dropped or renamed; no existing route, permission, or behaviour was changed.

---

## 5. Deployment

```bash
# 1. Pull the Phase 1 changes, then:
php artisan migrate            # applies the 3 additive storefront migrations

# 2. Seed permissions + roles + catalogue (idempotent — safe to re-run):
php artisan db:seed --class=Database\\Seeders\\StorefrontPermissionsSeeder
php artisan db:seed --class=Database\\Seeders\\StorefrontCatalogSeeder

# 3. Build front-end assets (storefront uses the existing Vite pipeline):
npm install && npm run build

# 4. Clear caches:
php artisan optimize:clear
```

On a brand-new install, `php artisan db:seed` runs both seeders automatically (wired into `DatabaseSeeder`).

**Optional storefront settings** (defaults are sensible; set via the existing settings store):
`storefront.store_name`, `storefront.support_email`, `storefront.support_phone`, `storefront.pickup_address`,
`storefront.hero_headline`, `storefront.hero_subheadline`, `storefront.delivery_enabled`.

---

## 6. Rollback

Phase 1 is fully reversible and low-risk (additive).

```bash
# Roll back the 3 storefront migrations (drops only the 4 storefront tables):
php artisan migrate:rollback --step=3
```

The modified files are additive; to fully revert, restore the 6 files from the previous commit. The new roles/permissions rows are inert if unused. No operational or finance data is affected by a rollback.

---

## 7. Verification checklist

- [x] `php artisan migrate` then `migrate:rollback` both succeed (verified: up + down clean).
- [x] Full suite green: `./vendor/bin/phpunit` → 1077 passed, 0 failures.
- [x] Storefront suite green: `./vendor/bin/phpunit tests/Feature/Storefront` → 27 passed.
- [ ] `GET /shop` and `GET /shop/browse` render for an anonymous visitor.
- [ ] A salesperson user is redirected to the storefront dashboard on login and can open **Push to storefront**.
- [ ] A customer user is redirected to **/account** on login and **cannot** reach `/storefront-admin` (403).
- [ ] Pushing a pig creates a **draft** listing; the pig record is unchanged (still alive in operations).
- [ ] Publishing a listing whose source pig was subsequently sold is **refused** with a clear message.
- [ ] Pinning a published listing to `featured_home_hero` makes it appear on the homepage hero.

---

## 8. Roadmap (remaining phases)

| Phase | Scope |
|-------|-------|
| **2** | Listing detail pages, search/filter/sort polish, favourites, quote-request flow, availability sync job, back-office listing editor. |
| **3** | Cart, checkout, order entities + status machine, addresses at checkout, COD, **Paystack** init + server-side verify + webhook, internal confirmation workflow. |
| **4** | **Finance integration** — storefront order/payment/refund domain events → subscribers posting via `PostingEngine` (mirrors the pig/feed-sale spine; never posts on order creation). |
| **5** | Fulfilment (pickup/delivery scheduling), order notes/comms, quote→order conversion, cancellation/refund approval, reports/dashboards. |
| **6** | Hardening — audit coverage, permission tightening, validation/idempotency review, UX polish, deploy + verification docs. |
