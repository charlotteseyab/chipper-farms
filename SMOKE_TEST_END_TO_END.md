# Chipper Farms — quick end-to-end smoke test

**Time:** ~25 minutes. **Currency:** GHS.

One pass from a pig on the farm, through the shop, to the books — plus the
handful of checks that catch the defects this system has actually had.

This is the **short** test. `FINANCE_SMOKE_TEST(1).md` is the full 574-line
finance walkthrough; use that when you want depth on the ledger. Use this one
before a release, or after any change to navigation, permissions or settings.

---

## Before you start

```bash
php artisan migrate --seed          # a test database, not production
php artisan queue:work              # or confirm your sync path
```

The queue matters more than it looks. **Most finance postings are event-driven**,
so with no worker the sale happens and the books stay empty — and that looks
identical to a bug.

Log in as **admin**. Where a step says "as X", switch user; that is the point of
the step.

---

## Part 1 — The shop (≈8 min)

| # | Do this | Expect | Why it's here |
|---|---|---|---|
| 1 | `/settings/modules` — confirm Storefront and the finance essentials are on | Toggles reflect what you switch | Everything below depends on flags |
| 2 | `/storefront-admin/push` — push a pig to the shop | Listing created with a `LIST-` reference | The pig → shop path |
| 3 | Try pushing a pig **currently under medication withdrawal** | **Refused**, naming the withdrawal | Selling a pig inside its withdrawal period is a food-safety failure |
| 4 | `/shop/browse` in a **private window** (not logged in) | Catalogue visible, listing openable | Guest browsing |
| 5 | Add to cart → `/shop/checkout` → place a **COD** order as a guest | Order confirmed, `SO-` reference shown | Guest checkout |
| 6 | Immediately re-open the listing in another window | Stock is **held**, not sold twice | The hold is what prevents overselling |
| 7 | `/storefront-admin/orders` → open the order → **Confirm** | Status moves; timeline records who did it | |

**Checkpoint:** the order exists, stock moved once, and the timeline names a person.

---

## Part 2 — Money reaches the books (≈7 min)

| # | Do this | Expect | Why it's here |
|---|---|---|---|
| 8 | `/finance/overview` | Loads; **Needs attention** panel present | |
| 9 | On the order page, find the **finance posting** panel | Says posted, pending, or skipped — never blank | This panel did not exist until recently; a sale that never reached the books used to look identical to one that did |
| 10 | If it says **skipped** | Reads as an explanation, *not* an error | Deferred lines are lines the system couldn't route automatically — not faults |
| 11 | `/finance/money-in` — record a payment against the order | Allocates to that order only | Targeted, not FIFO |
| 12 | `/finance/reports/income-statement` | Revenue reflects the sale, **net of tax** | Tax is money held for GRA, never income |
| 13 | `/finance/trial-balance` | **Balances** | If this is off, stop and investigate before anything else |

**Checkpoint:** the sale is in the books, tax is separated, and the trial balance balances.

---

## Part 3 — The things that have actually broken (≈10 min)

These are worth more than the rest. Each one is a defect this system genuinely
had, in a form that gave **no error** when it was wrong.

### 3.1 A farm manager can see the finance overview
As **farmmanager**, open `/finance/overview`.

**Expect 200.** A 403 means the permission seeders have drifted apart again — for
a long time every farm manager created through the app was locked out while a
legacy role kept access.

### 3.2 The finance menu matches what the farm has
`/settings/modules` → switch **off** every advanced accounting feature. Reload any page.

**Expect** Chart of Accounts, Journals, Vendor Invoices etc. to **disappear from the sidebar**.
Switch them back on — they return.

A menu entry that survives its feature being off is a dead end: clicking it 404s.

### 3.3 Failed postings are visible to someone
`/finance/overview` → the **Needs attention** panel.

If the outbox has failed events, they must appear here for an **admin**, not only
a superuser. These alerts were invisible to everyone but superuser for a long
stretch, so an empty queue was not evidence of a healthy one.

**On live data, check directly rather than trusting the screen:**
```sql
SELECT status, COUNT(*) FROM domain_events GROUP BY status;
```

### 3.4 Two farms don't share one price
Only if you run more than one organisation. Set a different
**live weight price** per farm in settings, then record a sale in each.

**Expect** each sale priced from its own farm's setting. This was global until
recently — one farm's price per kilo set another farm's revenue.

### 3.5 It works on a phone
Open `/sales`, `/finance/overview` and `/storefront-admin/orders` at **390px** wide
(browser devtools, iPhone 12).

**Expect no sideways scrolling.** If the page slides horizontally, controls end up
off-screen.

### 3.6 A duplicate payment notification changes nothing
If you can replay a Paystack `charge.success` webhook for an order already paid:

**Expect** one verified payment and **one** payment entry on the timeline.
Paystack retries by design, so re-delivery is routine, and the order history must
not claim the customer paid twice.

---

## Part 4 — Returns (≈3 min, optional)

| # | Do this | Expect |
|---|---|---|
| 14 | `/finance/returns` — record a return against the sale | Credit note raised |
| 15 | Check the income statement again | Revenue reduced **net**, tax reversed separately |
| 16 | For a returned **pig** | No quantity field, and it does **not** go back on sale automatically — a returned animal needs a health decision first |

---

## If something fails

| Symptom | Look here first |
|---|---|
| Sale happened, books empty | Is the queue worker running? Then `domain_events` for `failed` |
| Menu entry missing | Feature off (`/settings/modules`), or the user lacks the permission the **route** checks |
| Menu entry 404s | Feature is off but the menu still offers it — a gating bug |
| Trial balance out | Stop. Do not proceed to close anything |
| 403 for a role that should have access | Permission seeders drifted; re-run them and compare `farmmanager` against `manager` |

---

## Not covered here, and not self-certifiable

- **Ghana tax rates need accountant/GRA confirmation before going live.** This is
  a hard precondition, not a formality, and no test in this repo can satisfy it.
- Paystack sandbox runs against the real gateway
- Supervised queue/scheduler in a production-like environment
- Storage and signed-download behaviour under real load

## Deeper checks, when you want them

```bash
./vendor/bin/phpunit                                   # full suite
bash scripts/testing/run-mariadb-tests.sh              # against MariaDB
bash scripts/testing/assert-migrations-reversible.sh   # schema rolls back and returns
```

The last one matters before any deploy you might need to undo.
