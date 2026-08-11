# Phase 16 — Full security and regression (§47–§49)

## Document control

| Field | Value |
| --- | --- |
| Phase | 16 — Full security and regression |
| Date | 2026-08-11 |
| Branch | `staging` |
| HEAD at phase start | `fd35d02` (Phase 15) |
| Blueprint anchor | §47 (idempotency matrix), §48 (transaction/locking matrix), §49 (permission matrix) |
| Depends on | Phases 1–15 — all COMPLETE |
| Phase status | **COMPLETE** |

## 1. What this phase was for

§47 and §48 are matrices of controls. §49 is a matrix of who may do what,
followed by one instruction: *"Implementation must reconcile current seeded
permissions with these product roles."*

Until this phase those were prose. The controls were mostly there — they were
built phase by phase with these matrices open — but nothing asserted they were
still there, and the permission matrix had drifted in a way nobody could see.

**Permission grants are silent when they miss.** No error is raised. The role row
exists, the permission attaches to it, and a person is quietly locked out of
their job. That is the theme of everything below.

## 2. §47 — Idempotency

Thirteen of fourteen controls verified in place. Both new items were things the
matrix implied and nothing enforced.

### 2.1 The listing reference had no backstop

§47 names `StorefrontReferenceAllocator` as the control for order, quote **and**
listing references. Orders and quotes also carry `UNIQUE (organization_id,
reference)`; listings carried only a unique slug.

In practice the allocator is a single writer serialised through
`NumberingService`'s `SELECT ... FOR UPDATE`, and `PushToStorefrontService` is
the only path that creates a listing — so this was defence in depth, not a live
defect. It is worth having: the allocator's own docblock records that order
references were once generated in two places from a row count, and it was the
unique index that turned that into a visible failure rather than two orders
quietly sharing a reference.

The migration **refuses** if duplicates already exist, naming them, rather than
repairing. A listing reference is printed, linked and quoted to customers;
choosing which row keeps the number is a business decision, not one a migration
should make at deploy time.

### 2.2 The Phase 15 retry was never proved to reach the claimer

Phase 15 built a "Retry finance posting" button that returns a dead-lettered
event to `pending`, and its tests asserted the status column changed. Nothing
asserted the thing that matters: that a re-queued event is then **collected and
processed**. A button that sets a column and is never collected again would have
passed every test Phase 15 wrote.

It is collected — the claim query filters on status and `next_retry_at`, not on
the attempt counter, which arrives at the ceiling on a dead letter. `fail()`
does check the ceiling, so a replay that fails again dead-letters immediately
instead of climbing the backoff ladder a second time. That is the right
behaviour and is now pinned: **one press, one attempt.** Staff who fixed the
cause get an answer straight away, and a farm does not accumulate an invisible
queue retrying on a ladder nobody can see.

## 3. §48 — Transactions and locking

All twelve flows honoured; no changes needed. The two easiest to get wrong were
checked directly:

- **Refund** — the Paystack call happens *before* `DB::transaction()` opens, so
  no lock is held across network latency.
- **COD completion** — the blocker preflight runs before the transaction, and the
  refusal event is written outside it so it is not rolled back with the refusal.
- **Bank transfer** — satisfies §48's second option ("post atomically through
  ledger"): the transfer row and its posting are one transaction.

## 4. §49 — The permission matrix, and the defect it was hiding

### 4.1 One product role, two role rows

`manager` and `farmmanager` are the same person. `User::normalizeRoleName()`
maps the former onto the latter on every write, and `manager` is absent from
`User::ALLOWED_ROLE_NAMES`, so **nothing the application creates can hold it**.

The seeders did not know that, and had settled into three conventions:

| Seeder | Convention |
| --- | --- |
| `StorefrontPermissionsSeeder` | aliased manager → farmmanager |
| most others | granted to **both** names |
| `FinancePermissionsSeeder` | granted `view_finance` to **`manager` alone** |

Measured before touching anything:

```
role=farmmanager  GET /finance -> 403   can(view_finance)=false
role=manager      GET /finance -> 200   can(view_finance)=true
```

§49 row 1 gives Farm Manager the overview. Every farm manager created through
the application was refused it; the row no user can hold kept it. In the other
direction a legacy `manager` user lost eight storefront, expense and purchase
permissions.

**The fix**: one shared convention (`NormalizesSeededRoleNames`) applied to all
25 permission seeders, so a seeder may keep writing the farm's vocabulary and
the grant lands on the row a user can hold. Plus an idempotent, re-runnable
migration for databases that already carry the split: it copies the legacy
row's grants onto `farmmanager` **before** moving users across, so nobody loses
access in the gap. The `manager` row is left in place — deleting a row other
tables may reference is not something `down()` can undo.

### 4.2 The matrix is now executable

`PermissionMatrixTest` asserts §49's capability rows against seeded reality, and
adds the assertion that would have caught this the day it was written: **no
permission may be granted to a role name no user can hold.**

`mill_operator` is a documented exception rather than an oversight — five
permissions, all also held by `farmmanager` and `admin`, so nothing is
unreachable. Making it a real role is a product decision §49 does not cover.
Recorded as IMP-118.

## 5. IMP-070 — the finance menu was never wired up

Carried since Phase 7 as "two menu builders in parallel". It was worse than
that.

`FinanceNavigation` — the feature-and-permission-aware builder Phase 3 wrote for
exactly this job — was **referenced by nothing but its own tests**. The live
sidebar was `NavigationHelper::sidebarSections()`: ~240 lines of hardcoded role
arrays that knew nothing about organisation feature flags.

So Phase 3's menu and Phase 14's gating both passed their tests while the
running application did neither. After Phase 14, a farm on Essentials was still
offered Vendor Invoices and Chart of Accounts in its sidebar and got a 404 for
clicking them — a §46 dead end manufactured by the navigation itself.

### 5.1 Wiring it up exposed four more defects

None could be seen while the class was dead code, because nothing made it agree
with `routes/web.php`.

| Defect | Effect once wired |
| --- | --- |
| Overview asked for `view_finance_dashboard` — **a permission no seeder creates** | The entry vanished for everyone. It existed only in this array and two Needs Attention cards |
| `finance.costing.index` — **a route that has never existed** | `admits()` drops entries whose route is unregistered, silently removing the **entire inventory and costing area** from the menu |
| Period Close / Year End asked for `manage_period_close` | Both routes require `view_period_close`; the screens were hidden from a farm manager who may read them |
| Reports, Loans and Investors had no sub-entries | Balance Sheet, Cash Flow, Reclassification, Cap Table and Allocate Profit had working routes and nothing linking to them |

All four are one shape: **the menu demanding more than the page does, or naming
something that is not there.** The first is the most serious beyond the menu —
`view_finance_dashboard` also gated the two Needs Attention cards for failed
finance postings and stuck outbox events, so **those alerts have been invisible
to every user except superuser since Phase 3.** The recovery queue was built
correctly and then gated on a permission that was never created.

They are fixed rather than reverted, because §25 is explicit that the ledger
screens are "kept... rather than being deleted", and a screen with no link is
deleted by another name.

### 5.2 A test that passed for the wrong reason

`FinanceOverviewScreenTest` asserted that the bookkeeping card was **hidden**
from a user without `view_finance_dashboard`. It passed because *nobody* holds
that permission — so it proved nothing about filtering while concealing the
defect. Rewritten to exercise the filtering against a card whose permission
exists and that this viewer genuinely lacks.

## 6. The two remaining carried items

### 6.1 IMP-112 — cross-tenant misses now answer 404

`AdminOrderController` and `StorefrontPlacementController` answered 403, telling
a prober that the record exists and belongs to someone else. Storefront
references are sequential and easy to guess. Both now answer 404: another farm's
records are indistinguishable from records that were never there.

403 remains the right answer when the resource **is** yours and you may not do
this to it. That is the permission layer, and it still answers 403.

### 6.2 `storefront.customer_accounts` — gated, with order history left open

Parked by Phase 14 because gating `account/*` naively would hide existing
customers' orders. The resolution was already written down in
`EnsureStorefrontOpen`, which faced the same tension when the shop closes:
*"What closes is the ability to browse and to place something new."*

Account self-service (dashboard, profile, addresses, saved items, quotes) closes
with a 503 and a page. **Order history is deliberately left ungated.** A
person's record of what they bought is theirs, and a farm switching off a
convenience feature is not a reason to take it away.

## 7. Tests

37 new across six files, plus corrections to four existing ones.

| File | Tests |
| --- | --- |
| `PermissionMatrixTest` | 11 |
| `ManagerRoleReconciliationTest` | 6 |
| `ListingReferenceUniquenessTest` | 5 |
| `DeadLetteredEventReplayTest` | 4 |
| `FinanceSidebarTest` | 5 |
| `CustomerAccountGatingTest` | 6 |

### 7.1 Verified in both directions

| Defect reintroduced | Result |
| --- | --- |
| Seeder role normalisation removed | **5 tests fail**, including the concrete 403 |
| The migration moves users without copying grants first | **2 fail** — the farm manager loses `view_finance` in the gap |
| The listing unique index dropped | **Fails**; the migration's own rollback is exercised rather than assumed |
| The sidebar bypasses `FinanceNavigation` | **4 fail** |
| Order history gated along with self-service | **Fails** — the dead end Phase 14 feared |
| The account gate removed entirely | **6 error** |

### 7.2 Three existing fixtures were wrong, not the code

`VendorInvoiceControllerTest` and `CustomerControllerTest` both attached a user
to the raw `manager` row — building a user the application itself cannot
produce. Both now normalise as the application does; every permission assertion
in them is unchanged.

`FinanceSidebarNavigationTest` asserted a menu for an organisation with the
advanced features **off**. Before Phase 16 the sidebar ignored feature flags, so
the entries appeared regardless. Its farm now enables the features it uses — the
same call Phase 14 made for `FinanceTestSetup`. Its route assertions are
unchanged; three labels moved to §20's vocabulary.

## 8. Commands run and exact results

```
$ ./vendor/bin/phpunit
Tests: 1770, Assertions: 5264, Skipped: 13         # 0 failures, 0 errors

$ bash scripts/testing/run-mariadb-tests.sh        # MariaDB 11.4.2, port 3399
Tests: 1770, Assertions: 5274, Skipped: 7          # 0 failures, 0 errors
```

A first MariaDB attempt reported **1686 errors** because the 11.4.2 server had
been reaped between phases. That is IMP-096's failure mode showing itself
loudly rather than as a silent exit 0 — the reason this programme reads the
gate's output rather than its exit code either way. The server was restarted and
the gate re-run from scratch.

Two migrations, both additive:

- `2028_09_20_100000_reconcile_manager_and_farmmanager_roles` — idempotent and
  re-runnable; `down()` is deliberately a no-op, because it cannot distinguish
  the users it moved from those who were always farm managers.
- `2028_09_20_100100_add_unique_reference_index_to_storefront_listings` —
  rollback exercised in test.

## 9. Status

**Phase 16: COMPLETE.**

Still open and named rather than dropped:

| Item | Note |
| --- | --- |
| **IMP-114** | §55 viewport review of the older ledger screens |
| **IMP-118** | `mill_operator` holds permissions but no user can hold the role |
| **IMP-001** | `sales.pricing.*` is still global across tenants |

Phase 17 is local production certification (§60).
