# Phase 1 — Organisation Feature Flags and Module Presets

## Document control

| Field | Value |
| --- | --- |
| Phase | 1 — Organisation feature flags and module presets |
| Date | 2026-08-07 |
| Branch | `staging` |
| HEAD at phase start | `46e54d2` |
| Blueprint anchor | §32 (feature-flag architecture), §33 (presets), §53 (compatibility) |
| Prompt anchor | §10–§16 |
| Phase status | COMPLETE |

## 1. Scope

Build the organisation-scoped feature layer that every later phase depends on.
The blueprint (§32) and the prompt both require this before navigation changes,
for a concrete reason: hiding a screen without a flag layer produces a dead end
with no way back, and there is no mechanism to re-expose it.

Delivered: flag storage, a catalogue, a resolution service with dependency
handling, module presets, route-level gating, an administrator settings screen,
a permission, and a backward-compatibility backfill.

## 2. Architecture decisions

### 2.1 A dedicated table, not `system_configs`

The established pattern in this codebase is `Setting::set('some.key', $value)`
over `system_configs`. That was rejected here.

Phase 0 §6 established that `system_configs` has columns
`(id, key, value, created_at, updated_at)` and **no `organization_id`**. Storing
per-organisation module configuration in a table with no tenant column would
guarantee that enabling a module for one farm enabled it for every farm — the
exact defect this phase exists to avoid.

`organization_feature_flags` therefore carries `organization_id NOT NULL` with a
unique index on `(organization_id, feature_key)`.

### 2.2 An absent row means "not configured", not "disabled"

The service resolves a missing row to the **catalogue default**, not to `false`.

If an empty table resolved to false, the moment this migration ran every
organisation would lose every module until someone configured them. 193
migrations of working functionality would vanish at deploy. The backfill
(§2.6) then writes explicit rows so behaviour is recorded rather than inferred.

### 2.3 A feature flag is not an authorization control

Stated in blueprint §32, prompt §11, and logged in the blueprint's own gap
register as SEC-001. The required rule is:

```
feature enabled  AND  permission granted  AND  organisation scope valid
```

`EnsureFeatureEnabled` answers only the first clause. Every gated route still
carries its `permission:` middleware. `test_enabled_feature_still_enforces_permission`
is the regression guard: if someone ever "simplifies" a route by dropping
`permission:` because `feature:` is already present, that test fails.

### 2.4 The gate returns 404, not 403

A disabled feature is not an authorization failure — the surface is not part of
this organisation's product. 403 would confirm to a prober that the surface
exists and is merely switched off, which conflicts with prompt §118's
"cross-tenant resources should remain undiscoverable". 404 also reads correctly
to a legitimate user, who has no permission problem to fix.

### 2.5 Dependencies are applied, not merely validated

`enable()` walks the transitive `requires` closure and turns on ancestors first.
`disable()` walks the transitive dependants and turns them off.

Enabling Withholding Tax without Advanced Payables would offer certificates for
payments the organisation cannot make. Leaving WHT enabled after Advanced
Payables is switched off describes a configuration the application cannot
honour. Both directions are tested.

`usable()` re-walks the chain at read time, so a hand-edited or
partially-migrated row cannot open a surface whose prerequisites are off.

### 2.6 Existing organisations are backfilled to everything, not to Essentials

Essentials is right for a *new* farm. Applying it to an existing one would
remove, at deploy time and without warning, screens a current customer's
accountant uses today.

Blueprint §53 requires the rollout to be additive. The backfill therefore
enables the **entire catalogue** for every organisation that existed when the
migration ran. Moving to a simpler preset is an explicit administrator decision.

The backfill is idempotent and resumable: it skips organisations that already
have any row (so a re-run cannot overwrite later administrator choices), upserts
rather than inserts (so a partial run can resume), and chunks. `down()` deletes
only rows it could have written.

### 2.7 Presets are additive by default

`applyPreset()` turns on what the preset lists and touches nothing else.
Applying "Storefront Seller" must not silently destroy an accountant's Advanced
Accounting setup.

An explicit `$exclusive` flag performs a genuine reset. The UI must confirm
before requesting it.

Essentials → Growing Farm → Full Accounting form a strict superset ladder, which
is asserted by test. Storefront Seller and Procurement Advanced are **overlays**,
applicable on top of any rung — treating them as rungs would force a farm that
sells online to give up bank reconciliation.

### 2.8 Only features with real implementations are in the catalogue

The prompt lists candidate flags including multi-currency, budgets and e-VAT, and
also says "do not invent non-functional modules just to have toggles". Every key
in the catalogue was checked against the live route table first. Multi-currency,
budgets and e-VAT have no implementation in this codebase and are **not**
present. They can be added when the feature they gate exists.

### 2.9 Caching shape

Navigation resolves many keys per request, so the service caches. The cache is:

- **per instance, not static.** An earlier defect in this codebase
  (`EnsureFinanceAccess`) used a static memo keyed by role and went stale under
  `RefreshDatabase` and Octane. Static state outlives what it describes.
- **keyed by organisation id**, so one tenant cannot answer for another.
- **invalidated on every write.**

Registered as a singleton, which in Laravel means one instance per request and
one per test. Three tests cover this: cross-organisation isolation, cache
non-contamination, and read-after-write.

### 2.10 The settings screen is not gated by a feature flag

Deliberate. Gating the flag screen behind a flag means switching the wrong thing
off removes the only route back. `test_the_screen_stays_reachable_after_an_exclusive_reset`
guards it. Permission alone protects the screen.

## 3. Code delivered

| File | Purpose |
| --- | --- |
| `database/migrations/2028_09_01_100000_create_organization_feature_flags_table.php` | Flag storage, org-scoped, unique per (org, key) |
| `database/migrations/2028_09_01_100100_backfill_existing_organizations_to_full_accounting.php` | Additive backfill for pre-existing tenants |
| `app/Support/Features/FeatureCatalog.php` | Canonical feature definitions, dependency graph |
| `app/Support/Features/ModulePreset.php` | Five presets: three ladder, two overlay |
| `app/Models/OrganizationFeatureFlag.php` | Row model with audit relations |
| `app/Services/Organizations/OrganizationFeatureService.php` | `enabled/disabled/usable/enable/disable/configuration/applyPreset/dependencies/currentPreset` |
| `app/Http/Middleware/EnsureFeatureEnabled.php` | Route gate, AND-combined, 404 |
| `app/Http/Controllers/Settings/ModuleFeatureController.php` | Settings screen |
| `resources/views/settings/modules.blade.php` | Mobile-first settings UI |
| `database/seeders/OrganizationFeaturePermissionsSeeder.php` | `manage_feature_flags`, admin/superuser only |
| `bootstrap/app.php` | `feature` middleware alias |
| `app/Providers/AppServiceProvider.php` | Service singleton |
| `routes/web.php` | Three settings routes, permission-gated |
| `database/seeders/DatabaseSeeder.php` | Seeder registration |

## 4. Feature catalogue

31 features. Finance: 8 essential, 10 advanced. Storefront: 10 essential,
3 advanced.

Dependency edges (all asserted acyclic and pointing at real features):

```
finance.bank_reconciliation      → finance.cash_bank_momo
finance.procurement_approvals    → finance.simple_purchase
finance.advanced_ap              → finance.simple_purchase
finance.wht                      → finance.advanced_ap → finance.simple_purchase
finance.investor_management      → finance.advanced_accounting
finance.advanced_biological_...  → finance.advanced_accounting
finance.advanced_treasury        → finance.cash_bank_momo
finance.period_close             → finance.advanced_accounting
finance.customer_credit          → finance.contacts
storefront.*                     → storefront.enabled
```

## 5. Permission

`manage_feature_flags`, granted to **admin and superuser only** — not
farmmanager, accountant or treasurer. Turning a module off changes what every
user in the organisation can reach, and an exclusive preset apply can remove an
accountant's entire ledger surface in one action. Prompt §119 explicitly asks
for this to be a distinct permission rather than a broad `manage_finance`.

## 6. Tests

42 new tests across four files.

| File | Tests | Covers |
| --- | ---: | --- |
| `OrganizationFeatureServiceTest` | 14 | Absent-row semantics, audit, dependencies, org isolation, cache correctness, catalogue integrity |
| `ModulePresetTest` | 11 | Preset contents, superset ladder, additive vs exclusive, drift, backfill compatibility and idempotency |
| `FeatureRouteGateTest` | 8 | URL access when disabled, permission still enforced, ancestor gating, unknown key is a hard error |
| `ModuleSettingsScreenTest` | 9 | Render, permission, toggle, validation, preset apply, lockout guard |

## 7. Verification in both directions

Passing tests only prove code runs. Each critical guarantee was verified by
reintroducing the defect and confirming the correct test fails.

| Mutation | Expected failure | Observed |
| --- | --- | --- |
| Absent row resolves to `false` instead of catalogue default | `test_absent_row_resolves_to_catalogue_default_not_false` | 1 failed, 13 passed — correct test |
| Cache not keyed by organisation | isolation + cache-contamination tests | 2 failed, 12 passed — both correct tests |
| `disable()` does not cascade to dependants | `test_disabling_a_parent_cascades...` | 1 failed, 13 passed — correct test |
| `enable()` ignores dependency chain | `test_enabling_a_leaf_feature_pulls_in_its_whole_chain` | 1 failed, 13 passed — correct test |
| Route gate never blocks | URL-access + ancestor tests | 2 failed, 6 passed — both correct tests |

All mutations reverted; suite green afterwards.

## 8. Commands run and results

| Command | Result |
| --- | --- |
| `php -l` on all 9 new/changed PHP files | No syntax errors |
| `php artisan migrate --force` | 2 migrations applied |
| `php artisan test tests/Feature/Features/OrganizationFeatureServiceTest.php` | 14 passed (79 assertions) |
| `php artisan test tests/Feature/Features/ModulePresetTest.php` | 11 passed (220 assertions) |
| `php artisan test tests/Feature/Features/FeatureRouteGateTest.php` | 8 passed (10 assertions) |
| `php artisan test tests/Feature/Features/ModuleSettingsScreenTest.php` | 9 passed (20 assertions) |
| `php artisan test` (full, SQLite) | **1354 passed, 13 skipped, 0 failed** (4112 assertions) |
| `bash scripts/testing/run-mariadb-tests.sh --filter=Features` | 42 passed, 329 assertions |

Baseline before this phase was 1321 passed. 1354 − 1321 = 33, plus the 9
settings-screen tests written after that run, totalling 42 new.

## 9. Problems found and corrected

| # | Problem | Disposition |
| --- | --- | --- |
| 1 | MariaDB run reported 1 PHP 8.4 deprecation: `PigSaleWorkflowBaselineTest::makePig(): Implicitly marking parameter $ear as nullable` | **Corrected.** Pre-existing, unrelated to this phase, caught because the filter matched broadly. Becomes fatal in PHP 9. Blast radius checked repo-wide: this was the only instance. |

## 10. Deliberate non-goals

| Not done | Why |
| --- | --- |
| Navigation is not yet feature-aware | Phase 3. This phase builds the mechanism; consuming it is a separate, testable change. |
| Existing finance routes are not yet gated | Phase 14 (advanced feature gating), after the simplified surfaces exist. Gating them now would hide screens before replacements are built. |
| Migrating `system_configs` to org scope | Its own workstream. Changes the meaning of existing production rows and needs a backfill and compatibility analysis of its own. |
| Multi-currency, budgets, e-VAT flags | No implementation exists to gate. |

## 11. Unresolved risks carried forward

| Risk | Carried to |
| --- | --- |
| Global settings still leak across organisations | Separate workstream; must land before genuine multi-tenant rollout |
| Feature-disable close-out behaviour for open records is designed but not exercised end to end | Phase 14, where real routes are gated and open-record paths exist to test |
| Concurrent preset application under contention is not proven | Phase 16 concurrency pass. The unique index makes double-application structurally impossible, but that is an argument, not evidence. |

## 12. Exit criteria

| Prompt §16 criterion | Met | Evidence |
| --- | --- | --- |
| Organisation-scoped flags work | Yes | `OrganizationFeatureServiceTest` |
| Presets work | Yes | `ModulePresetTest` |
| Permissions remain authoritative | Yes | `test_enabled_feature_still_enforces_permission` |
| Routes not accessible by typing URL when disabled | Yes | `test_disabled_feature_is_not_reachable_by_typing_the_url` |
| Open workflows remain recoverable | Partial | Settings screen never self-locks; full open-record close-out deferred to Phase 14 with real gated routes |
| Tests pass on SQLite and MariaDB | Yes | 1354 SQLite, 42 MariaDB |

**Phase 1 status: COMPLETE.**
