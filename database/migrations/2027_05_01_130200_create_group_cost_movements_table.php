<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 — group cost movements ledger.
 *
 * One row per business event that touches a group cost pool. Full
 * before/after snapshots. UNIQUE(source_type, source_id) gives
 * idempotency at the DB layer.
 *
 * Movement event types:
 *
 *   feed_issue        — feed delivered to group; pool value increases
 *                       at the issued feed's WAC × qty.
 *
 *   sale_cogs         — pig sold from group; pool decreases by the
 *                       sold pig's allocated share (per-kg basis).
 *
 *   mortality_writeoff — pig died; pool decreases by the dead pig's
 *                       allocated share.
 *
 *   transfer_out      — pig moved to another group; pool decreases by
 *                       moved pig's allocated share.
 *
 *   transfer_in       — pig arrived from another group; pool increases
 *                       by the same value the source group wrote off.
 *
 *   opening           — opening pool entry at cutover; pool established
 *                       with declared carrying value.
 *
 *   adjustment_add    — count correction; positive (e.g. discovered
 *                       extra pig).
 *
 *   adjustment_deduct — count correction; negative (e.g. missing pig
 *                       not previously recorded as dead).
 *
 *   adjustment_revalue — value correction without quantity change
 *                       (e.g. period-end adjustment to match physical
 *                       audit). Phase 5 reserves this; not used by
 *                       default flows.
 *
 * Source types:
 *
 *   'feed_issue'             — feed_issues.id
 *   'pig_sale'               — pig_sales.id (sale_cogs)
 *   'pig_death'              — pig_deaths.id (mortality_writeoff)
 *   'pig_group_transfer'     — pig_group_transfers.id (transfer in/out)
 *   'group_opening_import'   — group_opening_pool_imports.id × N + row
 *   'manual_adjustment'      — group_cost_adjustments.id (Phase 5
 *                              ships the table for future use; no UI yet)
 *
 * Idempotency: UNIQUE(source_type, source_id).
 *
 * Posting state: journal_batch_id forward-links to the GL posting once
 * the subscriber has posted. NULL = pending; non-NULL = posted.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('group_cost_movements')) {
            return;
        }

        Schema::create('group_cost_movements', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')
                  ->references('id')
                  ->on('organizations')
                  ->restrictOnDelete()
                  ->cascadeOnUpdate();

            $table->unsignedBigInteger('group_id');
            $table->foreign('group_id')
                  ->references('id')
                  ->on('groups')
                  ->restrictOnDelete()
                  ->cascadeOnUpdate();

            // Forward link to the snapshot row, set after the snapshot
            // is upserted.
            $table->unsignedBigInteger('balance_id')->nullable();
            $table->foreign('balance_id')
                  ->references('id')
                  ->on('group_cost_pools')
                  ->nullOnDelete();

            // Event type — short token, see model EVENT_* constants.
            $table->string('event_type', 30);

            // Source pointer.
            $table->string('source_type', 30);
            $table->unsignedBigInteger('source_id');

            // Signed deltas. Positive on inflows (feed_issue,
            // transfer_in, opening, adjustment_add). Negative on
            // outflows (sale_cogs, mortality_writeoff, transfer_out,
            // adjustment_deduct).
            $table->integer('pig_count_delta')->default(0);
            $table->decimal('value_delta',  18, 4)->default(0);
            $table->decimal('weight_delta', 14, 4)->default(0);

            // Snapshots BEFORE.
            $table->integer('pig_count_before')->default(0);
            $table->decimal('value_before',  18, 4)->default(0);
            $table->decimal('weight_before', 14, 4)->default(0);

            // Snapshots AFTER.
            $table->integer('pig_count_after')->default(0);
            $table->decimal('value_after',  18, 4)->default(0);
            $table->decimal('weight_after', 14, 4)->default(0);

            // For sale_cogs / mortality, this is the dead/sold pig's
            // weight at the event time, captured for audit so that the
            // allocation formula is reproducible from the row alone.
            $table->decimal('event_pig_weight_kg', 14, 4)->nullable();

            // Per-kg pool basis AT THE TIME OF THE EVENT — i.e.
            // value_before / weight_before. Captured as decimal(18,6)
            // so the allocation step survives rounding.
            $table->decimal('event_per_kg_basis', 18, 6)->nullable();

            $table->timestamp('occurred_at');

            $table->unsignedBigInteger('performed_by_user_id')->nullable();
            $table->foreign('performed_by_user_id')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();

            // Forward GL link.
            $table->unsignedBigInteger('journal_batch_id')->nullable();

            // Opening-import audit link (set for source_type='group_opening_import').
            $table->unsignedBigInteger('opening_import_id')->nullable();

            // Free-form.
            $table->string('reference', 200)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            // Idempotency.
            $table->unique(
                ['source_type', 'source_id'],
                'group_cost_movements_source_unique',
            );

            // Read paths.
            $table->index(['group_id', 'id'],                    'group_cost_movements_current_idx');
            $table->index(['group_id', 'occurred_at'],           'group_cost_movements_timeline_idx');
            $table->index(['organization_id', 'event_type'],     'group_cost_movements_org_event_idx');
            $table->index('journal_batch_id',                    'group_cost_movements_journal_idx');
            $table->index('opening_import_id',                   'group_cost_movements_opening_idx');
        });

        // Deferred FK on group_cost_pools.last_movement_id.
        if (Schema::hasTable('group_cost_pools') &&
            Schema::hasColumn('group_cost_pools', 'last_movement_id')) {
            try {
                Schema::table('group_cost_pools', function (Blueprint $table) {
                    $table->foreign('last_movement_id', 'group_cost_pools_last_move_fk')
                          ->references('id')
                          ->on('group_cost_movements')
                          ->nullOnDelete();
                });
            } catch (\Throwable $e) {
                logger()->warning(
                    'Could not add group_cost_pools.last_movement_id FK: ' . $e->getMessage()
                );
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('group_cost_pools')) {
            try {
                Schema::table('group_cost_pools', function (Blueprint $table) {
                    $table->dropForeign('group_cost_pools_last_move_fk');
                });
            } catch (\Throwable) {
                // OK, idempotent rollback.
            }
        }
        Schema::dropIfExists('group_cost_movements');
    }
};
