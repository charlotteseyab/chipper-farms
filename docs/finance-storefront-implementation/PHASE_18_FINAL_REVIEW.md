# Phase 18 — Final implementation review (§63, §64)

## Document control

| Field | Value |
| --- | --- |
| Phase | 18 — Final implementation review |
| Date | 2026-08-11 |
| Branch | `staging` |
| HEAD at phase start | `83663a8` (Phase 17) |
| Blueprint anchor | §63 (final recommended scope), §64 (sign-off conditions) |
| Depends on | Phase 17 — COMPLETE |
| Phase status | **COMPLETE** |

## 1. What a final review is for

§63 is a scope contract — eleven things to build, eleven to defer. §64 is four
prohibitions and a readiness statement. Checking them is mostly confirmation
work.

The value turned out to be elsewhere. **A sweep written in Phase 16 to check
menu permissions against route permissions was broken, and reported a clean
result.** Redone correctly here, it found eleven more defects of a kind Phase 16
had already found twice and believed it had cleared.

## 2. §63 — scope conformance

### 2.1 Deferred scope was not built

Every "defer" row was searched across `app/`, `routes/` and
`database/migrations/`. Nothing on the list exists. The apparent hits are all
different meanings of the same word, which is worth recording so a future sweep
does not re-raise them:

| Term | Hits | What they actually are |
| --- | --- | --- |
| promotion | 11 files | Pig lifecycle promotion (weaner → grower), not sales promotions |
| deposit | 6 files | Bank reconciliation *outstanding deposits*, an accounting term |
| budget | 2 files | The outbox's retry budget, and a comment naming budgets as unimplemented |
| loyalty | 2 files | A provisional `loyalty_points` column from a 2026 migration, commented "not used" |
| partial payment | 2 files | Comments recording that partial-payment allocation is future work |
| coupons, multi-currency, breeding bookings, marketplace, multi-vendor, report builder | 0 | absent |

### 2.2 Included scope was built

All eleven "include now" rows are delivered and documented in the phase reports:
feature flags and presets (1), simplified finance nav and dashboard (3), quick
expense (4), simple purchase (6a/6b), cash/bank/MoMo (5), Ghana 2026 tax
profiles (2), storefront settings (8), guest checkout (9), checkout holds (10),
order/refund recovery UX (13, 15). **Mobile polish is partial** — §55's viewport
review remains IMP-114, open, as Phase 17 recorded.

## 3. §64 — the four prohibitions

| Prohibition | Verified how | Result |
| --- | --- | --- |
| Do not replace the finance engine | `PostingEngine`, `NumberingService`, `OutboxClaimer`, invoice/receipt/payment services all present and still the path used | ✅ |
| Do not bypass the sale spine | `RecordPigSaleService` and `RecordFeedSaleService` intact; `StorefrontFinanceBoundaryTest` sweeps storefront code for direct `PostingEngine`/`JournalBatchDraft` use | ✅ |
| Do not treat feature flags as authorization | Every route swept: **159 feature-gated, and none lacks a permission or role check** | ✅ |
| Do not launch Ghana tax without external confirmation | Recorded as an unmet external gate in `PHASE_02` §61 and the risk register | ✅ (still unmet, correctly) |

## 4. The Phase 16 sweep was broken

### 4.1 What went wrong

Phase 16 compared what each menu entry demands against what its route enforces.
It matched middleware by **alias** — `permission:` — while `route:list` reports
the **resolved class**, `App\Http\Middleware\PermissionMiddleware:view_finance`.

So the comparison ran against empty lists for every route, found no
intersections to contradict, and reported no mismatches. The one finding it did
produce (`finance.costing.index`, a route that does not exist) came from the
route-existence side, which worked.

A sweep that finds nothing looks identical to a clean codebase. That is the
whole lesson of this phase, and it is the same shape as the defects it was
looking for.

### 4.2 What the corrected sweep found

**Eighteen phantom permissions** in `FinanceNavigation` — names no seeder
creates. `view_finance_dashboard`, found in Phase 16, was one of eighteen rather
than one of one.

A phantom permission is held by nobody. `hasAnyPermission()` is false for every
user except superuser, who bypasses through `Gate::before`. Seven of the
eighteen sat harmlessly beside a real permission in an OR-list. Eleven did not,
and **seven menu entries asked exclusively for names that do not exist**:

- Chart of Accounts
- Journal Entries
- Bank Statements
- Bank Reconciliation
- Payment Runs
- Supplier Statements
- Vendor Invoices

Since Phase 16 wired the sidebar to `FinanceNavigation`, those entries have been
invisible to every non-superuser. **That regression shipped in Phase 16 and the
broken sweep is why it was not caught then.**

### 4.3 The fix

Every entry now asks for a permission its own route actually enforces — the rule
Phase 16 stated and applied to two entries. Where the routes turned out to
enforce only `view_finance`, that is what the menu asks for.

Worth naming plainly: the phantom names record an *intent* that was never built.
Someone meant `view_chart_of_accounts` to be a distinct permission. It never
existed, so the route was written against `view_finance` and the finer control
was never real. Making the menu match the route restores access and reflects the
system as it is. **If finer-grained ledger permissions are wanted, the route is
where they belong** — tightening the menu alone would hide screens without
protecting anything.

### 4.4 Two things that made this hard to see

**The guard test first reproduced the bug it was written to catch.**
`Route::gatherMiddleware()` returns the **alias**, while `route:list` returns the
**resolved class** — the mirror image of the Phase 16 mistake. The helper
returned empty for every route until it matched both forms. It is kept as an
explicit two-branch check with the reason written down rather than tidied.

**A test was granting the phantom permissions to itself.**
`FinanceOverviewScreenTest::user()` builds its user with
`Permission::firstOrCreate`, which *invents* whatever name it is handed. The test
granted `view_chart_of_accounts`, the menu asked for `view_chart_of_accounts`,
and the two agreed — while no real user held it. A fixture that creates
permissions on demand can never detect a phantom, and this one had been
concealing them since Phase 3.

## 5. The guard

`NavigationPermissionIntegrityTest` enforces two rules over every entry
`FinanceNavigation` defines:

1. **Every permission named must be one a seeder creates.** Otherwise the entry
   is dead for everyone but superuser.
2. **At least one must be a permission the route itself enforces.** Otherwise the
   menu is stricter than the page and hides a screen the user may open.

Neither is an authorization control — route middleware remains that (SEC-001).
These only stop the menu from lying about it.

A third test asserts the sweep parses more than 25 entries and reads a non-empty
permission list for a known route, because the failure being guarded against is
precisely a sweep that silently matches nothing.

### 5.1 Verified in both directions

| Defect reintroduced | Result |
| --- | --- |
| A phantom permission restored to any entry | **Fails**, naming the entry and the permission |
| A menu entry pointed back at a permission its route does not check | **Fails**, printing both lists |
| The helper narrowed to match only the resolved class (the original bug) | **Fails** on the sweep-integrity test |

## 6. Commands run and exact results

```
$ ./vendor/bin/phpunit
Tests: 1781, Assertions: 5294, Skipped: 13         # 0 failures, 0 errors

$ bash scripts/testing/run-mariadb-tests.sh        # MariaDB 11.4.2, port 3399
Tests: 1781, Assertions: 5304, Skipped: 7          # 0 failures, 0 errors
```

No migrations.

## 7. Sign-off

§64's readiness statement holds: idempotent posting, outbox processing, guarded
order confirmation, Paystack verification (now including duplicate delivery),
refund settlement, sales returns, credit notes and permission-gated admin
surfaces are all in place and tested on both engines plus real multi-process
concurrency.

**Open items, named rather than closed:**

| Item | Status |
| --- | --- |
| **IMP-001** | `sales.pricing.*` still global across tenants — latent while single-tenant, a live money defect on second-tenant onboarding |
| **IMP-114** | §55 viewport review of older ledger and dashboard screens; §60's Mobile row needs a browser harness that is not installed |
| **IMP-118** | `mill_operator` holds permissions no user can hold — needs a product decision, not a code change |
| **IMP-084** | The hold-lock negative control is probabilistic rather than deterministic |
| §61 external certification | Ghana tax confirmation, Paystack sandbox, supervised queue/scheduler, storage and signed downloads, and both UAT tracks — none self-certifiable here |

**The Ghana tax rates must not go live without accountant/GRA confirmation.**
That was §64's explicit condition, it remains unmet, and no phase has claimed
otherwise.
