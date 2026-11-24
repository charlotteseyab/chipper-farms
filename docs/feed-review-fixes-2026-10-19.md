# Feed review fixes — 19 October 2026

The five issues from the latest feed review are addressed:

1. Feed reset includes feed issue and reversal movements in its backup, restores affected group cost pools to their earlier snapshots, and preserves earlier livestock costs. It refuses a reset when later non-feed cost activity depends on those movements, before deleting anything. Collection and deletion share a transaction and organization lock.
2. Mixed purchase requests that still reference materials being removed block both dry runs and resets. Their material references remain intact.
3. Domain event cleanup uses every collected source, including stock count lines, vendor invoices, payments and allocations. Pending stock-count events cannot survive the deletion of their source lines.
4. Bulk production planning checks saved submission keys before forecasting again and returns the original quantities on retries. Forecasting, checking waiting plans and creating plans share the organization lock. An inventory change cannot turn a retry into a conflicting quantity or another plan.
5. Same-day recipe corrections append a revision. Date followed by descending ID determines the applicable version; earlier versions and existing plan snapshots remain intact. Setup and history queries use the same order.

## Database change

Apply `2028_11_13_000000_allow_same_day_feed_recipe_revisions.php` with the normal deployment migrations before saving same-day corrections. It replaces the unique effective-date constraint with an index, preserving all recipe rows. Rollback refuses if multiple revisions exist on the same date rather than deleting history. No application database was migrated or reset during this work.

## Regression coverage

`FeedModuleResetTest` covers pool cleanup, preservation of earlier livestock costs, refusal of dependent cost history and mixed purchases, and pending stock-count event cleanup. `FeedShortcutsTest` covers replay after stock covers all demand and same-day correction while a plan retains its original recipe snapshot.

Validation: 204 targeted feed, inventory, production, procurement and finance tests passed (957 assertions). Tests used SQLite in memory. The new migration's forward path was exercised by the test database; MySQL concurrency and migration rollback were not executed. Syntax checks passed for the reset service and migration. Repository whitespace checking reports an existing blank line at the end of `resources/css/app.css`, outside these fixes.
