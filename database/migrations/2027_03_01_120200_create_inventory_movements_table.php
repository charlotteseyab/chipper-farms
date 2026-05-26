<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 — unified inventory movement ledger.
 *
 * Exactly one row per movement, full before/after snapshots, traceable
 * back to the source (stock log, feed batch, opening balance import,
 * adjustment, etc.). Replaces the Phase 0.7 raw_material_valuations
 * table; raw material valuations are migrated into this table in a
 * separate data-migration step.
 *
 * Replaces:
 *   raw_material_valuations  (raw materials only)
 *
 * Covers:
 *   raw materials AND finished feed AND future kinds (biological feed
 *   lots, etc.) — all via the item_kind discriminator.
 *
 * Movement source tracking:
 *
 *   source_type is a SHORT TOKEN ('stock_log', 'feed_batch',
 *   'feed_batch_item', 'opening_import', etc.) NOT a FQCN class string.
 *   Rationale:
 *     - Class renames don't break historical lookups.
 *     - The source table name is recognisable in DB inspection without
 *       guessing what the class was at the time.
 *     - The tokens are enumerable in code (SOURCE_* constants on the
 *       model) for type safety.
 *
 *   source_id is the integer primary key in the referenced table.
 *
 *   We do NOT use Eloquent morphTo here. The token+id pattern gives us
 *   clean, indexable, FK-checkable references that survive class moves.
 *
 * Idempotency:
 *
 *   UNIQUE(source_type, source_id) — exactly one inventory movement per
 *   source event. Two concurrent attempts to record the same movement
 *   collide cleanly at the DB layer; InventoryService catches the
 *   UniqueConstraintViolationException and translates to a typed
 *   DuplicateMovementException.
 *
 *   For opening balances (no source row to identify), source_type =
 *   'opening_import' and source_id = inventory_opening_balance_imports.id;
 *   the import audit table enforces hash-based dedup at the import level.
 *
 * Forward GL link:
 *
 *   journal_batch_id is nullable. Phase 4 subscribers fill it in after
 *   posting to GL. A NULL value means "not yet posted" — these are the
 *   movements the outbox processor will pick up. A non-NULL value is
 *   the receipt of GL posting completion.
 *
 * Precision:
 *
 *   decimal(18,4) for quantities and value deltas/snapshots.
 *   decimal(18,6) for weighted-average cost.
 *   Identical to the Phase 0.7 valuation table — values copied over by
 *   the data migration without precision loss.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('inventory_movements')) {
            return;
        }

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')
                  ->references('id')
                  ->on('organizations')
                  ->restrictOnDelete()
                  ->cascadeOnUpdate();

            // Item discriminator + id.
            $table->string('item_kind', 30);
            $table->unsignedBigInteger('item_id');

            // Convenience FK to the balances row this movement updated.
            // Nullable only for the case where the balances row was
            // created in the same transaction (set inside the service
            // after the balance is upserted).
            $table->unsignedBigInteger('balance_id')->nullable();
            $table->foreign('balance_id')
                  ->references('id')
                  ->on('inventory_balances')
                  ->nullOnDelete();

            // Event type — what business operation produced this movement.
            // See InventoryMovement model EVENT_* constants for the
            // canonical list.
            $table->string('event_type', 30);
            //  'receive'            : positive qty, may shift WAC
            //  'usage'              : negative qty, WAC unchanged
            //  'adjustment_add'     : positive qty, WAC unchanged (count gain)
            //  'adjustment_deduct'  : negative qty, WAC unchanged (count loss)
            //  'adjustment_set'     : signed delta to reach new on-hand, WAC unchanged
            //  'feed_produce'       : positive qty (for finished feed kind), shifts WAC
            //  'feed_consume_for_pigs' : negative qty (finished feed → pig group)
            //  'opening'            : positive qty, sets initial WAC
            //  'transfer_out'       : negative qty, sibling 'transfer_in' on dest
            //  'transfer_in'        : positive qty

            // Source pointer — short token, see model SOURCE_* constants.
            $table->string('source_type', 30);
            $table->unsignedBigInteger('source_id');

            // Signed quantity delta. Receipts/adjustments-add are positive,
            // usage/deduct are negative.
            $table->decimal('quantity_delta', 18, 4);

            // Signed value delta in functional currency (GHS today).
            // For receipts: +quantity × unit_cost.
            // For usage:    -quantity × old_WAC.
            // For adjustment_add: +quantity × old_WAC (no price signal).
            // For adjustment_set: +/- (new_qty × old_WAC - old_value).
            $table->decimal('value_delta', 18, 4);

            // Snapshots BEFORE the event.
            $table->decimal('quantity_on_hand_before',  18, 4);
            $table->decimal('value_on_hand_before',     18, 4);
            $table->decimal('weighted_avg_cost_before', 18, 6);

            // Snapshots AFTER the event — these match the inventory_balances
            // row AT THE TIME the movement was recorded. Future movements
            // overwrite balances; the after-snapshot in this row is the
            // immutable historical record.
            $table->decimal('quantity_on_hand_after',   18, 4);
            $table->decimal('value_on_hand_after',      18, 4);
            $table->decimal('weighted_avg_cost_after',  18, 6);

            // The unit cost supplied by the event itself.
            //   - 'receive': supplier price per unit.
            //   - 'opening': opening unit cost from import.
            //   - 'feed_produce': effective unit cost of the produced batch.
            //   - other events: NULL.
            $table->decimal('event_unit_cost', 18, 6)->nullable();

            // When the business event occurred (not when this row was
            // written). For backfilled openings, this is the historic
            // cutover date.
            $table->timestamp('occurred_at');

            $table->unsignedBigInteger('performed_by_user_id')->nullable();
            $table->foreign('performed_by_user_id')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();

            // Forward GL link, filled by subscriber after posting.
            $table->unsignedBigInteger('journal_batch_id')->nullable();
            // FK omitted because journal_batches may live in a separate
            // schema in some deployments; we keep the column indexable
            // but trust the application layer.

            // Opening balance audit link (created in next migration).
            $table->unsignedBigInteger('opening_balance_import_id')->nullable();

            // Free-form metadata.
            $table->string('reference', 200)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            // The fundamental idempotency invariant — one movement per
            // source event. This is the DB-level guarantee against double
            // posting from retried workflows or concurrent writes.
            $table->unique(
                ['source_type', 'source_id'],
                'inventory_movements_source_unique',
            );

            // Read paths.
            $table->index(['item_kind', 'item_id', 'id'],            'inventory_movements_current_idx');
            $table->index(['item_kind', 'item_id', 'occurred_at'],   'inventory_movements_timeline_idx');
            $table->index(['organization_id', 'event_type'],         'inventory_movements_org_event_idx');
            $table->index(['journal_batch_id'],                      'inventory_movements_journal_idx');
            $table->index(['opening_balance_import_id'],             'inventory_movements_opening_idx');
        });

        // Add the deferred FK from inventory_balances.last_movement_id
        // → inventory_movements.id. This couldn't be added in the
        // previous migration because inventory_movements didn't exist.
        if (Schema::hasTable('inventory_balances') &&
            Schema::hasColumn('inventory_balances', 'last_movement_id')) {
            try {
                Schema::table('inventory_balances', function (Blueprint $table) {
                    $table->foreign('last_movement_id', 'inventory_balances_last_movement_fk')
                          ->references('id')
                          ->on('inventory_movements')
                          ->nullOnDelete();
                });
            } catch (\Throwable $e) {
                // FK creation can fail if a prior partial migration left
                // an orphan; in that case we proceed without the FK and
                // log. The unique + index still hold.
                logger()->warning(
                    'Could not add inventory_balances.last_movement_id FK: ' . $e->getMessage()
                );
            }
        }
    }

    public function down(): void
    {
        // Drop the FK on inventory_balances before dropping the table
        // it references.
        if (Schema::hasTable('inventory_balances')) {
            try {
                Schema::table('inventory_balances', function (Blueprint $table) {
                    $table->dropForeign('inventory_balances_last_movement_fk');
                });
            } catch (\Throwable) {
                // FK may not exist if up() failed mid-way.
            }
        }

        Schema::dropIfExists('inventory_movements');
    }
};
