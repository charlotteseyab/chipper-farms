# Storefront pricing and purchase flow — implementation report

**Branch:** `remediation/finance-storefront-final-blockers`
**Date:** 13 August 2026

---

## 1. What this changed, in one paragraph

A pig listing used to be one price and a quantity box. That is right for a bag
of feed and wrong for an animal. Staff publishing a listing were asked to choose
a sale mode, a pricing basis, a price per kilo, a unit price and a currency —
database concepts, none of which a farmer thinks in — and buyers got a single
number for a pool that might hold a 78 kg pig and a 124 kg one.

The push page now asks one question: **which stock**. Everything else is derived
from the answer and from the rates the farm already configured at Settings →
Setup. Buyers get the two purchases a farm actually makes: **live**, where they
pick specific animals and pay for the weight those animals carry, and
**carcass**, where they ask for kilograms and the farm decides which animals
meet it. Managers get a per-line view of what each order is waiting for. And
every real sale still goes through `RecordPigSaleService` — the storefront has
not learned a second way to sell a pig.

---

## 2. The seven slices

| # | Slice | Commit |
|---|-------|--------|
| 1 | Latest-weight pricing source, setup rates, listing snapshots, `source_category` | `214a36d` |
| 2 | Push page: four source categories, generic fields removed, stock selector | `77bc52d` |
| 3 | Buying live or by carcass weight; per-animal cart lines | `0c7121e` |
| 4 | Three kinds of hold; order lines carry the whole calculation | `ea21145` |
| 5 | Per-line fulfilment state and approval | `89e6f5a` |
| 6 | Piglets materialise at the point of sale | `e0a2d77` |
| 7 | Gate sales cannot break a promise made in kilograms | `43f5547` |
| — | Four defects the concurrency suite found | `cba4863` |
| — | Browser verification | `8f6a288` |

---

## 3. The decisions that shaped it

### 3.1 Weight comes from the weight records, not the intake cache

`pigs_management.current_weight_kg_cache` looks like the obvious source and is
the wrong one. It is written at intake and **nothing updates it when an animal
is weighed**, so on any pig weighed since arrival it is stale — sometimes by
months. The push page was reading it, which meant listings were priced off an
intake figure.

Pricing now reads `latestWeight()` and falls back to the cache only for an
animal that has never been weighed. An animal with no weight at all is *named*
rather than priced at zero, on the staff picker and in the shop.

### 3.2 One animal is one cart line

Every total in the shop is `unit price × quantity`. Four animals of different
weights on one line would have to either round the money or quote a weight none
of them has. So each chosen animal becomes its own line at its own price.

That required the cart's unique index to be widened — and **widened, not
dropped**. `line_key` is `'0'` for an ordinary line and the pool row id for an
animal, so the old one-line-per-listing rule survives intact and "one line per
animal" is added on top. A `NULL` discriminator would not do: both MariaDB and
SQLite treat NULLs as distinct in a unique index, which would have switched the
old guarantee off entirely.

### 3.3 Kilograms are never counted as head

A carcass line's `quantity` is 1 — one request — and its weight lives in
`requested_kg`, checked against carcass capacity in kilograms. Letting a 400 kg
request consume "400" of a column that counts animals is how a shop sells four
hundred pigs it does not have.

The two units are counted apart everywhere: separate hold types, separate
availability checks, separate progress on the manager's screen. They are joined
in exactly one place — see §4.1, which is where the most serious defect was.

### 3.4 The legacy price column is derived upwards

With no price field on the push page, a stock-backed listing's `fixed_price` is
derived from the **dearest** animal in the pool, not the average. A pooled
listing can deliver any animal in it; of the two ways to be wrong, overcharging
is visible and refundable, while undercharging sells the heaviest pig at the
lightest one's price and nobody notices until the margin is gone. The real money
for these listings is per-animal, computed when the animals are chosen; this
column is the floor under any path not yet moved onto it.

### 3.5 Piglets become animals at the moment they are sold

A farm records piglets as counts. `RecordPigSaleService` sells a *pig*. That left
two options: give piglet sales their own ledger — the parallel sales system this
work exists to prevent — or create the animal records at the point of sale and
sell them normally. This is the second.

The records are honest about what they are: `acquisition_source` marks them as
point-of-sale piglets and names the order, the ear tag is derived from that order
rather than invented to look like a farm tag, and gender is `unknown` because
nobody sexed them.

---

## 4. What the tests found that review did not

### 4.1 Live and carcass buyers could oversell each other (SPP-013)

The most serious defect in this work, and only the concurrency suite could find
it. Each check policed its own unit: the head check counted animals, the kilogram
check counted meat, and neither knew what the other had promised. Two buyers took
100 kg each from a 200 kg pool while a third walked off with one of the two
animals. **Every individual check said yes**, and the farm owed 200 kg with 100
left to cut.

Carcass capacity is now what the pool can deliver *after* the animals other
buyers have named are taken out of it, including animals the same order is
naming.

### 4.2 Two deadlocks (SPP-014, SPP-015)

Adding a live line locked pool rows and then, through a foreign key, needed the
listing; checkout locks the listing and then the pool rows. Opposite order, two
buyers, deadlock — one of them saw a database error instead of a basket. The
cart now takes the listing lock first, explicitly.

Separately, `carcassKgAvailable()` called `refreshListingSnapshot()`, which
*saves*. A read that writes takes an exclusive lock inside a transaction that is
also inserting a row referencing that listing. There is now a pure
`carcassCapacityKg()` that computes the same number and saves nothing.

### 4.3 A column width SQLite does not enforce (SPP-017)

`acquisition_source` is 50 characters. Materialised piglets wrote a sentence into
it. Every feature test passed; MariaDB refused the insert and the piglet sale
failed outright. The feature test now asserts the length too.

### 4.4 A same-day re-weigh could be ignored (SPP-026)

`latestWeight()` ordered by `weigh_date` alone, which leaves two weights recorded
on the same day in an undefined order. SQLite returned the later row and MariaDB
the earlier one — so an animal re-weighed after a misread scale was priced off
the misreading, on the engine production uses. Only running the same test on both
engines could have found it. The relation now tie-breaks on `id` descending,
because a second weight on the same day *is* the correction.

### 4.5 A test that passed whether the rule existed or not (SPP-021)

The test asserting a live line's quantity cannot be changed used a quantity the
ordinary availability check happened to refuse. It passed with the rule removed.
It now asks for a quantity that *is* available, so only the rule can say no.

### 4.6 A data-flow gap that would have shipped (SPP-011)

`source_category` was set on the listing and read on the order, with nothing
carrying it across. Piglet materialisation passed every test I wrote and would
**never have fired in production** — a piglet lot is indistinguishable from a bag
of feed at checkout without it. Found by removing a `forceFill` from a test
fixture and asking whether the value arrived on its own. It did not.

---

## 5. Verification

| Gate | Result |
|------|--------|
| `php artisan test` | 1931 passed, 13 skipped |
| MariaDB suite | 1944 passed, 7 skipped |
| Concurrency suite | 25 passed |
| Browser suite (Dusk) | 13 passed |
| `composer validate --no-check-publish` | valid |
| `composer audit` | no advisories |
| `npm audit` | 0 vulnerabilities |
| `npm run build` | built |
| `php artisan optimize` | cached cleanly |
| `git diff --check` | clean |
| Migration reversibility | 215 up / 214 down / 214 up — PASS |

New tests: 94 feature, 7 browser, 6 concurrency.

### 5.1 A note on the MariaDB gate

The first run of this gate failed with one test. That failure was SPP-026 — a
real defect in how the latest weight is resolved, not a flaky test — and is
described in §4.4. The gate passes after the fix.

---

## 6. Open by design

Two things are deliberately not implemented, and a farm should know both before
using this.

**Piglets are priced per head by hand.** Setup pricing is per kilogram and
piglets have no weight records, so a piglet listing keeps a typed price. If a
farm wants piglets priced by weight, they need individual weight records first,
at which point they can be pooled like any other stock.

**No dressing-out yield is applied.** Carcass capacity is the *live* weight of
the animals on offer. Dressing-out percentage is farm-specific and inventing one
would misstate what the farm can deliver. A farm selling 150 kg of carcass weight
is currently promising 150 kg of live animal, which is conservative — it
over-reserves rather than under-delivers.

---

## 7. Status

**STOREFRONT PRICING AND PURCHASE FLOW COMPLETE — READY FOR HUMAN SMOKE TEST**

Every gate in §5 has been run and passed. The two items in §6 are open by design,
not unfinished work, and a farm should read them before going live.
