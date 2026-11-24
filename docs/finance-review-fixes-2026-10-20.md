# Finance review fixes — 20 October 2026

All seven findings from the finance review are addressed.

1. Ledger queries retain previously posted originals after reversal. Their reversing entries offset them on their own posting dates. Rejected drafts remain excluded, despite sharing the `reversed` status. The same rule is used by trial balance, financial statements, cash, treasury, investor reporting, biological-asset reconciliation, subledger reconciliation and bank book balances.
2. Posting and approval reject closed or locked fiscal years even when the individual period remains open. Posting validates within a transaction and locks the fiscal year, matching the lock used by year-end close/reopen.
3. Bank reconciliation adds outstanding deposits and subtracts outstanding withdrawals when computing bank balance minus adjusted book balance. Valid uncleared deposits and withdrawals can reconcile.
4. Journal reversal reloads and locks the original batch before checking its status or building the reversal, preventing stale requests from creating another manual reversal.
5. Approval reloads and locks the batch and reuses the posting engine's account validation, including active status, organization, currency and manual-entry restrictions. Rejection also checks the locked, current status.
6. Soft-lock bypass ignores only soft locks. Any applicable active hard cutoff remains enforced.
7. Each year-end closing cycle receives a separate history row and idempotency key. Reopening and reversing occur atomically under the fiscal-year lock. A failed reversal rolls back the reopened status. Closing calculations exclude closing journals and their reversals, including when the reversal occurs in the following year. The year-end screen selects the latest cycle.

## Deployment

The normal deployment migrations must include `2028_11_14_000000_allow_year_end_closing_cycles.php`. It replaces the single-closing-per-year unique constraint with an index. The service serializes close operations on the fiscal-year row and rejects an existing completed cycle. Rollback refuses when multiple cycles exist instead of deleting accounting history.

No application database was migrated or reset during implementation. Report queries reflect the corrected logic when run again; previously stored close-pack snapshots remain historical records and are not rewritten.

## Validation

Regression coverage includes current and historical reversed balances, rejected drafts, stale reversal/rejection requests, account deactivation/currency/manual-permission changes, closed and locked years, overlapping hard/soft locks, uncleared deposit and withdrawal arithmetic, and closing again after a reversal in a later fiscal year.

Results: the finance, costing and storefront finance integration suite passed **1,043 tests (3,137 assertions), with 6 skipped**. A subsequent targeted year-end run passed **8 tests (25 assertions)**, including the additional rollback check and cross-year reclose scenario. Syntax and whitespace checks passed for the changes; the new regression file and migration were formatted with Pint.

Tests ran on SQLite in memory and exercised the migration's forward path. Live MySQL/MariaDB concurrency and migration rollback were not executed, so the tests establish business logic and stale-request behavior rather than real concurrent lock behavior.
