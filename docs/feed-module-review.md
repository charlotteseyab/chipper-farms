# Feed module review

The existing feed workflow is retained: FeedPlanningController / planning-reports, FeedProductionController, ProduceFeedBatchService, IssueFeedToGroupService, RawMaterial inventory and stock logs, InventoryService movements/balances, group cost pools, domain-event finance posting and PurchaseRequestController.

Existing planning uses active PigManagement records and age limits 56 / 98 / 154 days, breeding sex and mating status. It hard-codes fallback feeding rates, uses 30 days for every month, does not recognise lactation and converts gross demand to ingredients without subtracting finished feed. Recipes are mutable quantity-per-100kg rows. Completed batch items already preserve actual ingredient quantities/costs. Finished feed already exists in inventory_balances; no second stock ledger is required. Issues already target groups and post inventory, costing and finance transactionally.

Extensions: append-only effective-dated standards, audited stage overrides, recipe-version snapshots, current-state planning by date range/stage/feed/room/group, shared-feed aggregation before stock subtraction, shortage purchase-request prefilling, production plans confirmed through the existing producer, request idempotency and planned-consumption snapshots/variance on existing feed issues.

Retain existing age boundaries; do not import the old hard-coded daily-rate fallbacks as approved standards. Missing standards should be visible exceptions. Add lactation from existing farrowing/date-weaned records. No new animal status table. Group-level issuance remains the accounting unit because existing feed cost allocation uses group pools.

Additive database changes will preserve legacy batches/issues: new standards/override/recipe-version records and nullable planning/snapshot/idempotency fields on existing operational records. Legacy batches retain their existing item snapshots. Historical issues lacking a planned snapshot must show Unknown, not reconstructed values from today's animals.

Risks: legacy material quantities and authoritative inventory balances can differ; planning and completion must agree about usable stock. Existing stock movements prevent replay of a source ID but duplicate HTTP submissions create new IDs, so submission keys are required. Real-data migration remains independently blocked by unresolved prior ear-tag conflicts; do not bypass that migration to enable production deployment. Lifecycle/date-only data and unregistered litter piglets require explicit visibility rather than invented forecasts or double-counting.
