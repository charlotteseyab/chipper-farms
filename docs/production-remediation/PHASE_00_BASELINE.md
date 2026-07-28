# Phase 0 — Baseline and Safety Controls

**Date:** 28 July 2026
**Branch:** `remediation/chipper-farms-production-go`
**Baseline tag:** `pre-remediation-baseline`
**Starting commit:** `4598a864bbe8371ff10065080ac7557ab6fb8650`
**Phase verdict:** COMPLETE — baseline established, no business logic changed.

---

## 1. Phase Objective

Establish an independently recalculated baseline, provision the safety and
source-control controls, stand up the tracking artefacts, and determine which
of the original audit's *environment-limited* items can now actually be
certified. No application behaviour was changed in this phase.

---

## 2. Runtime and Toolchain

| Component | Version |
|---|---|
| PHP (CLI) | 8.4.22 |
| PHP constraint (`composer.json`) | `^8.2` |
| Composer | 2.8.1 |
| Node | v20.19.0 |
| npm | 10.8.2 |
| Laravel (`composer.lock`) | v11.45.1 |
| MariaDB (provisioned, see §5) | 11.4.2-MariaDB-4 |
| Docker | **not available** |

---

## 3. Repository Inventory — Recalculated

The audit instructed that its counts must not be assumed still valid. They were
recalculated independently. **Every count matches the audit exactly**, so the
audit baseline is confirmed unchanged.

| Item | Audit | Recalculated | Match |
|---|---:|---:|:--:|
| Migrations | 188 | 188 | ✅ |
| App PHP files | 509 | 509 | ✅ |
| Controllers | 86 | 86 | ✅ |
| Models | 120 | 120 | ✅ |
| Policies | 11 | 11 | ✅ |
| Test PHP files | 182 | 182 | ✅ |
| Jobs | 1 | 1 | ✅ |
| Non-vendor routes | 472 | 472 | ✅ |
| Skip occurrences in tests | 42 | 42 | ✅ |

---

## 4. Baseline Command Results

### 4.1 Test suite

```
php artisan test   (after config/route/view clear)
Tests:    13 skipped, 1161 passed (3442 assertions)
Duration: 108.60s
```

Reproduces the audit's authoritative run (1,161 / 13 / 3,442) exactly.
Duration differs only because of machine load.

### 4.2 Routes and schedule

```
php artisan route:list --except-vendor   → Showing [472] routes

env CACHE_STORE=array php artisan schedule:list
  * * * * *  finance:process-events
  0 * * * *  php artisan storefront:sync-availability
```

Only 2 schedules active. **CF-P0-010 reproduced.**

---

## 5. Environment Limitations — Re-tested and Largely REMOVED

The original audit recorded six blocking environment limitations. Phase 0
re-tested each. **Three are now resolved**, which materially expands what can
be certified in later phases.

| Original limitation | Phase 0 result |
|---|---|
| MySQL/MariaDB unavailable → no production-engine migration proof | **RESOLVED** — private MariaDB 11.4.2 instance provisioned (§5.1) |
| `composer audit` blocked by network egress policy | **RESOLVED** — now runs; results in §6 |
| npm registry reachability | **RESOLVED** — reachable (HTTP 200) |
| Paystack sandbox credentials | **STILL BLOCKED** — no `PAYSTACK*` keys present in `.env` |
| Docker | **STILL UNAVAILABLE** — no docker binary, no passwordless sudo |
| Browser E2E driver | **NOT YET PROVISIONED** — to be assessed in Phase 7 |

### 5.1 MariaDB provisioning (how the DB limitation was removed)

The host has MariaDB 11.4.2 binaries but the system service is inactive and
there is **no passwordless sudo**, so the system instance cannot be started.
A *private* instance was therefore initialised into the session scratchpad and
started on a non-default port, requiring no elevated privileges:

```bash
mariadb-install-db --datadir=$SP/mysql/data --auth-root-authentication-method=normal
mariadbd --datadir=$SP/mysql/data \
         --socket=/tmp/claude-1000/cf-mysql.sock \
         --port=3399 --bind-address=127.0.0.1 \
         --log-error=$SP/mysql/error.log &
```

Note: the socket lives at a short path because the scratchpad path exceeds the
107-character UNIX socket limit.

Databases created: `chipper_farms`, `chipper_farms_test`, `chipper_farms_upgrade`.

**This instance is ephemeral and session-scoped.** It is adequate for
migration, locking and concurrency certification, but it is *not* evidence
about the customer's real production database. Phase 3 and Phase 9 must record
that distinction.

### 5.2 First production-engine migration proof

Never previously achieved. Run against MariaDB 11.4.2:

```
env DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3399 \
    DB_DATABASE=chipper_farms DB_USERNAME=root \
    php artisan migrate:fresh --force
```

Result: **all 188 migrations completed**; `migrations` table holds 188 rows;
128 tables created. This closes the "fresh migration on the production engine"
gap. Upgrade-from-snapshot and rollback rehearsals remain outstanding
(Phase 3).

---

## 6. Dependency Audit — New Evidence

### 6.1 Composer (previously BLOCKED — now available)

`composer audit` could not be executed during the original audit, so no
Composer advisory evidence existed. It now runs:

```
Found 36 security vulnerability advisories affecting 14 packages.
```

| Severity | Count |
|---|---:|
| high | 4 |
| medium | 25 |
| low | 6 |
| unscored | 1 |
| **Total** | **36** |

Affected packages: `dompdf/dompdf` (6), `guzzlehttp/guzzle` (7),
`guzzlehttp/psr7` (4), `laravel/framework` (3), `league/commonmark` (2),
`phpunit/phpunit` (1), `psy/psysh` (1), `symfony/http-foundation` (2),
`symfony/mailer` (1), `symfony/mime` (2), `symfony/polyfill-intl-idn` (1),
`symfony/process` (1), `symfony/routing` (2), `symfony/yaml` (3).

The four **high** severity advisories are the most release-relevant:

- `laravel/framework` — CRLF injection in default email rule (CVE-2026-48019).
- `symfony/http-foundation` — CVE-2025-64500, incorrect `PATH_INFO` parsing
  leading to **limited authentication bypass**. Directly compounds the
  Phase 2 broken-access-control findings.
- `symfony/mime` — CVE-2026-45067, email header / SMTP command injection via CRLF.
- `phpunit/phpunit` — CVE-2026-24765 (dev-only dependency).

This is **new evidence not present in the original register**. It is recorded
as new finding `CF-NEW-026` (§8) rather than folded silently into CF-P0-011,
because it is separately verifiable and separately closable.

### 6.2 npm (confirms the audit)

```
npm audit --audit-level=low
critical: 1, high: 10, moderate: 2, total: 13
```

Exactly matches CF-P1-021. Direct dependencies affected: `axios`, `postcss`,
`vite`. Critical: `shell-quote` (transitive).

---

## 7. Findings Reproduced by Direct Inspection

Phase 0 performs no fixes, but several findings were confirmed against the
running system rather than by source reading alone.

**CF-P0-010 (schedules not registered) — CONFIRMED.**
`app/Console/Kernel.php` declares 5 schedules (`weights:notify`,
`gestation:notify`, `notifications:process`,
`finance:reconcile-invoice-aggregates`, `finance:accrue-loan-interest`).
`bootstrap/app.php` uses Laravel 11's `withRouting(commands: routes/console.php)`,
so `App\Console\Kernel` is **never instantiated** — it is dead code. The two
schedules that *do* work are registered in service providers
(`FinanceServiceProvider.php:128-139`, `StorefrontServiceProvider.php:54-57`)
via `$this->app->booted()`. That provider pattern — which already uses
`withoutOverlapping()` and `onOneServer()` — is the correct remediation target.

**CF-P1-016 (migration recovery) — CONFIRMED, with impact amended.**
`2026_04_25_120000_add_legacy_reporting_indexes.php:54` guards on
`Schema::hasTable('farrowings')` while the real table is `farrowing`
(singular), so the guarded block never executes.

Verified on MariaDB: the intended index `farrowings_farrowing_date_idx` is
absent, while the control index `pigs_management_date_of_birth_idx` (same
migration, correct table name) **is** present — proving the migration runs and
that this specific branch is silently skipped.

*Impact amendment:* the `farrowing` table already carries
`farrowing_farrowing_date_index` on `farrowing_date` from an earlier
migration, so the intended query coverage exists under a different index name.
The defect is real dead code, but the audit's stated impact ("upgrade/recovery
can skip indexes") is already mitigated in practice. Phase 3 should fix the
dead branch and record the corrected impact rather than inflating it.

*Additional latent defect found:* the same migration wraps
`$table->index(...)` in `try/catch` inside the `Schema::table()` closure. The
closure only *queues* Blueprint commands; SQL executes after the closure
returns, so those `try/catch` blocks cannot catch duplicate-key errors. The
guard is non-functional. Recorded as `CF-NEW-027`.

**CF-P0-005 (route binding) — CONFIRMED.** `bootstrap/app.php:23-37` binds
`pig`, `farrowing`, `mating`, `weaning` through global `findOrFail` with no
organisation scoping. The in-file comment even states real authorization was
deferred to a later phase.

---

## 8. New Findings Raised in Phase 0

| ID | Severity | Title | Basis |
|---|---|---|---|
| CF-NEW-026 | P0 | 36 Composer advisories across 14 packages, incl. 4 high (auth-bypass in `symfony/http-foundation`, CRLF in `laravel/framework` and `symfony/mime`) | `composer audit`, newly executable |
| CF-NEW-027 | P2 | Non-functional `try/catch` around Blueprint index creation in `add_legacy_reporting_indexes` | Source + MariaDB verification |

Both are appended to the live register.

---

## 9. Source Control Controls

- Working tree at start: clean except two pre-existing untracked files
  (`FINANCE_SMOKE_TEST(1).md`, `FarmSystemCodebasefinal.zip`) — matches the
  audit's recorded state. Neither was modified.
- Branch `remediation/chipper-farms-production-go` created from `staging`.
- Tag `pre-remediation-baseline` created at `4598a86`.
- The three authoritative audit files are **unmodified**; verified by
  `git status` showing no changes to them.
- `FarmSystemCodebasefinal.zip` will **not** be committed.

---

## 10. Tracking Artefacts Created

| Artefact | Purpose |
|---|---|
| `CHIPPER_FARMS_REMEDIATION_MASTER_PLAN.md` | Phase sequence, mapping, strategies, GO criteria |
| `CHIPPER_FARMS_REMEDIATION_STATUS.csv` | Live register: all 25 original rows + 16 tracking columns |
| `docs/production-remediation/` | Per-phase evidence reports |
| `docs/operations/` | Runbook destination (Phase 8) |

The status register was generated programmatically from the original issue
register so that no row could be lost or reworded; all 25 findings carry over
with 43 columns total.

---

## 11. Exit Criteria

| Criterion | Result |
|---|---|
| All three audit files read in full | ✅ |
| All 25 findings in live tracker | ✅ (25 rows verified) |
| Baseline evidence recorded | ✅ |
| Codebase behaviour unchanged | ✅ (no source edits; suite reproduces 1,161/13) |
| Remediation branch exists | ✅ |
| Master plan exists | ✅ |
| No audit file removed or overwritten | ✅ |

**Phase 0 verdict: PASS.**

---

## 12. Carry-Forward Risks

1. **Paystack sandbox credentials absent.** Phase 5's exit criterion "the
   application can submit a real Paystack sandbox refund" cannot be met without
   them. Phase 5 can implement and fully unit/contract-test the refund path
   against a fake client, but live sandbox certification will remain an
   external requirement.
2. **The MariaDB instance is ephemeral and not the customer's production
   database.** Fresh-migration proof is genuine; upgrade-from-real-snapshot is
   not obtainable here.
3. **No Docker and no root.** CI must be authored as configuration
   (Phase 8) and validated by local equivalence, not by executing the runner.
4. **Baseline suite passes but is not a release gate** (CF-P1-017): SQLite
   in-memory, array cache/session, sync queue. Any Phase 3+ concurrency claim
   must run against MariaDB, not the default suite.
