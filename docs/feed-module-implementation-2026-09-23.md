> Update: the farm confirmed colour-based identity. The tag and feed migrations have now succeeded. See [colour-identity migration results](ear-tag-colour-identity-2026-09-23.md). Earlier blocker/deployment statements below describe the state before that clarification.

# Feed module upgrade — implementation and validation

Date: 23 September 2026. Status: **Ready for farm UAT on an isolated environment; not deployed to the source database.**

## A. Existing functionality reviewed

The pre-implementation findings are in [feed-module-review.md](feed-module-review.md). Existing planning, recipes, batch mixing, raw materials and stock logs, unified inventory balances/movements, group feed issues, group cost pools, purchase requests, finance domain events, permissions and audit logging were retained. Animal eligibility uses the existing registration, sale, death and archive relationships. Breeding stage resolution uses existing mating, farrowing and weaning records; rooms, structures and groups use existing relationships.

The source-data review found 2 feed types, 2 legacy recipe lines, 13 raw materials, 3 stock logs, 3 inventory balances/movements and 3 purchase requests; no batches, batch items, feed issues or groups. No approved feeding rates were inferred from previous hard-coded defaults.

## B. Changes implemented

- Existing Planning Reports becomes Feed Planning & Dashboard: today, seven days, fourteen days, calendar month and custom inclusive periods; stage, feed, group, room and structure filters.
- Append-only feeding standard versions, effective dates, active/inactive status and configurable kg/animal/day. Previous versions cannot be edited or deleted through their models.
- Existing age boundaries retained: piglet through day 56, weaner through 98, grower through 154, then finisher. Breeding males are boars; females use confirmed pregnancy, active farrowing and weaning. Other sows remain an explicit configurable stage. Missing/invalid information raises a planning exception.
- Audited manual stage overrides record previous/new stage, reason, operator and timestamp. Clearing an override returns to automatic determination.
- Aggregate demand across stages sharing a feed before subtracting usable finished inventory once. The unified ledger is authoritative, including where an old raw-material cache differs.
- Recipe versions validate distinct active ingredients in kg and an exact 100% total. Versions and production snapshots preserve formulations; legacy recipes remain intact.
- Ingredient requirements derive from net production. Positive shortages open the existing purchase-request review form with material, quantity, period and explanatory notes. The approval workflow is unchanged.
- Production plans have no stock effect. Confirmation records actual ingredients, finished yield, operator/date and the pinned recipe, then reuses existing inventory, stock logs, finance events and audit services in one transaction.
- Production and feed-issue submission keys prevent duplicate posting. Reusing a key with different principal quantities/references is rejected. Concurrent retry behavior was tested on MariaDB.
- Feed issues retain the first daily group/feed target and feeding-standard/population snapshot. Repeated deliveries do not multiply that daily target. Legacy unknown targets stay unknown.
- Consumption reports show daily/group/feed totals, saved stages, variance and percentage. Stage/location filters select whole matching group deliveries; they do not fabricate a split of a mixed group's actual consumption.
- Configurable variance percentage and finished-feed coverage alerts; no invented default operational thresholds. Missing configuration and shortages remain visible. Existing raw-material reorder alerts remain in use.
- Stock adjustments require a reason in the existing form/controller. Existing movement/audit records remain the history.
- Existing permissions protect configuration, production, overrides, reports and issues; feed navigation respects permissions. Browser review corrected an empty feed-selection JavaScript expression and added footer clearance to the affected pages.

## C. Database changes

Migration: `2028_10_14_000000_extend_feed_planning_and_history.php`.

New tables: `feeding_standards`, `feed_stage_overrides`, `feed_recipe_versions`, `feed_production_plans`. Foreign keys preserve references; unique organization/stage/effective-date and organization/feed/effective-date constraints prevent conflicting versions. Production plans have unique organization/submission keys.

Existing `feed_batches` gain nullable submission key, planned quantity and recipe snapshot. Existing `feed_issues` gain nullable submission key, planned quantity and planning snapshot. Both have unique organization/submission-key indexes. `feed_types` and `raw_materials` gain `is_active`, default true. Batch quantity and ingredient quantity/cost columns are widened to preserve ledger precision.

No historical feeding rates or recipes were invented/backfilled. Old batch items remain the actual ingredient record; old issues have unknown planned quantities. Rollback refuses to discard populated new history. Empty rollback is supported; widened decimal columns remain wide to avoid rounding losses.

The feed migration passed **up → down → up** on an isolated copy of all **148 source tables**, with original values compared after each step. Schema: `chipper_farms_feed_rehearsal_16f67263`. This explicitly tests the feed migration alone; it does **not** claim the full deployment migration chain is unblocked. The source database was not migrated.

## D. Files changed

The list below covers this feed task, excluding the pre-existing ear-tag work. No application files were removed.

- `app/Helpers/NavigationHelper.php`
- `app/Http/Controllers/Feed/FeedIssueController.php`
- `app/Http/Controllers/FeedPlanningController.php`
- `app/Http/Controllers/FeedProductionController.php`
- `app/Http/Controllers/PurchaseRequestController.php`
- `app/Http/Controllers/RawMaterialController.php`
- `app/Http/Requests/Feed/StoreFeedIssueRequest.php`
- `app/Models/FeedBatch.php`
- `app/Models/FeedBatchItem.php`
- `app/Models/FeedIssue.php`
- `app/Models/FeedProductionPlan.php`
- `app/Models/FeedRecipeVersion.php`
- `app/Models/FeedStageOverride.php`
- `app/Models/FeedType.php`
- `app/Models/FeedingStandard.php`
- `app/Models/RawMaterial.php`
- `app/Models/Setting.php`
- `app/Services/Feed/FeedConfigurationService.php`
- `app/Services/Feed/FeedPlanningService.php`
- `app/Services/Feed/FeedProductionPlanService.php`
- `app/Services/Feed/FeedRecipeService.php`
- `app/Services/Feed/FeedStageResolver.php`
- `app/Services/Feed/IssueFeedToGroupService.php`
- `app/Services/Feed/ProduceFeedBatchService.php`
- `app/Services/Inventory/AdjustRawMaterialService.php`
- `database/migrations/2028_10_14_000000_extend_feed_planning_and_history.php`
- `docs/feed-module-implementation-2026-09-23.md`
- `docs/feed-module-review.md`
- `resources/views/feed/issues/create.blade.php`
- `resources/views/feed/issues/show.blade.php`
- `resources/views/feeds/feed-production/index.blade.php`
- `resources/views/feeds/planning-reports/index.blade.php`
- `resources/views/feeds/purchase-requests/create.blade.php`
- `resources/views/feeds/raw-materials/index.blade.php`
- `routes/web.php`
- `scripts/testing/rehearse-feed-upgrade.php`
- `scripts/testing/run-feed-browser.php`
- `tests/Browser/FeedWorkflowTest.php`
- `tests/Concurrency/FeedWorkflowConcurrencyTest.php`
- `tests/Feature/Feed/FeedPlanningUpgradeTest.php`
- `tests/Feature/Operations/RawMaterialStockWorkflowBaselineTest.php`

## E. Calculations

| Quantity | Calculation |
|---|---|
| Daily demand | Eligible current animal count × effective daily kg/animal |
| Seven-day demand | Sum daily demand across seven days; constant rate gives daily × 7 |
| Calendar month | Sum each date in the selected calendar month; constant rate gives daily × actual days in month |
| Custom period | Inclusive start/end dates, 1–366 days; each effective standard applies only on its applicable dates |
| Production | max(0, aggregated feed demand − available finished feed) |
| Ingredient requirement | Production × ingredient percentage / 100; aggregate shared ingredients before stock comparison |
| Material shortfall | max(0, required ingredient quantity − available ingredient stock) |
| Purchase suggestion | Positive shortfall; round upward to the existing purchase form's 2-decimal precision |
| Variance | Actual group/feed daily issues − first saved daily target |
| Percentage | Variance / target × 100 when target > 0; otherwise unknown/not applicable |
| Coverage | Available finished feed / average daily requirement; approximate stock-out is today + whole covered days |

Forecasts explicitly hold today's population/stages constant. Daily rates can change over the forecast according to effective dates. Ingredient planning uses the recipe at the start of the plan for one proposed production quantity; it is not a scheduled multi-recipe manufacturing forecast. No wastage, safety-stock or weight thresholds were invented.

## F. Integration and historical behavior

The central FeedPlanningService is reused by the dashboard, shortage purchase requests and issue snapshots. New services coordinate existing inventory posting rather than maintaining another ledger. Stock and costs are checked under transaction locks. Final audit failures roll back operational records and inventory together. Existing finance subscribers continue processing the original domain events.

Issued feed is still costed into existing group pools. Historical snapshots retain counts, stages, rates, standard IDs, relevant location membership and group/feed names. Production retains recipe version/formulation, expected quantity and actual ingredient rows. Later animal exits, overrides and configuration changes do not recalculate these records. Changing current stock changes a forecast, not completed records.

## G–H. Tests and results

- Full regression: **2,104 passed, 14 skipped, 6,771 assertions**. This includes animal, lifecycle, sales/death, purchases, inventory, permissions, reporting and dashboard suites. The skips are existing environment-dependent tests, not removed failures.
- Final focused feed/stock regression after history and browser fixes: **56 passed, 251 assertions**. The final new upgrade suite also passed **23 tests / 133 assertions**, including a 1,001-animal decimal-demand calculation and zero-target percentage handling.
- New `FeedPlanningUpgradeTest`: standards and effective versions; missing configuration; age boundaries and breeding lifecycle; overrides; animal exits; shared finished-feed subtraction; daily/week/month/leap-month calculations; shortages and purchase prefilling; recipes/units/inactive materials/tenant isolation; actual production; issue retries and daily aggregation; positive/negative/undefined percentage variance; immutable legacy targets; audit-failure rollback; permission denial; rendering and alerts; large population and decimals.
- Updated stock-workflow regression keeps its insufficient-stock assertion and supplies the newly required reason. A new test verifies missing reasons leave stock unchanged. No working test was removed.
- Real MariaDB concurrency: **1 test, 18 assertions**. Four production requests with one key yield one batch; four issue requests with one key yield one issue; final balances reconcile (100 raw → 80 raw, 20 finished → 15 finished).
- Chrome walkthrough: **1 test, 15 assertions**. Actual browser controls configure a standard, create a plan, confirm production, issue feed and show variance. Mobile/desktop screenshots are generated in `tests/Browser/screenshots/`.
- Existing-data migration rehearsal: passed up/down/up and original-value comparisons on the isolated copy.
- Frontend production build passed. Targeted PHP formatting and `git diff --check` passed.

The initial full run had one failure because the existing insufficient-stock fixture omitted the now-required reason; it was corrected without weakening its assertion. Initial browser runs exposed fixed-header/footer click positioning and an empty-selection expression; these were corrected and the walkthrough passed. There are no outstanding failures in the reported final runs.

Reproduction commands:

```sh
php artisan test --compact
php artisan test --compact tests/Feature/Feed/FeedPlanningUpgradeTest.php
php scripts/testing/rehearse-feed-upgrade.php
php scripts/testing/rehearse-feed-upgrade.php --concurrency
php scripts/testing/run-feed-browser.php
npm run build
```

The rehearsal reads the configured source and writes only a newly named copy. The concurrency runner uses the guarded concurrency database. The browser runner creates a disposable SQLite database and local server, leaving the configured source untouched.

## I. Functional walkthrough and manual reconciliation

Representative values below are **test inputs, not approved farm rations**.

One 120-day growing animal × 2 kg/day = 2 kg/day, 14 kg/seven days and 56 kg for February 2026. A 70% maize / 30% soya recipe totals 100%; 14 kg requires 9.8 kg maize + 4.2 kg soya. Starting with 1,000 kg of each ingredient, creating a plan leaves both unchanged. Confirmation leaves 990.2 kg maize, 995.8 kg soya and 14 kg finished feed. Issuing 3 kg leaves 11 kg finished feed. The saved daily plan is 2 kg; variance is +1 kg / +50%. Production cost at 2/kg maize and 4/kg soya is 36.40, or 2.60/kg; issue cost is 7.80. These calculations were independently reconciled against the browser fixture and stock/audit assertions.

Shared-feed example: grower 2 kg/day + weaner 1 kg/day = 21 kg/seven days. With 10 kg finished stock, production is 11 kg, requiring 7.7 kg maize + 3.3 kg soya. Stock is deducted once across both stages. February demand is 84 kg. With a 1,000 kg/day test rate, seven-day maize demand is 4,900 kg; 1,000 kg stock leaves a 3,900 kg purchase suggestion. The existing purchase form was verified to show that suggestion for review.

Standards, stages/counts, daily/week/month calculations, recipe totals, ingredients, shortages, planning/confirmation, raw/finished balances, feed issues, variance, dashboard/report output, permissions and audit entries were exercised through browser and integration tests. Stage-change and animal-exit checks preserve old issue snapshots. This is an engineering walkthrough with synthetic farm fixtures; it is **not** farm-operator acceptance/sign-off.

## J. Outstanding decisions and deployment constraints

1. Configure approved farm rates, feed mappings and optional alert thresholds before relying on demand totals. No hard-coded defaults were promoted to approved standards.
2. Unregistered litter piglets are explicitly excluded. Confirm whether and how litter-level creep feeding should be counted without double-counting individually registered animals. No unsupported litter rule was introduced.
3. Actual consumption is recorded by existing groups. Mixed-stage/mixed-room group delivery totals cannot be uniquely allocated by stage/room without additional operational input. Reports label this limitation rather than presenting inferred allocations as fact.
4. Backdated issues capture today's population with the standard effective on the issue date; this is disclosed on the issue form. They are not a reconstruction of an unavailable historical herd census. Legacy issues without targets stay unknown.
5. The prior ear-tag migration conflicts still block the complete source deployment chain. Resolve and rehearse those separately; the successful feed-only rehearsal does not authorize bypassing them.
6. Farm UAT must confirm the approved feeding setup, group organization, lifecycle data and real stock reconciliation. No source production data was changed and no production release is claimed.

## K. Final status

**Ready for farm UAT in an isolated environment.** The implemented planning → inventory → production → consumption path and representative calculations have been verified; farm acceptance and the pre-existing deployment blockers remain outside the completed engineering validation.
