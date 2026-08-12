# Phase 18b — Closing the issue register

## Document control

| Field | Value |
| --- | --- |
| Phase | 18b — Issue register closure |
| Date | 2026-08-11 |
| Branch | `staging` |
| HEAD at phase start | `c706e22` (Phase 18) |
| Scope | Every item left Open in `CHIPPER_FARMS_FINANCE_STOREFRONT_IMPLEMENTATION_STATUS.csv` |
| Phase status | **COMPLETE**, with one item deliberately left open — IMP-134, §7 |

## 0. Summary

Fourteen items were Open. Nine turned out to be **already fixed** with stale
rows; five needed real work; one row was **malformed** and had been unreadable
since it was written. Three new findings came out of the work, one of them serious
and self-inflicted (§8).

| | Count |
| --- | --- |
| Stale rows closed with evidence | 9 |
| Genuinely fixed here | 5 |
| Malformed row repaired | 1 (IMP-037) |
| New findings recorded | 3 (IMP-134, IMP-135, IMP-136) |
| Still open | 1 (IMP-134) |

## 1. The nine that were already fixed

Every one was verified against source and a passing test before its row was
touched — a row is not closed because a report says so.

| Item | Verified by |
| --- | --- |
| IMP-022 receipt attachments in Quick Expense | `QuickExpenseAttachmentTest`, 9 tests |
| IMP-029 Simple Purchase not built | `SimplePurchaseTest`, `SimplePurchaseScreenTest` |
| IMP-035 MariaDB 11.4.2 gate failures | gate green every phase since Phase 8 |
| IMP-036 / IMP-041 pig death free text | migration `2028_09_07_100000` added the `*_other` columns; the request stops collapsing; accessors surface the text |
| IMP-039 sale purpose export vs cull | migration `2028_09_06_100000` allows **both** — additive, so no existing row is rejected |
| IMP-037 WHT crash and cross-supplier leak | fixed; the row was merely unreadable |
| IMP-070 duplicate finance sidebar | Phase 16 |
| IMP-112 cross-tenant 403 vs 404 | Phase 16 |

Two test names were corrected while confirming the pig-death work:
`test_other_cause_collapses_to_text` described the **old** behaviour and had
survived the fix that removed it.

### 1.1 The malformed row

IMP-037's `Resolution` contained unquoted commas — *"Fixed all three: is_default,
grouped the or-condition inside a closure, and corrected the column names"* — so
every field after it shifted by two and its `Status` parsed as
`" grouped the or-condition inside a closure"`. It read as Open in every sweep
since. Quoted; the register now parses with zero malformed rows.

## 2. IMP-096 — a gate that could report success while proving nothing

The runner was invoked as `run-mariadb-tests.sh | tail -7`. **A pipeline's exit
status is the last command's**, and `tail` succeeds at printing nothing, so a run
against a dead server was invisible from outside no matter how carefully the
caller checked `$?`.

The checks now live inside the script, because the caller cannot be relied on to
make them:

- the server is checked **before** PHPUnit starts, so a dead server produces one
  sentence instead of ~1700 identical connection errors;
- a run is only called a pass if PHPUnit printed a test-count summary.

Verified: a run producing no summary exits **70** with an explicit
`INCONCLUSIVE` message. The preflight's message path is not separately proven —
the local env file pins the port and the caller cannot override it — and the
summary check is the one that actually prevents the IMP-096 failure.

## 3. IMP-084 — a negative control that only sometimes failed

The hold race test's control was probabilistic: with `lockForUpdate()` removed,
twelve workers *did* produce two live holds on one animal, but not on demand. The
window between the read and the write is microseconds, so whether two workers
land in it depends on how busy the machine is.

Replaced with a control that does not depend on luck. One worker takes an
explicit `FOR UPDATE` lock on the listing row and holds it; another calls
`placeForOrder()` with a two-second lock wait:

- if the code locks the row, it waits and times out — **pass**;
- if it does not, it sails through and places the hold — **fail**.

Those differ by mechanism, not timing. The lock is taken on the row
`placeForOrder()` must actually contend for, so it cannot pass by locking the
wrong row.

**Verified three times in a row with `lockForUpdate()` removed: three failures
out of three.**

## 4. IMP-114 — the viewport review, measured

Laravel Dusk was added and pinned to the installed Chromium 126. Eight screens
are measured at 390×844 for horizontal overflow — the page sliding sideways,
which puts controls off-screen.

**It found a real defect.** `/sales` overflowed by **236px**.

### 4.1 The first diagnostic blamed the wrong element

The initial version reported the widest box, which named
`table.min-w-full` — a table already correctly wrapped in an `overflow-x-auto`
container. Had that been trusted, the "fix" would have been applied to code that
was right.

The measurement now ignores any element with a scrollable ancestor, because such
an element is *supposed* to be wider than the screen and scrolls inside its own
box. What remained was the true cause: a header row of four buttons in a
non-wrapping flex. Fixed with `flex-wrap` and `gap`.

Reverse check: restoring `space-x-4` reproduces the overflow and fails the test.

## 5. IMP-118 — mill_operator

Not a new product role. The seeders granted it five permissions,
`EnsureFinanceAccess` documented it in its role matrix, and
`NavigationHelper::dashboardItem()` had been made total **specifically because**
a role outside `ALLOWED_ROLE_NAMES` would 500 every page — naming mill_operator
as the role that did it.

Everything was built around it except its name in the allowed list, so no user
could hold it and all five grants reached nobody. Added, with a test that one can
now be provisioned, holds `view_inventory_value`, and is served a page.
`PermissionMatrixTest::KNOWN_UNREACHABLE_ROLES` is now empty.

## 6. IMP-001 — settings were global across tenants

Open since Phase 0. `system_configs` had a globally unique `key`, so
`sales.pricing.live_weight_price` — which `RecordPigSaleService` reads to price a
real sale — was shared by every tenant. On a second farm, one farm's price per
kilo would set another farm's revenue.

`organization_id` is added **nullable**: a NULL row is the default for everyone,
a scoped row wins for its own farm. **No existing row was moved**, so every
current value remains the default and a single-tenant install behaves
identically. A farm departs from the default only when someone saves a value for
it.

The alternative — copying every row to every organisation — would have made the
backfill the risky part and would freeze today's values as though each farm had
chosen them.

Reverse check: removing the scoping fails 4 of 7 tests, including the money
defect stated directly.

## 7. IMP-134 — migrations that cannot be rolled back (OPEN)

Surfaced by the browser suite rolling back after each test: **a run of migrations
have a `down()` that cannot complete on MariaDB.** They were only ever exercised
on SQLite, which rebuilds a table on a column drop and so tolerates dropping a
column that still carries a foreign key or an index. MariaDB refuses.

`extend_activity_logs_for_audit_trail` is **fixed** — the foreign key is dropped
first, and both the FK and index drops are conditional on the schema actually
containing them, so a part-way rollback can be retried. Rollback and re-apply are
exercised on MariaDB 11.4.2.

A detail worth keeping: **a `try/catch` around `$table->dropForeign()` catches
nothing.** Inside a `Schema::table()` closure that call only *queues* the
command; the SQL runs after the closure returns, outside the try. The first
attempt at this fix made exactly that mistake and failed on the opposite error.

`users.last_seen` fails the same way and is **not fixed**. That is the open part
of this item, and it is left open rather than rushed.

## 8. IMP-135 — the browser suite destroyed the development database

**This is the most serious thing in this phase and it was self-inflicted.**

`DatabaseMigrations` runs `migrate:fresh`, which drops every table on the
connected database. Dusk drives a real HTTP server, so that connection is the one
in `.env` — the development database. Nothing stopped it.

The first run dropped `chipper-farms` and everything in it. **The pages still
rendered, empty, so the run looked like it had worked** — the failure was found
half an hour later, by a rollback error.

The data was unrecoverable: no dump existed, and none of the three
candidate databases held a snapshot (`chipper_farms_restore_test` and
`chipper_farms_upgrade_test` had zero tables; `chipper_farms_test` is the gate's
scratch database). Rebuilt with `migrate:fresh --seed`.

Two independent guards now:

1. **`DuskTestCase` refuses to start** unless the connected database is
   `chipper_farms_upgrade_test`. Verified to refuse `chipper-farms` by name.
2. **`MobileViewportTest` no longer uses `DatabaseMigrations`.** It only reads
   pages to measure layout; it never needed a pristine database per test.
   Removing the trait removes the destructive step altogether, which is better
   than making the destruction safer.

The lesson is not subtle: `tests/Concurrency/ConcurrencyTestCase` has had
`assertSafeDatabaseSelection()` since it was written, for exactly this hazard. A
new suite with the same hazard was added without the same guard.

A correction for the record: while assessing the damage I reported that "the
`pigs` table is gone". The table is `pigs_management` — I checked a name that
never existed. The data loss was real; that particular evidence was not.

## 9. IMP-136 — a test that passed only because of the order it ran in

The MariaDB gate failed two navigation assertions that the SQLite suite passed.
Run **in isolation, the test failed on both engines** — so the engine difference
was only how it surfaced. The real defect was order-dependence, and a green
full-suite run had been concealing it.

`FinanceNavigation` asks `OrganizationFeatureService::usable()` **without** an
organisation id, so the service resolves one from `CurrentOrganization`. The
fixture enabled every flag on `$user->organization` while nothing was bound, so
the navigation read a different organisation — in practice none — every flag fell
back to its catalogue default, and the advanced entries vanished. When another
test had already bound an organisation in the same process, it passed.

The fixture now binds `CurrentOrganization` as a real request does. Verified by
running the file on its own on both engines.

This is worth noting beside §2: a suite-level pass is not evidence that a
particular test is sound. This one had been reported as passing in Phase 16.

## 10. Commands run and exact results

```
$ ./vendor/bin/phpunit
Tests: 1789, Assertions: 5312, Skipped: 13         # 0 failures, 0 errors

$ CF_CONCURRENCY_DB_* … ./vendor/bin/phpunit -c phpunit.concurrency.xml
OK (15 tests, 270 assertions)                      # MariaDB 11.4.2

$ DB_DATABASE=chipper_farms_upgrade_test php artisan dusk --filter MobileViewportTest
PASS — 8 screens at 390×844                        # Chromium 126

$ bash scripts/testing/run-mariadb-tests.sh        # MariaDB 11.4.2, port 3399
Tests: 1789, Assertions: 5322, Skipped: 7          # 0 failures, 0 errors
```

Two migrations added (`scope_system_configs_to_organizations`, and the
`activity_logs` `down()` repair), both exercised in rollback on MariaDB.

## 11. Status

**136 of 137 register items are closed.** IMP-134 is open with its remaining
scope stated: the migrations after `activity_logs` whose `down()` has never run
on MariaDB.
