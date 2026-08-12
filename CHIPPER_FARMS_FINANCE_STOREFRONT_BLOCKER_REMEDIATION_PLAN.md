# Chipper Farms Finance + Storefront Blocker Remediation Plan

Date: 2026-08-12
Branch: remediation/finance-storefront-final-blockers
Baseline commit: 3ed95b263bbcdd1be00b099cfa63c709e33ab51c

## Objective

Remediate repository-level P0/P1 blockers from the final independent go/no-go audit and prepare the repository for external certification. This document is not a production GO.

## Scope

1. Reproduce and classify the audit failures without destructive access to the normal `.env` database.
2. Repair stale-cache failure modes and provide a guarded production-cache test runner.
3. Remove obsolete conditional skips for implemented finance route and service surfaces.
4. Verify the default Laravel test suite, targeted finance/security/storefront tests, Node audit, frontend build, and local MariaDB gates where available.
5. Record evidence and remaining external certification items.

## Safety Rules

Approved disposable database names only:

- `chipper_farms_test`
- `chipper_farms_concurrency_test`
- `chipper_farms_upgrade_test`
- `chipper_farms_restore_test`

Normal `.env` application database access must not be used for destructive test operations.

## Work Phases

See `docs/finance-storefront-blocker-remediation/PHASE_00_BASELINE_AND_FAILURE_CLASSIFICATION.md` through `PHASE_13_FINAL_REMEDIATION_REVIEW.md`.
