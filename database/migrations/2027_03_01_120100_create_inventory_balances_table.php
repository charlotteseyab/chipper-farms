<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 — unified inventory balances snapshot.
 *
 * One row per (organization, item_kind, item_id) holding the CURRENT
 * on-hand quantity, total value, and weighted-average cost. Read-heavy:
 * every inventory lookup ("what's on hand", "what's it worth") hits this
 * table; writes happen only at movement time.
 *
 * Why a separate snapshot table instead of always reading the latest
 * movement?
 *   - Lookups are O(1) on (item_kind, item_id) instead of "find the
 *     latest movement for this item" via a per-item subquery.
 *   - The balances row is the canonical source for inventory value
 *     reports; the movements table is the ledger that explains how we
 *     got there.
 *   - Concurrency: the row-level lock on inventory_balances during
 *     movement writes serialises concurrent operations on the same item
 *     without locking the full movements table or the source raw_materials
 *     row (which would lock out other unrelated business logic).
 *
 * item_kind discriminator:
 *   - 'raw_material'   → item_id references raw_materials.id
 *   - 'finished_feed'  → item_id references feed_types.id
 *   - future: 'biological_asset_feed_lot' for IAS 41 (Phase 6)
 *
 * Why a discriminator instead of separate tables per kind?
 *   The math is identical (WAC), the columns are identical, the lookup
 *   patterns are identical. Splitting the table would force duplicate
 *   services and parallel logic for each new inventory kind, which
 *   compounds tech debt as more kinds appear. The discriminator + FK is
 *   the standard "polymorphic key with explicit type" pattern, validated
 *   at the service layer.
 *
 * Why NOT a real polymorphic morphTo column?
 *   morphTo trades type safety for flexibility we don't need. We know
 *   the universe of item kinds at compile time, the FK can't be enforced
 *   in the DB (morphTo writes the class name as a string), and reports
 *   that group by kind become awkward. The explicit `item_kind` enum-like
 *   column + manual join in the service is clearer, more performant, and
 *   FK-checkable at runtime.
 *
 * Precision:
 *   decimal(18,4) for quantities and total values, decimal(18,6) for the
 *   weighted-average cost. Matches the precision discipline locked in
 *   Phase 0.1 and the existing raw_material_valuations table.
 *
 * Concurrency:
 *   Writers acquire a row-level lock on the balances row via
 *   SELECT ... FOR UPDATE at the start of every movement. Two concurrent
 *   receipts on the SAME item serialise on this lock; receipts on
 *   different items run in parallel.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('inventory_balances')) {
            return;
        }

        Schema::create('inventory_balances', function (Blueprint $table) {
            $table->id();

            // Tenant scope — every inventory row carries it explicitly.
            // Even though the discriminator + item_id combo is unique per
            // (org, kind, item_id), the org_id column is also present on
            // its own for query convenience (org-wide aggregations).
            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')
                  ->references('id')
                  ->on('organizations')
                  ->restrictOnDelete()
                  ->cascadeOnUpdate();

            // Discriminator + soft FK to the underlying item table.
            // We don't add a hard FK because item_id has different
            // referent tables per item_kind; instead, the InventoryService
            // verifies existence at write time.
            $table->string('item_kind', 30);    // 'raw_material' | 'finished_feed'
            $table->unsignedBigInteger('item_id');

            // Cached display fields — denormalised from the source item
            // table for report performance. Refreshed when the source row
            // is updated (via observer in the model layer). Optional:
            // reports can join through to the source if they need the
            // authoritative current name.
            $table->string('item_name_cache', 200)->nullable();
            $table->string('item_unit_cache', 20)->nullable();

            // Authoritative current state — written under row lock by
            // InventoryService at every movement.
            $table->decimal('on_hand_qty',       18, 4)->default(0);
            $table->decimal('on_hand_value',     18, 4)->default(0);
            $table->decimal('weighted_avg_cost', 18, 6)->default(0);

            // Forward link to the most recent movement that wrote this
            // balance. Nullable FK is added AFTER inventory_movements
            // exists (next migration in dependency order); for now we
            // declare the column and the FK reference is created later.
            // The column is here from day 1 to avoid an ALTER on this
            // table when the FK is added.
            $table->unsignedBigInteger('last_movement_id')->nullable();

            // First/last movement timestamps for at-a-glance ageing.
            $table->timestamp('first_movement_at')->nullable();
            $table->timestamp('last_movement_at')->nullable();

            $table->timestamps();

            // The fundamental uniqueness invariant — one balance row
            // per (org, kind, item). Enforced at the DB layer so race
            // conditions in service code can't double-insert.
            $table->unique(
                ['organization_id', 'item_kind', 'item_id'],
                'inventory_balances_org_kind_item_unique',
            );

            // Read paths.
            $table->index(['item_kind', 'item_id'],          'inventory_balances_kind_item_idx');
            $table->index(['organization_id', 'item_kind'],  'inventory_balances_org_kind_idx');
            $table->index('last_movement_at',                'inventory_balances_last_move_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_balances');
    }
};
