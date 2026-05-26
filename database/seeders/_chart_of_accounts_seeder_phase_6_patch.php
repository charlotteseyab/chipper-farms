<?php

/**
 * Phase 6 — ChartOfAccountsSeeder corrections (hand-merge).
 *
 * WHY
 *
 * The seeder and the Phase 5 migrations diverged on three account codes.
 * Phase 6 migration 140000 normalizes ALREADY-MIGRATED databases at runtime,
 * but the seeder itself must also be corrected so FRESH installs are born
 * consistent (otherwise a fresh `migrate --seed` re-introduces the conflict
 * because the seeder runs after migrations and would clobber 1230/5000).
 *
 * Apply these edits to database/seeders/ChartOfAccountsSeeder.php, then
 * delete this file. After editing, a fresh install + Phase 6 migration must
 * be a no-op (the migration's guardNoPostings + idempotent upserts converge).
 *
 * ===================================================================
 *  EDIT 1 — line ~117: 1230 is Live Pig Inventory, NOT vet supplies.
 * ===================================================================
 *
 *  REPLACE:
 *    ['code' => '1230', 'name' => 'Veterinary Supplies Inventory',    'account_type' => 'asset', 'account_subtype' => 'current_asset',     'parent_code' => '1200'],
 *
 *  WITH (two rows — 1230 becomes Live Pig Inventory; vet supplies moves to 1250):
 *    ['code' => '1230', 'name' => 'Live Pig Inventory',               'account_type' => 'asset', 'account_subtype' => 'current_asset',     'parent_code' => '1200', 'is_system' => true, 'is_control' => true, 'allow_manual_journal' => false, 'description' => 'Per-group cost pools roll up here (Phase 5). Subscribers are the only sanctioned writers.'],
 *    ['code' => '1250', 'name' => 'Veterinary Supplies Inventory',    'account_type' => 'asset', 'account_subtype' => 'current_asset',     'parent_code' => '1200', 'is_system' => true, 'description' => 'Relocated from 1230 in Phase 6. Reserved for vet-supply capitalisation; not yet wired.'],
 *
 *  (Leave the existing 1240 Packaging Materials row immediately after, untouched.)
 *
 * ===================================================================
 *  EDIT 2 — line ~259: 5000 becomes the Cost of Sales PARENT.
 * ===================================================================
 *
 *  REPLACE:
 *    ['code' => '5000', 'name' => 'Cost of Pigs Sold',                'account_type' => 'expense', 'account_subtype' => 'cogs', 'is_system' => true, 'allow_manual_journal' => false, 'description' => 'Posted at pig sale: carrying cost of biological asset disposed'],
 *
 *  WITH (5000 is now a non-posting parent; add 5110 as the real Cost of Pigs Sold leaf):
 *    ['code' => '5000', 'name' => 'Cost of Sales',                    'account_type' => 'expense', 'account_subtype' => 'cogs', 'is_system' => true, 'allow_manual_journal' => false, 'description' => 'Cost of sales parent. Children: 5100 feed, 5110 pigs, 5200 by-products, 5300 adjustments, 5320 mortality.'],
 *    ['code' => '5110', 'name' => 'Cost of Pigs Sold',               'account_type' => 'expense', 'account_subtype' => 'cogs', 'parent_code' => '5000', 'is_system' => true, 'allow_manual_journal' => false, 'description' => 'COGS at pig sale: weight-proportional share of source group pool. Posted by PigSaleCogsSubscriber.'],
 *
 * ===================================================================
 *  EDIT 3 — lines ~260-264: parent the rest of cost-of-sales under 5000,
 *           and add Phase 5 Mortality Loss (5320).
 * ===================================================================
 *
 *  For the existing 5100 / 5200 / 5300 / 5400 / 5410 rows, ADD
 *  'parent_code' => '5000' to each (if not already present). Example:
 *
 *    ['code' => '5100', 'name' => 'Cost of Feed Sold',  ..., 'parent_code' => '5000', ...],
 *    ['code' => '5200', 'name' => 'Cost of By-products Sold', ..., 'parent_code' => '5000', ...],
 *    ['code' => '5300', 'name' => 'Inventory Adjustments and Shrinkage', ..., 'parent_code' => '5000', ...],
 *    ['code' => '5400', 'name' => 'Biological Asset Mortality (Adult)',  ..., 'parent_code' => '5000', ...],
 *    ['code' => '5410', 'name' => 'Biological Asset Mortality (Piglet)', ..., 'parent_code' => '5000', ...],
 *
 *  And ADD the Phase 5 Mortality Loss leaf (used by PigDeathRecordedSubscriber):
 *    ['code' => '5320', 'name' => 'Mortality Loss',     'account_type' => 'expense', 'account_subtype' => 'cogs', 'parent_code' => '5000', 'is_system' => true, 'allow_manual_journal' => false, 'description' => 'Cost basis written off when a pig dies (Phase 5). Posted by PigDeathRecordedSubscriber.'],
 *
 * ===================================================================
 *  RESULT
 * ===================================================================
 *
 *  Fresh install COA (cost of sales family):
 *    5000  Cost of Sales            (parent, no manual)
 *      5100  Cost of Feed Sold
 *      5110  Cost of Pigs Sold      <- Phase 5 posts here
 *      5200  Cost of By-products Sold
 *      5300  Inventory Adjustments and Shrinkage
 *      5320  Mortality Loss         <- Phase 5 posts here
 *      5400  Biological Asset Mortality (Adult)    [unused; available]
 *      5410  Biological Asset Mortality (Piglet)   [unused; available]
 *
 *    1230  Live Pig Inventory       (control, no manual)  <- Phase 5 posts here
 *    1250  Veterinary Supplies Inventory             [relocated; available]
 *
 *  This matches exactly what Phase 6 migration 140000 enforces on already
 *  migrated databases, so the two paths converge.
 */
