# Chipper Farms Remaining Remediation Plan

Date: 2026-07-31  
Branch: `remediation/remaining-production-blockers`  
Baseline tag: `pre-remaining-production-blockers`

## Scope

This plan covers the remaining audit blockers from the final GO/NO-GO review:

- isolated MariaDB/XAMPP certification databases and safety guards;
- MariaDB migration, schema, and concurrency proof;
- sales-return credit-note documents and idempotent revenue reversal;
- Paystack refund settlement webhook handling;
- CI alignment with the guarded local test entry points;
- external certification runbook and evidence register.

## Execution Phases

| Phase | Area | Target |
| --- | --- | --- |
| 00 | Baseline and safety | Preserve baseline, avoid production DB/archive damage |
| 01 | MariaDB isolation | Add local examples and hard database guard |
| 02 | Credit notes | Create credit-note header/line tables, models, service, posting flow |
| 03 | Paystack refunds | Reconcile authenticated refund settlement/failure webhooks |
| 04 | Concurrency | Run real MariaDB multi-process suite on isolated DB |
| 05 | Scheduler/queues | Verify registered schedules and outbox tests |
| 06 | CI | Wire guarded MariaDB and concurrency scripts into CI |
| 07 | Backup/restore | Keep runbook evidence pending external environment |
| 08 | Skips | Close SQLite-only schema skips with MariaDB evidence |
| 09 | Security | Re-run dependency and route authorization gates |
| 10 | Closure | Produce evidence, issue register, and external certification runbook |

## Exit Criteria

Local engineering closure requires passing default tests, targeted MariaDB tests, concurrency tests, dependency audits, production asset build, route/event/schedule listing, and cache compilation.

External production certification remains separate and must be performed with real secrets, Paystack sandbox/live test credentials, off-host backup storage, production-like deployment, and rollback proof.
