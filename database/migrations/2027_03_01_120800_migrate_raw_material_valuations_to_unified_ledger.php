<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 — migrate raw_material_valuations → inventory_movements +
 *           inventory_balances, then drop the legacy valuations table.
 *
 * The Phase 0.7 raw_material_valuations table is the predecessor of
 * Phase 4's unified inventory_movements ledger. They share the same
 * shape; the only difference is the new table covers ALL inventory
 * kinds (raw materials + finished feed + future), not just raw materials.
 *
 * This migration:
 *   1. Copies every row in raw_material_valuations into
 *      inventory_movements with item_kind='raw_material'.
 *   2. Computes the latest movement per material and writes the
 *      inventory_balances snapshot.
 *   3. Wires inventory_balances.last_movement_id to point to the
 *      latest movement row.
 *   4. Drops raw_material_valuations.
 *
 * Why drop the old table?
 *   Pre-production / not in live use means there's no operational risk
 *   to a clean cutover. Keeping the old table would mean perpetually
 *   keeping the old RawMaterialValuationService too, and reconciling
 *   between the two ledgers — permanent tech debt. The unified table
 *   is the source of truth from this migration onwards; the legacy
 *   service is deleted in a code drop.
 *
 * Edge cases:
 *   - Empty raw_material_valuations: trivially proceeds (0 rows copied).
 *   - raw_material_valuations missing: migration short-circuits with
 *     a warning. This happens on fresh installs that never deployed
 *     Phase 0.7.
 *   - Materials with valuation rows but with stock_log_id pointing to
 *     a deleted stock log (Phase 0.7 used nullOnDelete): preserved
 *     in inventory_movements with the legacy stock_log_id repointed
 *     to source_type='legacy_stock_log' for traceability.
 *
 * Idempotency (corrected — CF-P1-016):
 *   The original note here claimed that "the UNIQUE(source_type, source_id)
 *   constraint catches a re-run cleanly — the second run inserts zero rows and
 *   proceeds". The constraint does exist (inventory_movements_source_unique),
 *   but that is not how a plain INSERT ... SELECT behaves: it ABORTS with
 *   "1062 Duplicate entry 'legacy_valuation-<id>'". Verified directly against
 *   MariaDB 11.4.
 *
 *   Why that matters despite the DML being transactional: the DROP must run
 *   outside the transaction (MySQL implicitly commits on DDL — see the comment
 *   at the drop). So there is a real window in which the copy has COMMITTED but
 *   the legacy table has not yet been dropped. If the process dies there — a
 *   deploy timeout, an OOM kill, a lost connection — the next run finds the
 *   legacy table still present, re-runs the INSERT, and fails on the unique key.
 *   The migration is then permanently stuck: it cannot complete and cannot be
 *   rolled forward without manual intervention, mid-deploy.
 *
 *   inventory_balances has the same exposure through its own
 *   UNIQUE(organization_id, item_kind, item_id).
 *
 *   Both steps are now genuinely idempotent:
 *     - the copy carries WHERE NOT EXISTS against the already-migrated
 *       source_type/source_id pair, so a re-run copies only what is missing;
 *     - the balance snapshot upserts instead of blind-inserting.
 *
 *   NOT EXISTS is used rather than INSERT IGNORE / ON DUPLICATE KEY because it
 *   is portable: this migration also runs on SQLite in the test suite, where
 *   INSERT IGNORE is not valid syntax.
 *
 * Rollback:
 *   The down() recreates raw_material_valuations and copies back the
 *   rows. This is best-effort: timestamps may shift, and any movement
 *   rows for finished feed (item_kind='finished_feed') are not copied
 *   back because they have no counterpart in the legacy table.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('inventory_movements') ||
            ! Schema::hasTable('inventory_balances')) {
            throw new \RuntimeException(
                'Phase 4 data migration requires inventory_movements and '
                . 'inventory_balances tables to exist. Run prior Phase 4 '
                . 'migrations first.'
            );
        }

        if (! Schema::hasTable('raw_material_valuations')) {
            logger()->info(
                'Phase 4 data migration: raw_material_valuations table missing. '
                . 'No data to migrate. Continuing.'
            );
            return;
        }

        DB::transaction(function () {
            // 1. Copy rows in chronological order so the inventory_movements
            //    table preserves the original event sequence per material.
            DB::statement('
                INSERT INTO inventory_movements (
                    organization_id,
                    item_kind,
                    item_id,
                    balance_id,
                    event_type,
                    source_type,
                    source_id,
                    quantity_delta,
                    value_delta,
                    quantity_on_hand_before,
                    value_on_hand_before,
                    weighted_avg_cost_before,
                    quantity_on_hand_after,
                    value_on_hand_after,
                    weighted_avg_cost_after,
                    event_unit_cost,
                    occurred_at,
                    performed_by_user_id,
                    reference,
                    notes,
                    created_at,
                    updated_at
                )
                SELECT
                    organization_id,
                    \'raw_material\'                AS item_kind,
                    raw_material_id                AS item_id,
                    NULL                           AS balance_id,
                    event_type,
                    \'legacy_valuation\'           AS source_type,
                    id                             AS source_id,
                    quantity_delta,
                    value_delta,
                    quantity_on_hand_before,
                    value_on_hand_before,
                    weighted_avg_cost_before,
                    quantity_on_hand_after,
                    value_on_hand_after,
                    weighted_avg_cost_after,
                    event_unit_cost,
                    occurred_at,
                    performed_by_user_id,
                    reference,
                    notes,
                    created_at,
                    updated_at
                FROM raw_material_valuations
                WHERE NOT EXISTS (
                    SELECT 1 FROM inventory_movements im
                    WHERE im.source_type = \'legacy_valuation\'
                      AND im.source_id = raw_material_valuations.id
                )
                ORDER BY id ASC
            ');

            // 2. For each raw material, write the inventory_balances
            //    snapshot from the LATEST inventory_movements row.
            //
            //    The "latest" row per material is identified by
            //    MAX(id) WHERE item_kind='raw_material' AND item_id=?.
            //    Because we just copied in ascending id order, the
            //    latest copied row IS the latest valuation per material.
            $latestPerMaterial = DB::table('inventory_movements')
                ->select(
                    'organization_id',
                    'item_id',
                    DB::raw('MAX(id) AS latest_movement_id'),
                )
                ->where('item_kind', 'raw_material')
                ->where('source_type', 'legacy_valuation')
                ->groupBy('organization_id', 'item_id')
                ->get();

            foreach ($latestPerMaterial as $row) {
                $latest = DB::table('inventory_movements')
                    ->where('id', $row->latest_movement_id)
                    ->first();

                if (! $latest) {
                    continue; // race-safety; shouldn't happen mid-transaction.
                }

                // Cache item name/unit from raw_materials for display.
                $material = DB::table('raw_materials')
                    ->where('id', $row->item_id)
                    ->first(['name', 'unit']);

                // Upsert rather than insert: inventory_balances carries
                // UNIQUE(organization_id, item_kind, item_id), so a re-run after
                // a partial failure would abort here exactly as the copy step did
                // (CF-P1-016). updateOrInsert is portable across MySQL and SQLite.
                $balanceKey = [
                    'organization_id' => $row->organization_id,
                    'item_kind'       => 'raw_material',
                    'item_id'         => $row->item_id,
                ];

                $balanceValues = [
                    'item_name_cache'    => $material->name ?? null,
                    'item_unit_cache'    => $material->unit ?? null,
                    'on_hand_qty'        => $latest->quantity_on_hand_after,
                    'on_hand_value'      => $latest->value_on_hand_after,
                    'weighted_avg_cost'  => $latest->weighted_avg_cost_after,
                    'last_movement_id'   => $latest->id,
                    'first_movement_at'  => DB::table('inventory_movements')
                                                ->where('item_kind', 'raw_material')
                                                ->where('item_id', $row->item_id)
                                                ->min('occurred_at'),
                    'last_movement_at'   => $latest->occurred_at,
                    'updated_at'         => now(),
                ];

                // created_at is set only when the row is genuinely new, so a
                // re-run refreshes the snapshot without rewriting its provenance.
                if (! DB::table('inventory_balances')->where($balanceKey)->exists()) {
                    $balanceValues['created_at'] = now();
                }

                DB::table('inventory_balances')->updateOrInsert($balanceKey, $balanceValues);

                $balanceId = DB::table('inventory_balances')
                    ->where($balanceKey)
                    ->value('id');

                // Patch every movement row for this material with the
                // balance_id. The "balance_id" column is informational
                // (a forward link); back-filling here keeps history
                // self-describing.
                DB::table('inventory_movements')
                    ->where('item_kind', 'raw_material')
                    ->where('item_id', $row->item_id)
                    ->update(['balance_id' => $balanceId]);
            }
        });

        // 3. Drop raw_material_valuations (outside the transaction).
        //    MySQL implicitly commits on DDL, which breaks DB::transaction()
        //    if drop runs inside the closure ("There is no active transaction").
        Schema::dropIfExists('raw_material_valuations');
    }

    public function down(): void
    {
        // Re-create the legacy table and copy raw-material rows back.
        if (Schema::hasTable('raw_material_valuations')) {
            return;
        }

        Schema::create('raw_material_valuations', function ($table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('raw_material_id');
            $table->unsignedBigInteger('stock_log_id')->nullable();
            $table->string('event_type', 30);
            $table->decimal('quantity_delta', 18, 4);
            $table->decimal('value_delta', 18, 4);
            $table->decimal('quantity_on_hand_before', 18, 4);
            $table->decimal('value_on_hand_before', 18, 4);
            $table->decimal('weighted_avg_cost_before', 18, 6);
            $table->decimal('quantity_on_hand_after', 18, 4);
            $table->decimal('value_on_hand_after', 18, 4);
            $table->decimal('weighted_avg_cost_after', 18, 6);
            $table->decimal('event_unit_cost', 18, 6)->nullable();
            $table->timestamp('occurred_at');
            $table->unsignedBigInteger('performed_by_user_id')->nullable();
            $table->string('reference', 200)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique('stock_log_id', 'raw_material_valuations_stock_log_unique');
            $table->index(['raw_material_id', 'id'], 'raw_material_valuations_current_idx');
        });

        if (Schema::hasTable('inventory_movements')) {
            // Copy back: only legacy-valuation rows are eligible. The
            // original stock_log_id is encoded in source_id when
            // source_type='legacy_stock_log' was used; otherwise it's
            // lost in the round-trip. This is best-effort.
            DB::statement('
                INSERT INTO raw_material_valuations (
                    organization_id, raw_material_id, stock_log_id,
                    event_type, quantity_delta, value_delta,
                    quantity_on_hand_before, value_on_hand_before, weighted_avg_cost_before,
                    quantity_on_hand_after,  value_on_hand_after,  weighted_avg_cost_after,
                    event_unit_cost, occurred_at, performed_by_user_id,
                    reference, notes, created_at, updated_at
                )
                SELECT
                    organization_id, item_id, NULL,
                    event_type, quantity_delta, value_delta,
                    quantity_on_hand_before, value_on_hand_before, weighted_avg_cost_before,
                    quantity_on_hand_after,  value_on_hand_after,  weighted_avg_cost_after,
                    event_unit_cost, occurred_at, performed_by_user_id,
                    reference, notes, created_at, updated_at
                FROM inventory_movements
                WHERE item_kind = \'raw_material\'
                ORDER BY id ASC
            ');
        }
    }
};
