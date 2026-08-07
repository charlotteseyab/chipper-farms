# Phase 0 — Baseline, Architecture Verification and Scope Lock

## Document control

| Field | Value |
| --- | --- |
| Phase | 0 — Baseline and scope lock |
| Date | 2026-08-07 |
| Branch | `staging` |
| Blueprint baseline | `9cb8863f5d3aa3be570ebf270830906f7c57da8a` |
| HEAD at phase start | `e9bc329c3a33eb128e191a6a720354af300a5a95` |
| Application source modified | Yes — one dependency update (see §7) |
| Phase status | COMPLETE |

## 1. Scope

Phase 0 establishes a known-safe starting point. Per the implementation prompt it
performs no feature implementation. It records the baseline, verifies that the
blueprint's claims still hold against current source, and locks scope for
Phases 1–18.

The prompt states that historical reports are evidence only and that **current
application source is authoritative**. Every blueprint gap claim relied upon in
later phases was therefore re-verified directly against source rather than
accepted from the document.

## 2. Baseline divergence from the blueprint commit

HEAD has moved two commits past the blueprint baseline:

| Commit | Subject | Affects this implementation? |
| --- | --- | --- |
| `aa6f8d7` | Add Claude Code permission policy | No — tooling config only |
| `e9bc329` | Permissions: close the gaps that would still have prompted | No — tooling config only |

Neither touches application code, migrations, tests or configuration consumed at
runtime. The blueprint baseline is therefore materially intact and no work was
reset to match it.

Untracked working-tree files (blueprint, review, prior go/no-go artefacts, three
`.zip` archives) were left untouched, consistent with the blueprint's own record
of the same tree.

## 3. Route surface — blueprint baseline confirmed

The blueprint claims 207 finance routes, 33 storefront-admin, 7 public shop.
Measured independently:

| Bucket | Count | Blueprint claim | Match |
| --- | ---: | ---: | --- |
| `finance/*` | 207 | 207 | Yes |
| `storefront-admin/*` | 33 | 33 | Yes |
| `shop/*` | 7 | 7 | Yes |
| `account/*` | 16 | — | — |
| `settings/*` | 19 | — | — |
| other | 231 | — | — |
| **Total** | **513** | — | — |

Command: `php artisan route:list --json`

## 4. Component inventory

| Area | Count |
| --- | ---: |
| Migrations | 193 |
| Models | 124 |
| Policies | 11 |
| Controllers | 87 |
| Finance services (`app/Finance` + `app/Services/Finance`) | 136 |
| Storefront classes (`app/Storefront`) | 32 |
| Finance outbox subscribers | 23 |
| Test classes | 198 |
| Finance Blade views | 79 |
| Storefront Blade views | 26 |

This confirms the blueprint's central premise: the engine is deep and the deficit
is product surface, not accounting capability. The preservation rule in §4 of the
prompt is therefore the correct default.

## 5. Blueprint gap claims — independently verified

Each claim below was checked against source, not assumed.

| Blueprint gap | Claim | Verified finding | Status |
| --- | --- | --- | --- |
| CFG-001 | No organisation feature flags | No migration matching `feature`/`preset`; no `FeatureFlag`/`OrganizationFeature` symbol anywhere in `app/` or `database/` | CONFIRMED |
| CFG-002 | No module presets | No preset concept in source | CONFIRMED |
| FIN-008 | Ghana tax defaults outdated | `database/seeders/TaxCodeSeeder.php:84` still seeds `COVID_LEVY` at 1%; `ChartOfAccountsSeeder.php:189` still creates account `2240 COVID-19 Health Recovery Levy Payable` | CONFIRMED |
| — | Tax profile model | Only `2026_04_27_110236_create_tax_codes_table.php` and a raw-material link migration exist. No profile/component/effective-date structure | CONFIRMED |
| STORE-002 | No checkout holds | No hold migration. The only `hold` match is `extend_counterparties_with_credit_hold`, which is an AR credit hold and unrelated | CONFIRMED |
| FIN-001 | No Quick Expense | No route, controller, service or view matching `quick.expense`/`QuickExpense` | CONFIRMED |
| CFG-005 | Storefront settings not org-scoped | `app/Storefront/Support/StorefrontSettings.php` is a static accessor over `App\Models\Setting` | CONFIRMED — and materially understated, see §6 |

No blueprint gap claim was found to be wrong. This is a notably better hit rate
than the earlier production-readiness audit, six of whose findings required
correction on evidence.

## 6. New finding — global settings are not organisation-scoped at all

**Severity: P1. Not recorded in the blueprint.**

The blueprint records CFG-005 as "storefront settings persistence is not org
scoped". The underlying problem is broader than the storefront.

`App\Models\Setting` maps to `system_configs`, whose full column list is:

```
id, key, value, created_at, updated_at
```

There is no `organization_id`. Every setting in the system is therefore global
across all tenants. This is not limited to presentation values — the same store
holds `sales.pricing.live_weight_price` and `sales.pricing.carcass_weight_price`,
which `PigSale::getLiveWeightRate()` / `getCarcassWeightRate()` read to price
real sales (`app/Models/PigSale.php:26-34`).

Consequence in a genuine multi-tenant deployment: one organisation changing its
pig price changes every other organisation's pig prices, and therefore their
revenue postings. It also means `finance.use_invoices_for_pig_sales` and similar
behavioural switches cannot differ per farm.

This is currently latent rather than active, because the deployment is
single-tenant in practice — `app/Models/Organization.php:10-17` says so
explicitly. It becomes a live money defect on the day a second tenant is
onboarded, which is precisely what the organisation-scoping work throughout this
codebase has been preparing for.

**Scope decision:** the Phase 1 feature-flag layer must be a real
organisation-scoped table and must not be built on `Setting`. Migrating the
existing global settings is tracked separately and is **not** silently folded
into Phase 1, because it changes the meaning of existing production rows and
needs its own backfill and compatibility analysis.

## 7. New finding — six dependency advisories disclosed after certification

**Severity: P1 at discovery. Resolved within this phase.**

`composer audit` reported 6 advisories affecting `league/commonmark` 2.8.3
(1 medium + 4 high + 1 medium; CVE-2026-71488 and CVE-2026-71478 among them). The
prior closure report recorded 0 advisories.

This is not a regression in prior work. Every advisory was **reported
2026-08-06**, one day before this phase. They did not exist at certification time.

`league/commonmark` is an indirect dependency, required by `laravel/framework`
v13.23.0 at `^2.8.1`.

Resolution: `composer update league/commonmark --with-dependencies`, which moved
it to a patched release. `composer audit` then reports:

```
No security vulnerability advisories found.
```

Regression check: full suite re-run after the update (see §9).

## 8. New finding — MariaDB gates run against an end-of-life server

**Severity: P1. Affects the credibility of prior certification claims.**

The prior certification recorded MariaDB **11.4.2**. The guarded gates do not use
that server.

`scripts/testing/test-mariadb-connection.sh` sources `.env.testing.mariadb.local`
and otherwise defaults to `127.0.0.1:3306`. Measured:

```
SAFE-DB-GUARD: Port: 3306
mariadb_version   selected_database
10.4.32-MariaDB   chipper_farms_test
```

Port 3306 is served by XAMPP's bundled MariaDB (`/opt/lampp/bin/mysql`,
`Ver 15.1 Distrib 10.4.32-MariaDB`). The privately provisioned 11.4.2 instance on
port 3399 is not running.

Two consequences:

1. **MariaDB 10.4 reached end of life in June 2024.** It receives no security
   patches. Certifying money-critical software against it is not equivalent to
   certifying against a supported release.
2. The concurrency suite is the evidence base for the oversell, double-confirm and
   outbox-claim guarantees. Those guarantees depend on InnoDB locking and
   isolation behaviour, which is exactly the area where 10.4 and 11.4 differ. Any
   claim of the form "concurrency is proven on MariaDB 11.4.2" is currently
   unsupported by the gates as configured.

**Scope decision:** this is recorded, not silently fixed. Re-pointing the gates at
a supported server is an infrastructure change that alters what every prior
concurrency result means, and it belongs in Phase 17 (local production
certification) with a full re-run, not buried in a baseline phase. It is entered
in the issue register as an open item.

## 9. Commands run and exact results

| Command | Result |
| --- | --- |
| `git rev-parse HEAD` | `e9bc329c3a33eb128e191a6a720354af300a5a95` |
| `git log 9cb8863..HEAD` | 2 commits, both tooling-only |
| `php artisan route:list --json` | 513 routes; finance 207 / storefront-admin 33 / shop 7 |
| `composer validate --no-check-publish` | `./composer.json is valid` |
| `composer audit` (before) | 6 advisories affecting `league/commonmark` |
| `composer update league/commonmark --with-dependencies` | Updated; advisories cleared |
| `composer audit` (after) | `No security vulnerability advisories found.` |
| `npm audit --audit-level=low` | No advisories reported |
| `php artisan test` (baseline, pre-update) | **1321 passed, 13 skipped, 0 failed**, 3803 assertions, 113.50s |
| `php artisan test` (post-update regression) | See §9.1 |
| `bash scripts/testing/test-mariadb-connection.sh` | Connects; **10.4.32-MariaDB**, db `chipper_farms_test` |

### 9.1 Post-update regression

Full suite re-run after the `league/commonmark` update:

```
Tests:    13 skipped, 1321 passed (3803 assertions)
Duration: 102.63s
```

Identical pass/skip/assertion counts to the pre-update baseline. **No regression.**

The dependency is a Markdown parser used by `Str::markdown`; blast radius is
limited to rendered Markdown, and no finance or storefront path depends on it.

## 10. Deferred baseline steps, and why

The prompt's Phase 0 lists `composer install` and `npm ci` and a `npm run build`.

- `composer install` was not re-run as a clean wipe: the tree is already installed
  and consistent, `composer validate` passes, and a reinstall would have discarded
  the security update applied in §7 unless sequenced after it.
- `npm ci` and `npm run build` are deliberately deferred to **Phase 17**, which the
  prompt defines as "from a clean checkout, execute full local release gates".
  That is the correct place for a destructive clean install, and running it twice
  adds no evidence.

Both are recorded here so the omission is explicit rather than silent.

## 11. Scope lock

Confirmed for implementation, in prompt order:

| Phase | Work | Blueprint anchor |
| --- | --- | --- |
| 1 | Organisation feature flags + presets | §32, §33 |
| 2 | Ghana 2026 tax profiles + snapshots | §13, §30 |
| 3 | Finance navigation + Overview + Needs Attention | §20, §22 |
| 4 | Quick Expense | §23 |
| 5 | Cash/Bank/MoMo workspace | §25 |
| 6 | Simple Purchase | §24 |
| 7 | Sales, contacts, invoices, receipts, credit notes, loans | §26–§29 |
| 8 | Storefront settings + catalog config | §34 |
| 9 | Guest cart + checkout | §38 |
| 10 | Checkout holds + reservations | §36, §37 |
| 11 | Pig to storefront | §35 |
| 12 | Storefront → finance integration | §41 |
| 13 | Refunds, returns, recovery UX | §42–§45 |
| 14 | Advanced feature gating | §16, §18 |
| 15 | Responsive UX + no-dead-end audit | §46, §55 |
| 16 | Security + full regression | §47–§49 |
| 17 | Local production certification | §60 |
| 18 | Final implementation review | §63, §64 |

Explicitly out of scope, per blueprint §63: promotions/coupons, multi-currency,
budgets, loyalty, breeding-service bookings, complex delivery optimisation,
multi-vendor marketplace, deposits/partial payments, advanced storefront
analytics, custom report builder, native mobile app.

## 12. Architecture decisions locked in this phase

1. **Feature flags get their own organisation-scoped table.** They will not be
   layered onto `Setting`, because `system_configs` has no tenant column (§6).
2. **Feature flags are never an authorization control.** Access requires
   feature enabled **and** permission granted **and** organisation scope valid,
   per blueprint §32 and prompt §11.
3. **No parallel accounting engine.** All new workflows are orchestration layers
   over `PostingEngine`, the outbox, and existing domain services.
4. **Tax work extends `TaxCode` rather than replacing it**, and is effective-dated
   so historical transactions stay explainable.
5. **Settings migration to org scope is its own workstream**, not a side effect of
   Phase 1.

## 13. Problems found

| # | Problem | Severity | Disposition |
| --- | --- | --- | --- |
| 1 | `system_configs` has no `organization_id`; all settings global including live sale pricing | P1 | Recorded; Phase 1 avoids the pattern; migration tracked separately |
| 2 | 6 new `league/commonmark` advisories | P1 | **Corrected in this phase** |
| 3 | MariaDB gates run on EOL 10.4.32, not the certified 11.4.2 | P1 | Recorded; re-point and re-run in Phase 17 |

## 14. Problems corrected in this phase

- `league/commonmark` updated to a patched release; `composer audit` clean.

## 15. Unresolved risks carried forward

| Risk | Carried to |
| --- | --- |
| Global settings leak across organisations | Separate workstream; must land before genuine multi-tenant rollout |
| Concurrency evidence rests on an EOL MariaDB | Phase 17 |
| Ghana tax changes need accountant/GRA confirmation | External certification |
| Paystack sandbox credentials still unavailable | External certification |
| CI has never executed on a real runner | External certification |

## 16. Exit criteria

| Criterion | Met |
| --- | --- |
| Baseline documented | Yes |
| No unexplained existing critical failure | Yes — 0 failures |
| Source baseline confirmed | Yes — route counts match blueprint exactly |
| Blueprint scope mapped to current components | Yes — §11 |
| No implementation performed | Yes — only a security dependency update |

**Phase 0 status: COMPLETE.**
