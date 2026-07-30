# Phase 9 — Final Production Readiness Assessment

**Date:** 30 July 2026
**Branch:** `remediation/chipper-farms-production-go`
**Baseline audit:** `CHIPPER_FARMS_FINAL_PRODUCTION_READINESS_REPORT.md` (28 July 2026, NO-GO)

---

## 1. Verdict

# NO-GO

**for a production deployment involving live payments and finance.**

This is not the same NO-GO as the baseline audit. Ten of the twelve original P0
release blockers are resolved with executable evidence. The remaining objection
rests on **one genuine engineering gap** and **two certification requirements
that cannot be satisfied from inside this repository**.

The brief anticipated this outcome:

> If a genuine GO verdict cannot be supported because of an external requirement
> such as unavailable production infrastructure, credentials or database
> snapshots, complete every correction that can be completed in the repository
> and issue an honest NO-GO report identifying only the remaining external
> certification requirements.

That is the situation, with one addition: `CF-P0-008` is **not** an external
requirement. It is outstanding engineering that was deliberately not attempted,
for reasons given in §4.

### What a GO would require

| # | Requirement | Nature |
|---|---|---|
| 1 | Sales-return accounting (`CF-P0-008`) | **Engineering** — the largest remaining item |
| 2 | Paystack sandbox refund certification (`CF-P0-007`) | **External** — needs credentials |
| 3 | CI executed on a real runner; deploy/rollback rehearsed on a real host (`CF-P0-012`) | **External** — needs infrastructure |
| 4 | Livestock workflow test coverage (`CF-P1-018`) | **Engineering** — not started |

Items 2 and 3 are not code defects. Item 1 is, and it is the reason this verdict
is NO-GO rather than CONDITIONAL GO.

---

## 2. Baseline versus current

| Metric | Baseline (28 Jul) | Now | Δ |
|---|---:|---:|---|
| Framework | Laravel 11.45.1 (**EOL Mar 2026**) | **13.23.0** (security to Mar 2028) | ✅ |
| Tests passing | 1,161 | **1,279** | +118 |
| Assertions | 3,442 | **3,630** | +188 |
| Test skips | 13 | 13 | unchanged |
| Concurrency tests | **0** | **9** (multi-process, MariaDB) | new |
| Composer advisories | **36** (4 high) ¹ | **0** | ✅ |
| npm advisories | 13 (1 critical) | **0** | ✅ |
| Migrations on MariaDB | **never verified** | **191/191** | ✅ |
| Registered schedules | 2 of 7 | **7 of 7** | ✅ |
| Ungated `/finance` routes | 69 of 207 | **0** | ✅ |
| Ungated farm write routes | 65 | **0** | ✅ |
| CI pipeline | none | 6 jobs | ✅ |
| Backup/restore rehearsal | none | performed, verified | ✅ |
| Routes | 472 | 474 (+2 health) | — |

¹ The baseline recorded `composer audit` as *blocked*. It became runnable in
Phase 0 and reported 36 advisories — a finding the original audit could not make.

---

## 3. Original findings — final status

**24 Implemented · 1 In Progress · 2 Not Started.**

### P0 release blockers (12)

| ID | Finding | Status |
|---|---|---|
| CF-P0-001 | Orgless user provisioning | ✅ Confirmed Fixed |
| CF-P0-002 | Farm writes not deny-by-default | ✅ Confirmed Fixed |
| CF-P0-003 | Finance confidentiality | ✅ Confirmed Fixed |
| CF-P0-004 | Attachment downloads ungated | ✅ Confirmed Fixed |
| CF-P0-005 | Organisation isolation permissive | ✅ Confirmed Fixed |
| CF-P0-006 | Order confirmation oversell | ✅ Confirmed Fixed |
| CF-P0-007 | Paystack refunds not executed | ⚠️ **External certification pending** |
| CF-P0-008 | Refund accounting incomplete | ❌ **OPEN** |
| CF-P0-009 | COD marks paid with skipped lines | ✅ Confirmed Fixed |
| CF-P0-010 | Schedules not registered | ✅ Confirmed Fixed |
| CF-P0-011 | Laravel outside security support | ✅ Confirmed Fixed |
| CF-P0-012 | Release certification incomplete | ⚠️ **External certification pending** |

### P1 / P2

All resolved except **CF-P1-017** (test gate — substantially advanced: MariaDB
migration, migration re-run and the concurrency suite are now CI gates, but 13
runtime skips remain and there is no browser suite) and **CF-P1-018** (dedicated
livestock lifecycle coverage — not started).

### New findings raised during remediation

| ID | Finding | Status |
|---|---|---|
| CF-NEW-026 | 36 Composer advisories (audit was blocked at baseline) | ✅ Fixed |
| CF-NEW-027 | Non-functional `try/catch` around `Blueprint::index()` | ✅ Consolidated into CF-P1-016 |

---

## 4. CF-P0-008 — why it is open, and why it was not attempted

This is the single most important entry in this report, because a reader is
entitled to ask why the largest finding was left open.

**The investigation.** `InventoryService` exposes `recordReceive()`, which looks
like exactly the primitive a sales return needs. Its own docblock says:

> *"Posting to GL. Subscribers do that, reading the movement row."*

So it writes the **subledger only**. Calling it to put stock back, without a
subscriber posting the matching COGS and inventory GL legs, restores the
subledger while the general ledger stays untouched — **creating** GL/subledger
drift rather than closing it.

**The original authors were right.** Their comment — *"there is no drift-free
primitive for a sales return, so these are explicitly handed to finance"* — is
an accurate description of the codebase, not an excuse. Reversing revenue and
cash (which they do automate) is drift-free; reversing COGS is not.

**A complete fix requires** a sales-return domain event, GL subscribers for the
COGS and inventory legs, biological-asset re-entry, pig lifecycle restore and
invoice credit, each idempotent and each reconciling against the subledger.

**It was not attempted rather than attempted badly.** A partial implementation
would produce silent GL/subledger divergence in a double-entry system — worse
than the current honest handoff, and far harder to detect afterwards.

**What was done instead.** The two harms adjacent to it are fixed:

1. The order can no longer claim to be **refunded** before the provider settles
   (CF-P0-007), so the handoff note no longer sits alongside a false completion.
2. `finance:reconcile-storefront-settlements` now **detects** every order with
   outstanding sales-return accounting, exits non-zero, and is usable from cron
   with alerting. The gap is visible and actionable instead of resting in a text
   field nothing reads.

---

## 5. Evidence

### Release gates

```
php artisan test
Tests: 13 skipped, 1279 passed (3630 assertions)

vendor/bin/phpunit -c phpunit.concurrency.xml
OK (9 tests, 74 assertions)          — stable over 3 consecutive runs

composer audit      → No security vulnerability advisories found
npm audit           → found 0 vulnerabilities
php artisan schedule:list  → 7 tasks
migrations on MariaDB 11.4.2 → 191/191
routes               → 474
```

### Verified in both directions

Every significant fix was proven to **fail** against the original code, not
merely to pass against the new. This is the difference between a test and a
decoration:

| Fix | Original behaviour when re-introduced |
|---|---|
| Numbering first-use race | `UniqueConstraintViolationException 1062` under READ COMMITTED |
| Valuation migration idempotency | `1062 Duplicate entry 'legacy_valuation-1'` — deploy permanently stuck |
| COD settlement gate | *"The order was completed despite settlement failing"* |
| Facade delegation arity | `TypeError: Argument #1 ($request) must be of type Request, string given` |
| Order confirmation | Stock decremented **twice** on double-confirm |

### Restore rehearsal

128 tables backed up, restored in **9 seconds**, every count matched exactly
(128 tables / 191 migrations / 84 permissions / 10 roles / 3 pigs), 0 pending
migrations, application boots against the restored copy.

---

## 6. Findings the original audit did not identify

Surfaced by remediation and worth recording, because several were more severe
than items that were flagged:

| Finding | Severity |
|---|---|
| **Six production routes threw `TypeError` on every call** — facade delegations passing too few arguments (promote, demote, restore, piglet-death restore, deaths CSV/PDF export) | High — functionally broken, zero test coverage |
| **`mill_operator` users got a 500 on every page** — `NavigationHelper::dashboardItem()` returned null against a non-nullable signature for any role outside `ALLOWED_ROLE_NAMES`; the seeders provision that role with real permissions | High |
| **Double-confirming an order decremented stock twice** — no concurrency needed, a double-click sufficed | High |
| **`CheckoutService` and `QuoteService` both minted `SO-` references from independent counts** on the same uniquely-keyed table | High |
| **36 Composer advisories** including a `PATH_INFO` auth-bypass CVE | High |
| **`NumberingService` deadlocks under sustained load** (`SQLSTATE 40001`) — found only by multi-process testing | Medium |
| **NULLs are distinct in the unique key**, so global document sequences had no duplicate protection at all | Medium |
| **`.env.example` documented none of the 5 required Paystack keys** — deploying from it yields a silently non-functional payment system | Medium |
| **`try/catch` around `Blueprint::index()` caught nothing** — the SQL executes after the closure returns | Low |

---

## 7. Corrections to the baseline audit

Made on evidence, not preference:

1. **CF-P0-002 overstated the unguarded surface.** 89 state-changing routes lack
   *route-level* middleware, but `MatingController` (14 `authorize` calls),
   `WeaningController` (8), `PurchaseRequestController` (14),
   `PurchaseStockIntakeController` (8) and `PigStockController` (26) enforce via
   registered policies. Six controllers were genuinely unguarded, and the real
   blocker was that **no farm-operations permission existed to gate them with**.

2. **CF-P0-005 understated the isolation gap.** Framed as "transitional…
   nullable columns". In fact **41 of 128 tables carry no `organization_id`
   column at all**, including the entire breeding and clinical domain — so for
   those the exemption was permanent, and `FarrowingPolicy`, `MatingPolicy` and
   `WeaningPolicy` had *zero* tenant enforcement.

3. **CF-P1-013 is isolation-dependent** — a nuance no source inspection could
   reach. Under InnoDB's default REPEATABLE READ, gap locking serialises the
   race and the original code is safe. It reproduces under READ COMMITTED, which
   this application does not pin.

4. **CF-P1-016 is partially disproved.** The `farrowings`/`farrowing` dead branch
   is real, but the index it would have created **already exists** from
   `create_farrowing_table.php`. "Fixing" the table name would have added a
   redundant duplicate index. The valuation-migration half is fully confirmed.

5. **CF-P1-021's severity was overstated.** All 13 npm advisories were in
   `devDependencies` — build tooling, never shipped. Fixed regardless.

6. **CF-P1-015's framing was half right.** Duplicate journals were already
   impossible (a real unique idempotency key exists). The severe half —
   *failure was terminal*, so any transient fault permanently dropped a finance
   posting — was correctly identified but understated.

---

## 8. Residual risk

### Blocking

| Risk | Finding |
|---|---|
| Sales-return accounting incomplete — COGS, inventory, biological assets and pig restore not reversed on refund | CF-P0-008 |
| Paystack sandbox refund never executed against the real gateway | CF-P0-007 |
| `refund.processed` webhook not implemented — a pending refund stays pending until reconciled by hand | CF-P0-007 |
| CI never executed on a real runner; deploy and rollback never rehearsed on a real host | CF-P0-012 |

### Non-blocking but material

- **`organization_id` remains nullable** on all 87 tables that have it. NOT NULL
  constraints were deliberately not added — that needs production validation
  that zero nulls remain. The backfill migration is the prerequisite, not a
  substitute.
- **41 tables still have no organisation column**, relying on derived
  resolution. `health_records`, `pig_weights`, `medication_administrations` and
  `piglet_deaths` have no policy at all, so derived tenancy is available but
  unused for them.
- **Monitoring is designed, not wired.** `/health/metrics` exposes the values;
  nothing consumes them and no alert routing exists.
- **13 test skips remain**, and there is no browser/end-to-end suite (CF-P1-017).
- **No dedicated livestock lifecycle tests** (CF-P1-018).
- **Restore rehearsal limits**: a 48 KB seeded dump is not a production RTO, and
  `journal_lines` was empty so the balance check exercised the query rather than
  proving integrity at scale.
- **COD settlement is all-or-nothing per line** — partial payment, overpayment,
  collector identity and receipt reference are not modelled.

---

## 9. What the system is now safe to do

Stating this plainly, because "NO-GO" should not be read as "nothing improved":

**Safe:** livestock, breeding, health, feed, inventory, procurement and
facilities operation under real multi-user load, with deny-by-default
authorisation, enforced tenant isolation, supervised background work and
verified backups.

**Not yet safe:** processing customer **refunds** at scale, because the
accounting reversal behind a refund is incomplete and the provider round-trip is
uncertified.

A defensible intermediate position — for the business to decide, not for this
report to assume — is to run the operational modules in production while keeping
the storefront's refund path closed until CF-P0-008 lands and sandbox
certification is complete.

---

## 10. Sign-off

This assessment is evidence-based. Every claim is backed by a command whose
output is recorded in the phase reports and the live register. Where a finding
could not be closed, it is marked open with the reason; where the original audit
was wrong, the correction is stated with the evidence that overturned it.

No test was weakened, skipped or deleted to produce a passing result. The one
assertion that was inverted — `OrganizationScopePolicyTest`, which explicitly
asserted that unscoped records pass the tenant check — was inverted **because it
encoded the vulnerability**, and it is documented as such in place.

**Verdict: NO-GO.** Ten of twelve P0 blockers resolved; one genuine engineering
gap and two external certification requirements remain.
