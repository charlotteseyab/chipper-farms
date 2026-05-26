<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 — group cost pools snapshot.
 *
 * Per-group snapshot of accumulated cost basis. One row per
 * (organization_id, group_id). Carries:
 *
 *   - on_hand_pig_count: how many live pigs are currently in the group
 *   - total_carrying_value: cumulative cost not yet written off (feed
 *     issued in, mortality + sale COGS out)
 *   - total_weight_kg: sum of current pig weights in the group;
 *     used as the allocation key for COGS/mortality
 *
 * Together these mean: per-pig average cost = total_carrying_value /
 * on_hand_pig_count. Per-kg average cost = total_carrying_value /
 * total_weight_kg.
 *
 * Phase 5 uses **per-kg** allocation at sale/mortality time (heavier
 * pigs absorb proportionally more of the pool). This is the most
 * defensible allocation when group cohorts vary widely in size.
 *
 * Read patterns:
 *   - "What's the cost basis of this group right now?" — O(1) on
 *     (org, group_id).
 *   - "What's our total live pig inventory?" — SUM across this table.
 *
 * Write patterns:
 *   - One row written or updated per event (feed issue, mortality,
 *     sale, transfer). All writes go through GroupCostPoolService.
 *
 * Why a separate snapshot vs always reading the latest movement?
 *   Same reasoning as Phase 4 inventory_balances:
 *     - O(1) lookup for reports.
 *     - Row-level lock target during concurrent writes.
 *     - Independent of movement table volume.
 *
 * Precision:
 *   total_carrying_value: decimal(18,4) — same as inventory ledger.
 *   total_weight_kg: decimal(14,4) — weights are typically 0–500kg.
 *   on_hand_pig_count: integer — can't be fractional.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('group_cost_pools')) {
            return;
        }

        Schema::create('group_cost_pools', function (Blueprint $table) {
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

            // Cached display fields — kept in sync via observer when
            // groups.name changes, but reports may join through if
            // they need the live name.
            $table->string('group_name_cache', 200)->nullable();
            $table->string('group_type_cache', 50)->nullable();

            // The authoritative snapshot.
            $table->integer('on_hand_pig_count')->default(0);
            $table->decimal('total_carrying_value', 18, 4)->default(0);
            $table->decimal('total_weight_kg', 14, 4)->default(0);

            // Last-movement forward link (deferred FK added when
            // group_cost_movements exists).
            $table->unsignedBigInteger('last_movement_id')->nullable();

            // Lifecycle markers.
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            // closed_at is set when the group is dissolved (all pigs
            // sold / died / transferred out) and pool value is zero.

            $table->timestamp('first_movement_at')->nullable();
            $table->timestamp('last_movement_at')->nullable();

            $table->timestamps();

            // One pool per (org, group).
            $table->unique(
                ['organization_id', 'group_id'],
                'group_cost_pools_org_group_unique',
            );

            // Read paths.
            $table->index(['organization_id', 'closed_at'], 'group_cost_pools_status_idx');
            $table->index('last_movement_at',              'group_cost_pools_last_move_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_cost_pools');
    }
};
