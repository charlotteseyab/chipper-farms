# Feed review fixes — 15 October 2026

## Phase 1: stock integrity and restocking

A physical stock count is rejected if the item has movements after the count time. The entire count rolls back, including earlier lines in the same submission. Staff must recount and enter the new time. This applies to raw materials and finished feed and prevents a historical count from overwriting later receipts or usage. Counts without later movements remain supported; raw-material adjustment movements and domain events now use the entered count time. The form accepts seconds and explains when a recount is necessary.

The raw-material restock shortcut computes the shortage in stock units. Ten missing bags suggest ten bags rather than dividing by bag weight again. Partial bag shortages round up to whole bags; kg shortages retain decimal quantities. Prefill notes name the actual unit. Forecast shortages, which are already in kg, retain their separate conversion path.

## Phase 2: production plans

Confirmation requires the plan to be planned. Completed submissions remain idempotent; cancelled plans are refused without inventory movement. Pending plans and earlier plans have independent pagination, so newer closed plans cannot hide outstanding work. Pending plans are ordered by production date, oldest first.

## Phase 3: feeding retries and reports

Feeding submissions use the transaction-protected stock check after service idempotency handling, rather than an earlier controller check that counted already-saved rows as new demand. Saved rows can be retried alongside new rows. Conflicting keys and stock shortages roll back new rows while preserving earlier saved submissions. Stock shortage errors still name the feed and quantities.

Consumption filters apply stage, room, and building together. When saved population locations are needed, all supplied fields must match the same location. This also applies to legacy group issues. Matching a location includes the saved feeding row; it does not apportion a stage-wide feeding quantity between rooms.

## Validation

Regression coverage: `tests/Feature/Feed/FeedReviewRegressionTest.php` (13 new tests).

Result: 171 tests passed with 790 assertions.

Checks cover feed workflows, production, receipts, raw-material adjustments, physical counts, procurement receiving, finance subscribers, feed sales and returns. Browser interaction and MariaDB concurrency are not exercised by the SQLite feature tests.

## Separate planning question

Ingredient forecasts continue to use the recipe at the start of the selected period. Splitting demand between recipe versions requires agreeing whether production occurs at the start or throughout the period; this was a design question in the review, separate from the six confirmed defects fixed here.
