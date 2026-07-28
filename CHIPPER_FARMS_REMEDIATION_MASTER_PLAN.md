# Chipper Farms — Production Remediation Master Plan

**Created:** 28 July 2026
**Branch:** `remediation/chipper-farms-production-go`
**Baseline tag:** `pre-remediation-baseline` @ `4598a86`
**Source audit verdict:** NO-GO — 12 P0, 10 P1, 3 P2 (25 findings)
**Objective:** Resolve or formally disprove every finding with evidence, then
re-certify. A GO verdict is only issued if the evidence supports it.

---

## 1. Baseline (recalculated in Phase 0, confirmed unchanged)

| Metric | Value |
|---|---:|
| Findings | 25 (12 P0 / 10 P1 / 3 P2) |
| Tests passing | 1,161 |
| Tests skipped | 13 (42 skip call sites in source) |
| Assertions | 3,442 |
| Migrations | 188 |
| Non-vendor routes | 472 |
| App PHP files | 509 |
| Laravel | 11.45.1 (out of security support since 12 Mar 2026) |
| npm advisories | 13 (1 critical / 10 high / 2 moderate) |
| Composer advisories | **36 across 14 packages (4 high)** — newly measurable |

Two new findings raised in Phase 0: `CF-NEW-026` (Composer advisories, P0),
`CF-NEW-027` (non-functional migration try/catch, P2). Working total: **27**.

---

## 2. What Phase 0 Changed About Feasibility

The original audit was constrained by six environment limitations. Phase 0
removed three of them, which converts several "unverifiable" findings into
genuinely certifiable ones:

| Capability | Before | Now |
|---|---|---|
| Production DB engine | unavailable | **MariaDB 11.4.2 provisioned** (private instance, port 3399) |
| Fresh migration on MySQL/MariaDB | never verified | **188/188 PASS** |
| `composer audit` | blocked | **runs — 36 advisories** |
| npm registry | assumed blocked | reachable |
| Paystack sandbox | unavailable | **still unavailable** (no credentials) |
| Docker / root | unavailable | **still unavailable** |

Consequence: Phases 3, 4 and 7 can produce real locking and concurrency
evidence. Phase 5 cannot produce live-sandbox refund evidence.

---

## 3. Phase Sequence and Finding Map

| Phase | Scope | Findings |
|---|---|---|
| 0 | Baseline, safety controls, tracking | — (all reproduced) |
| 1 | Supported platform + dependency baseline | CF-P0-011, CF-P1-021, CF-NEW-026, dep part of CF-P0-012 |
| 2 | Identity, permissions, organisation isolation | CF-P0-001…005, CF-P1-019 |
| 3 | Database, numbering, scheduler, queue, outbox | CF-P0-010, CF-P1-013…016, CF-NEW-027, DB part of CF-P0-012 |
| 4 | Storefront concurrency and order state | CF-P0-006, CF-P2-023, CF-P2-024 |
| 5 | Paystack, refunds, COD, finance completion | CF-P0-007, 008, 009, CF-P1-020 |
| 6 | Livestock and operational workflows | CF-P1-018 |
| 7 | Testing, security, performance certification | CF-P1-017 |
| 8 | CI, deployment, monitoring, backup, recovery | CF-P1-022, CF-P2-025, ops part of CF-P0-012 |
| 9 | Independent final review and GO decision | all |

`CF-P0-012` is deliberately split across Phases 1, 3 and 8; it cannot close
until all three parts land.

---

## 4. Sequencing Rationale and Dependencies

**Why the platform upgrade goes first.** Every later phase adds code and
tests. Upgrading the framework afterwards would invalidate that work and force
a full re-test. Doing it first means all subsequent evidence is generated on
the release framework. The cost is that Phase 1 is the highest-risk phase and
must complete with a green suite before anything else starts.

**Hard dependencies:**

- Phase 2 (route/permission enforcement) must precede Phase 6 and Phase 7
  security tests, which assert the enforcement exists.
- Phase 3 (numbering) must precede Phase 4: storefront order references
  (CF-P1-014) are fixed by the atomic sequence service built in Phase 3.
- Phase 3 (outbox atomic claiming) must precede Phase 5: refund and COD
  accounting rely on the outbox not double-posting.
- Phase 5 depends on Phase 4's order state machine — refund and COD are
  transitions within it.
- Phase 8 CI depends on Phase 7 defining which suites are release gates.

**Known dependency inversion to manage:** Phase 2 requires organisation
backfill migrations, but the migration-safety work is Phase 3. The smallest
safe prerequisite (idempotent, resumable backfill pattern) is defined in
Phase 2 and generalised in Phase 3, with the dependency recorded rather than
silently reordering the gates.

---

## 5. Migration Strategy

Deployed migration history is treated as immutable. Corrections ship as **new**
corrective migrations.

Rules applied to every migration written during remediation:

1. Idempotent — safe to re-run after partial failure.
2. Resumable — batched backfills checkpoint their progress.
3. Conflict-safe inserts (`INSERT … ON DUPLICATE KEY UPDATE` / `insertOrIgnore`),
   never bare `INSERT … SELECT` on a table with a unique key.
4. Never drop a source table until the destination is validated.
5. Validate before adding `NOT NULL`; quarantine rather than guess.
6. Rollback path documented, and `down()` genuinely reverses.
7. Rehearsed on MariaDB: fresh, upgrade-from-prior-schema, mid-migration
   interruption, and rollback.

The `organization_id` non-null campaign (CF-P0-005) specifically must inventory
null rows, report them, and quarantine ambiguous records — it must not invent
ownership.

---

## 6. Testing Strategy

| Layer | Engine | Gate |
|---|---|---|
| Unit / Feature (fast) | SQLite `:memory:` | pre-commit |
| Integration | MariaDB 11.4.2 | release |
| Concurrency | MariaDB, **separate connections/processes** | release |
| Migration (fresh + upgrade + rollback) | MariaDB | release |
| Security / permission matrix | MariaDB | release |
| Browser E2E | MariaDB | release (Phase 7 feasibility TBC) |

Non-negotiable rule carried from the audit: **sequential calls inside one test
are not concurrency evidence.** Any concurrency claim must use genuinely
separate database connections or OS processes.

Every one of the 42 skip sites is inventoried in Phase 7 and must end as
executable, deleted-as-dead, or documented-irrelevant. None may remain hiding a
critical workflow.

---

## 7. Prohibited Remediation Shortcuts

Carried verbatim from the remediation brief and treated as hard constraints:
no disabling routes, weakening validation, bypassing permissions, deleting or
skipping failing tests, relaxing assertions to accept wrong behaviour,
swallowing exceptions, clamping invalid values, `--ignore-platform-reqs`,
faked provider responses in place of real integration, hard-coded credentials,
live Paystack calls, or suppressed audit output.

---

## 8. Deployment and Rollback Strategy (Phase 8 target)

- Release gate: CI must pass from a clean checkout before deploy is permitted.
- Deploy order: maintenance mode → migrate → cache warm → asset publish →
  worker restart → scheduler verify → health check → smoke test.
- Rollback covers code, migrations, queue payload compatibility, assets,
  config and schedules — and explicitly documents that **executed Paystack
  refunds cannot be undone by a code rollback**, so financial compensation is a
  manual runbook step.

---

## 9. GO Certification Requirements

A GO verdict requires **all** of the following. Any single failure forces
NO-GO or CONDITIONAL GO with the exact residual item named.

1. Every P0, P1 and P2 finding closed as *Confirmed Fixed* or *Finding
   Disproved*, each with test and evidence references.
2. Full suite green on **MariaDB**, not only SQLite.
3. Concurrency tests green using real parallel connections.
4. Fresh **and** upgrade **and** rollback migrations rehearsed on MariaDB.
5. Cross-organisation and direct-URL permission matrices green for all
   representative roles.
6. No unresolved high/critical dependency advisory without a written,
   justified release decision.
7. Refund, COD and finance reversal paths proven consistent end to end.
8. Schedules, workers, health checks, monitoring, backup/restore and rollback
   evidenced.
9. No critical workflow behind a test skip.
10. A fresh independent audit pass finds no new P0.

**Honesty rule.** If a requirement cannot be met because of an external
dependency — currently: Paystack sandbox credentials, a real production
database snapshot, and CI/Docker execution — the report states **NO-GO** (or
CONDITIONAL GO) and names precisely those external items. A GO verdict will not
be manufactured by narrowing the criteria.

---

## 10. Known External Certification Requirements (as of Phase 0)

| Requirement | Why it cannot be met in-repo | Affects |
|---|---|---|
| Paystack sandbox credentials | no `PAYSTACK*` keys in environment | Phase 5 exit criterion |
| Real production DB snapshot | not supplied; provisioned MariaDB is empty and ephemeral | Phase 3 upgrade rehearsal fidelity |
| Docker / root / CI runner | no docker binary, no passwordless sudo | Phase 8 CI execution proof |
| Live worker supervision host | no systemd/supervisor access | Phase 8 process proof |

These are tracked as `Blocked by External Requirement` in the live register and
will be reported honestly in Phase 9 rather than waived.
