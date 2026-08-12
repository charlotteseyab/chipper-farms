# Chipper Farms — storefront pig-sale flow

## Document control

| Field | Value |
| --- | --- |
| Date | 2026-08-12 |
| Branch | `staging` |
| HEAD at start | `3ed95b2` |
| Commits | `e10100c`, `3de1bee`, `bf2382b`, `a4b55ff`, `199998f`, `d8a20d5`, `91f5f5c` |
| Scope | Selling Standard Stock through the storefront, with `/sales` remaining the only sales ledger |

---

## 0. What was actually missing

The brief describes this as missing integration. It is not: pig-backed listings
already sold through the storefront. What existed was **one listing = one
animal**, named by `storefront_listings.source_type` / `source_id`, with
`StorefrontOrderConfirmedSubscriber::routePigLine()` selling that single pig on
confirmation.

What did not exist is the shape a farm actually wants: publish *"market-ready
live pigs — 5 head"*, let a customer order three, and have staff choose **which
three** at approval. That is a many-to-many between a listing and the animals it
may draw from, and it needed new tables, services, screens and a change to how
confirmation works.

Both shapes now exist. `source_type`/`source_id` is untouched, so every existing
listing, order and finance subscriber behaves exactly as before.

---

## 1. Data model

Two additive tables. No column was dropped or repurposed.

### `storefront_listing_pigs` — the pool

Which Standard Stock a listing may draw from. Status ladder:
`available → held → allocated → sold`, or `removed` at any point.

### `storefront_order_item_pig_allocations` — what an approver committed

One row per animal per order line. Separate from the pool because the two have
different lifetimes: a pool row outlives many orders, and an allocation is
released when an order is cancelled while the pool row returns to `available`.

**Why not `storefront_order_items.posted_sale_id`:** it holds one sale id, and a
line for three pigs becomes three `pig_sales` rows.

### The uniqueness that is not an index

`(listing_id, pig_id)` and `(order_item_id, pig_id)` are real unique
constraints. The stronger invariant — *a pig may be ACTIVE in at most one pool,
and have at most one active allocation* — cannot be a partial unique index on
MySQL/MariaDB, so it is enforced under `lockForUpdate()` inside a transaction
and **proved by the concurrency suite rather than asserted by the schema**.

Migrations round-trip on MariaDB 11.4.2: 212 up, 211 to zero, 211 back up.

---

## 2. Services

### `StorefrontPigPoolService`

Pool membership and derived availability. Never creates a sale.

`quantity_available` on a pooled listing is **recomputed from the pool, never
typed in**. A farm that sells a pig at the gate has changed what the shop can
offer, and the number follows the animals rather than the other way round.

### `StorefrontPigAllocationService`

Validates a selection, commits animals to a line, and — at approval — calls
`RecordPigSaleService::execute()` **once per animal**. It does not write a pig
sale itself. That service soft-deletes the pig, audits, and dispatches
`PigSaleRecorded`, so finance posts exactly as it does for a farm-gate sale.

**Idempotent by construction:** only allocations still in an active state are
sold, and each becomes `sold` in the same transaction as its sale. A second call
finds nothing active and creates nothing — no separate idempotency key needed.

**Money is decimal strings.** A line's total and tax are split across its
animals with the residual on the last, so three head out of GHS 1,000 come to
333.33 + 333.33 + 333.34 and the sales sum to the line exactly.

---

## 3. Confirmation and de-duplication

Confirmation **refuses** a pooled order until the exact animals are chosen,
naming how many of how many. Refusing rather than deferring is deliberate:
confirmation is when stock moves and money is owed, so confirming an order
nobody can fulfil makes a promise the farm cannot keep.

The chosen animals are sold **inside the confirmation transaction**, so the sales
and the confirmation stand or fall together.

`StorefrontOrderConfirmedSubscriber` returns `null` for a line whose animals were
sold at approval — HANDLED, not deferred. See §7.2 for why that distinction
needed a stronger test than the obvious one.

Cancelling releases chosen-but-unsold animals. Allocations already `sold` are
untouched: cancelling an order does not un-sell a pig.

---

## 4. Direct sale from `/sales`

`RecordPigSaleService` now tells the pool about **every** sale, inside its own
transaction — so the gate form, an import and a console command all keep the
shop honest. The pool service stands aside when the animal is already allocated,
because that sale is an approval rather than a direct one.

The sale form **warns** when the shop is advertising the animal, and **refuses**
when a customer has already been promised it, naming the order.

---

## 5. UI

| Screen | What it does |
| --- | --- |
| Push to storefront | Standard Stock picker with filter and live count; quantity derived from what is ticked |
| Order page | Per-line panel: required count, chosen count, candidate animals with ear tag, breed, sex, age, weight |
| Listing page | The pool, ordered available → allocated → sold → removed, each with its reason and sale number |
| Direct sale form | The warning and the refusal above |

Permissions are on the **routes**, not the panels. A viewer sees what an order
is waiting for and gets 403 posting the form — tested explicitly, because a
hidden button is not a control.

---

## 6. Tests and results

| Suite | Result |
| --- | --- |
| Default (SQLite) | **1838 tests, 5620 assertions**, 0 failures |
| MariaDB 11.4.2 | **1838 tests, 5630 assertions**, 0 failures |
| Concurrency (MariaDB 11.4.2) | **19 tests, 324 assertions**, 0 failures |
| Browser (Chromium 126) | **5 tests, 16 assertions**, 0 failures |
| `npm run build` | **green** — 57 modules, built in 14.43s |
| Migration reversibility | 212 up / 211 down / 211 up, 0 failures |

New feature tests: 47 across seven files, covering all twenty numbered
requirements in the brief.

---

## 7. What went wrong while building it

### 7.1 A double sale, found only by real processes

**Racing a farm-gate sale against a storefront approval produced TWO `pig_sales`
rows for ONE animal.** Both paths succeeded. The farm would have sold one pig
and billed for two.

`RecordPigSaleService` had no lock and no already-sold check — two callers could
each read an unsold pig and both write. Fixed in that service rather than the
callers, because it is the single place a pig sale is created, so one guard
covers every path that will ever exist.

Every sequential test passed throughout. This is exactly what the concurrency
requirement was for.

### 7.2 A de-duplication test that tested nothing

The first version asserted "no second sale" and **passed with the guard
removed**. A pooled line has no `source_id`, so the unguarded subscriber
*defers* rather than re-selling — the sale count is unchanged either way.

The real difference is the order's posting state: `posted` with the guard,
`skipped — pig_awaiting_pig_selection` without. The line sold correctly but was
reported as an unresolved problem, the false alarm Phase 15 removed from that
panel. The test now asserts `posted`.

### 7.3 A sale payload I invented instead of copying

My first `saleDataFor()` added a `sale_channel` key the service does not accept,
divided money in **binary floating point** against the standing bcmath rule, and
dropped the `tax_profile_id`/`tax_snapshot` fields IMP-091 added. Replaced with
the shape `routePigLine()` already proves works. Left alone, storefront pool
sales would have posted the wrong tax.

### 7.4 Eligibility that blocked its own pool

Teaching `PigListingEligibilityService` about pools made every candidate "already
on the shop" — because it was in the pool being sold from. The check now takes a
listing to ignore, which is the difference between *may this pig be added to a
listing* and *may it be sold through the one it is in*.

### 7.5 Two defects only a rendering test could find

- `eligibleStandardPigsForListing()` used `$listing` in a closure without
  capturing it. Nothing called the method until a page did, so it sat through
  two green commits and 500'd the moment the picker rendered.
- `Sold@if(...)` is not a Blade directive — directives need a word boundary — so
  the stray `@endif` broke the if/elseif chain and 500'd the listing page.

### 7.6 A claim I had to withdraw

I wrote a "deterministic" concurrency control and documented it as isolating the
new `lockForUpdate()`. Measuring it showed otherwise: removing that line leaves
the test green, because the soft-delete on the same row blocks anyway. The
docblock now says what was measured. **The discriminating control for the lock is
probabilistic — 2 failures in 3 runs — the same limitation as IMP-084.**

---

## 8. Remaining risks

| Risk | Detail |
| --- | --- |
| **`local_id` collides across farms** (pre-existing) | `pigs_management.local_id` is generated as an organisation-scoped `max()+1`, but `pigs_local_id_unique` is a **global** unique index on that column alone. The moment a second farm exists, pig creation fails. Not this feature's defect and **not fixed here** — it needs a migration and a renumbering decision. Fixtures set `local_id` explicitly to work around it |
| Lock control is probabilistic | §7.6. The outcome tests are deterministic; the isolation of the lock line is not |
| Weightless pool sales | A pooled line is priced per head, so `sale_weight`/`price_per_kg` are null unless the line carries a weight. Correct for fixed-price sales, but pig sales reports that assume a weight will show blanks for these |
| Pool size and the picker | The selector renders every eligible animal with no pagination. Fine for tens; a farm with hundreds of Standard Stock will want search server-side |
| §61 external certification | Unchanged and still unmet — Ghana tax rates need accountant/GRA confirmation before go-live |

---

## 9. Go / no-go for this flow

Every item in the brief's Definition of Done is met: pig-backed listings from
multiple Standard Stock animals, customer orders a quantity, approval requires
exact selection, approval creates real `PigSale` records, sold animals leave
Standard Stock, `/sales` is the single source of truth, direct sale syncs the
shop, double sale prevented, duplicate confirmation does not duplicate sales,
the finance subscriber does not duplicate, permissions enforced on routes,
browser flow verified, and all five gates green.

**STOREFRONT PIG-SALE FLOW COMPLETE — READY FOR HUMAN SMOKE TEST**

Two things to carry into that smoke test: this flow does not fix the pre-existing
`local_id` collision, so **do not exercise it with a second organisation** until
that is repaired; and the queue worker must be running, or approval will create
the sales while finance posting sits unprocessed.
