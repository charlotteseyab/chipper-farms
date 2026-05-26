<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0.7 — raw_material_valuations table.
 *
 * Records the running weighted-average cost (WAC) for every raw material
 * after every stock movement. One row per movement. Enables:
 *   - Current WAC lookup: MAX(id) per raw_material_id → weighted_avg_cost_after
 *   - Point-in-time valuation: latest row at or before a date
 *   - Finance reconciliation: walk the history in chronological order and
 *     verify against GL inventory postings
 *
 * Design decisions:
 *   - Additive table. Existing raw_materials.unit_cost is NOT dropped in
 *     this batch — kept for one release as the legacy read path while the
 *     application adopts the new source of truth.
 *
 *   - decimal(18,6) for WAC and decimal(18,4) for quantities / totals.
 *     This is the finance precision locked in from Phase 0.1. The source
 *     tables use decimal(12,2) which is insufficient for long-running
 *     weighted-average rounding; the new valuation layer upgrades precision
 *     without touching the existing columns.
 *
 *   - stock_log_id FK is UNIQUE to guarantee exactly one valuation row per
 *     stock log. The backfill command relies on this for idempotency.
 *
 *   - organization_id present from day one per Phase 0.1 posture.
 *     Finance tables always carry tenant scope.
 *
 *   - event_type mirrors the source type on raw_material_stock_logs
 *     ('receive', 'adjustment', 'usage') but is duplicated here so the
 *     valuation history is self-contained — the consumer doesn't have to
 *     join back to the source table to interpret a row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raw_material_valuations', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')
                  ->references('id')
                  ->on('organizations')
                  ->restrictOnDelete()
                  ->cascadeOnUpdate();

            $table->unsignedBigInteger('raw_material_id');
            $table->foreign('raw_material_id')
                  ->references('id')
                  ->on('raw_materials')
                  ->cascadeOnDelete();

            // Source stock log: exactly one valuation per movement.
            $table->unsignedBigInteger('stock_log_id')->nullable();
            $table->foreign('stock_log_id')
                  ->references('id')
                  ->on('raw_material_stock_logs')
                  ->nullOnDelete();

            $table->string('event_type', 30);  // 'receive', 'adjustment_add', 'adjustment_deduct', 'adjustment_set', 'usage', 'opening'

            // The signed quantity delta for this event (+ for receipts / additions,
            // - for consumption / deductions).
            $table->decimal('quantity_delta', 18, 4);

            // The signed value delta, computed at event time.
            //   receive:           +(quantity × receipt_price)
            //   consumption:       -(quantity × current_WAC)
            //   adjustment_set:    +/- (new_qty × current_WAC - old_value)
            $table->decimal('value_delta', 18, 4);

            // Snapshots BEFORE the event (for audit trail).
            $table->decimal('quantity_on_hand_before', 18, 4);
            $table->decimal('value_on_hand_before', 18, 4);
            $table->decimal('weighted_avg_cost_before', 18, 6);

            // Snapshots AFTER the event.
            $table->decimal('quantity_on_hand_after', 18, 4);
            $table->decimal('value_on_hand_after', 18, 4);
            $table->decimal('weighted_avg_cost_after', 18, 6);

            // The unit cost supplied by the event itself. Meaningful only for
            // 'receive' — represents the price on the GRN / receipt document.
            // NULL for consumption / adjustment events that don't carry price.
            $table->decimal('event_unit_cost', 18, 6)->nullable();

            // When the business event occurred (not when this row was created).
            // For receipts this may equal created_at, but for opening balances
            // during backfill it may be historic.
            $table->timestamp('occurred_at');

            $table->unsignedBigInteger('performed_by_user_id')->nullable();
            $table->foreign('performed_by_user_id')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();

            $table->string('reference', 200)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            // Exactly one valuation per stock log. NULL stock_log_id
            // allowed for opening-balance rows written during backfill
            // where a stock log didn't exist.
            $table->unique('stock_log_id', 'raw_material_valuations_stock_log_unique');

            // Heavy read paths: "what's the current WAC for material X"
            // resolved via MAX(id) WHERE raw_material_id = ?
            $table->index(['raw_material_id', 'id'], 'raw_material_valuations_current_idx');

            // Point-in-time queries: "what was the WAC on date D"
            $table->index(['raw_material_id', 'occurred_at'], 'raw_material_valuations_timeline_idx');

            $table->index(['organization_id', 'event_type'], 'raw_material_valuations_org_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_material_valuations');
    }
};