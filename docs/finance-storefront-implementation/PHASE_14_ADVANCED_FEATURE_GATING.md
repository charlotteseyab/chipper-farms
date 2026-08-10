# Phase 14 — Advanced feature gating

## Document control

| Field | Value |
| --- | --- |
| Phase | 14 — Advanced feature gating |
| Date | 2026-08-10 |
| Branch | `staging` |
| HEAD at phase start | `440b903` (Phase 13b) |
| Blueprint anchor | §16 (optional advanced finance scope), §18 (optional storefront scope), §32 (flag architecture) |
| Depends on | Phases 1, 3–13 — all COMPLETE |
| Phase status | **COMPLETE** |

## 1. The debt this phase pays

Phase 1 defined twenty finance features. By Phase 13, eight of them gated a
route and the ten **advanced** ones gated nothing at all. `FinanceNavigation`
hid the menu entries and every URL stayed live.

Prompt §110 is the rule: "a disabled feature must not remain usable via URL".
Every phase from 7 onward recorded this and deferred it here on the same
reasoning — gating them one at a time, as each phase happened to touch a route,
would have left a half-state harder to reason about than either end.

| Feature | Now gates |
| --- | --- |
| `finance.advanced_accounting` | chart of accounts, manual journals, trial balance |
| `finance.advanced_ap` | vendor invoices, payment runs, supplier statements |
| `finance.wht` | WHT certificates |
| `finance.bank_reconciliation` | bank statements, bank reconciliation |
| `finance.period_close` | period close, year-end |
| `finance.investor_management` | investors |
| `finance.advanced_biological` | biological assets, group cost pools, opening pig inventory |

Each mapping was read off `FeatureCatalog`'s own `backs` field, which is where
the intent was written down in Phase 1 — not invented here.

## 2. The rule that outranks the gating

SEC-001 has not moved: access needs **feature enabled AND permission granted AND
organisation scope valid**. Gating is presentation; permission is the control.

Half of the new test file is the guard on that: every gated surface, with its
feature switched **on**, must still refuse a user who lacks the permission. A
feature flag quietly becoming the only thing between a user and a ledger is the
failure this phase could most easily have introduced.

## 3. What this did to the existing suite, and why that was the right answer

Turning the gates on broke **54 tests**. Every one was a test exercising a
surface that is off by default for a *new* organisation.

The fix is the same principle Phase 8 applied to the storefront: a farm that
uses WHT certificates switches WHT on, and so must its fixture.
`FinanceTestSetup::setUpFinanceFixtures()` now enables the whole catalogue,
because that fixture represents an **established** accounting organisation —
chart of accounts, fiscal periods, an accountant — and Phase 1's backfill gives
exactly such an organisation everything, so that nothing a working farm relies
on vanishes when a flag defaults to off.

A test *about* gating therefore switches a feature off deliberately. That is the
right way round: the interesting assertion is "this surface disappears when the
farm turns it off", not "it happens to be on".

### 3.1 The security test needed the opposite treatment, for the same reason

`FinanceRouteAuthorizationTest` builds its own organisation and tests the
**permission** boundary. Once the surfaces were gated, a disabled feature
answered 404 and would have masked whether the permission check ran at all — the
test would have kept passing for the wrong reason.

Every feature is now switched on there explicitly, with a comment saying why, so
every refusal in that file is a permission refusal.

## 4. Tests

24 in `AdvancedFeatureGatingTest`: eleven surfaces × two properties, plus
dependency cascade and an essentials-unaffected check.

### 4.1 Verified in both directions

| Defect reintroduced | Result |
| --- | --- |
| The advanced-accounting gate removed from `finance/coa` | **Fails** — the surface stays reachable with the feature off |
| A gated route's `permission:` middleware removed | **Fails** — "a feature flag is not an authorization control" |

### 4.2 The second control did not work at first

Dropping `permission:view_finance` from the chart-of-accounts route left the
SEC-001 test green. The reason is instructive and is itself good news: the outer
`finance.access` boundary (CF-P0-003) refuses a user holding no finance
permission at all, so the test could not tell "the route checked" from "the
boundary caught it".

The user in that test now holds `record_expenses` — the narrowest real finance
permission there is, enough to clear the outer boundary and entitling them to no
ledger. With that, removing a per-route permission fails loudly.

This is the third time in four phases that reintroducing a defect exposed a test
weaker than it looked. It keeps being worth the few minutes.

## 5. Deliberately not gated

| Surface | Why |
| --- | --- |
| `finance/tax` | Phase 2's reasoning stands: a farm on Essentials still charges tax and must be able to configure it. Permission alone protects it |
| `finance/ageing`, `finance/reports` | `finance.reports` is an **essential** feature, not advanced |
| `storefront.customer_accounts` | Blocking the account area would hide existing customers' orders. Needs a decision about what an existing account holder sees, which is §46 no-dead-end work — Phase 15 |
| `finance.customer_credit` | Backs "counterparty credit hold", which is a behaviour inside the customer screens rather than a route of its own. Gating the screens would take the whole contacts surface with it |

## 6. Commands run and exact results

```
$ ./vendor/bin/phpunit --filter AdvancedFeatureGatingTest
OK (24 tests, 48 assertions)

$ ./vendor/bin/phpunit
Tests: 1723, Assertions: 5052, Skipped: 13        # 0 failures, 0 errors

$ bash scripts/testing/run-mariadb-tests.sh        # MariaDB 11.4.2, port 3399
Tests: 1723, Assertions: 5062, Skipped: 7          # 0 failures, 0 errors
```

No migrations.

## 7. Status

**Phase 14: COMPLETE.** Every finance feature in the catalogue now gates the
surface it names, and the permission layer is proved to survive the gating.

Phase 15 (responsive UX and the no-dead-end audit, §46/§55) is next, and carries
two items parked here: `storefront.customer_accounts` gating, and the legacy
`NavigationHelper` sidebar that still builds a hardcoded role-based finance menu
alongside `FinanceNavigation` (IMP-070).
