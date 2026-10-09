# Feed usability and data review — 23 September 2026

## Findings and changes

The upgraded planning page mixed daily farm-wide indicators, selected-period forecasts, consumption history and configuration. Wide tables repeated daily/weekly/monthly figures; setup occupied the same screen as daily decisions. Production plans appeared after recipe administration and production history. Issue previews used the whole group's animal count next to one feed's requirement.

The revised interface retains the existing routes, design system, permissions and posting services:

- **Plan feed:** one period selector, optional location/feed/stage filters, selected-period totals for needed/in-stock/to-produce, per-feed action cards, ingredient shortages and an expandable calculation breakdown.
- **Consumption:** a separate view of saved planned versus actual deliveries. Seven/fourteen-day selections look backward; the default is the current month. Empty results have an explicit explanation.
- **Feeding setup:** current standards, the configuration form, expandable version history, stage overrides and alert thresholds. Inputs retain submitted values after validation errors.
- **Production:** plans and confirmation come first; completed plans collapse. Recipes and direct mixing remain available in a separate expandable section. Direct mixing explicitly states that confirmation immediately updates stock.
- **Feed issue:** plain operational labels; actual quantity starts blank; selecting a group auto-selects its feed when exactly one applicable stocked feed exists. The expected count includes only animals using that feed. Incomplete demand shows an unavailable target rather than a misleading partial quantity. Optional new-group inputs are separate and disabled when an existing group is selected; new groups are explained as having no animals until assigned through Stock.

## Data safeguards

Custom forecasts require both dates and reject reversed ranges. Summary figures now use the same selected period and filters as the detail. Small decimal requirements retain up to four decimal places. Repeated missing-standard messages are grouped for readability; the engine still evaluates every effective date. Partial forecasts are labelled and do not offer automatic production creation. Incomplete forecasts cannot prefill purchase requests; normal manual purchasing remains available.

Shared inventory is explicitly identified as farm-wide stock, counted once per feed and not reserved by a plan. Group consumption remains a group total, with the existing caveat that stage/room filters select matching group deliveries rather than inventing individual allocations. Forecast assumptions and historical snapshots are preserved. Farm-approved rates and correct animal grouping remain necessary; the interface does not invent missing data.

No database migration, stock adjustment, rate change or historical-record modification was required for this pass. Redundant farm-wide dashboard queries were removed, and animal/standard setup lists load only for the setup view.

## Validation

- Focused feed, production and raw-material regression: **55 passed / 264 assertions**.
- New checks cover view separation, filtered demand, backward-looking consumption ranges, missing/reversed custom dates, per-feed animal counts, incomplete issue previews and blocked incomplete purchase prefilling.
- Real Chrome workflow: **1 passed / 15 assertions**, exercising setup → plan → production → issue → variance. Desktop and 390-pixel mobile screenshots were inspected; the revised layout uses the existing application shell and responsive styles.
- Frontend production build and whitespace checks passed.
- Additional purchase workflow/policy results are recorded below.

This is a usability pass on the existing module, not a replacement of its calculation or accounting architecture. Farm-user feedback on the revised screens is the next acceptance step.

## Files in this pass

- `app/Helpers/NavigationHelper.php`
- `app/Http/Controllers/FeedPlanningController.php`
- `app/Http/Controllers/Feed/FeedIssueController.php`
- `app/Http/Controllers/PurchaseRequestController.php`
- `resources/views/feeds/planning-reports/index.blade.php`
- `resources/views/feeds/planning-reports/partials/setup.blade.php`
- `resources/views/feeds/planning-reports/partials/consumption.blade.php`
- `resources/views/feeds/planning-reports/partials/materials.blade.php`
- `resources/views/feeds/feed-production/index.blade.php`
- `resources/views/feed/issues/create.blade.php`
- `tests/Feature/Feed/FeedPlanningUpgradeTest.php`
- `tests/Browser/FeedWorkflowTest.php`
- This review document.

Additional purchase workflow/policy regression: **18 passed / 29 assertions**. Combined targeted server-side validation for this pass: **73 passed / 293 assertions**, plus the Chrome workflow above. No failures remain in these runs; the full repository suite was not rerun for this interface-focused pass.
