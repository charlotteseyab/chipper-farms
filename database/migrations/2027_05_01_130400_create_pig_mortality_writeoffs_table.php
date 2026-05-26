<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 — pig mortality writeoffs.
 *
 * Operational mirror of the 5320 Mortality Loss postings. One row per
 * pig_deaths row that hits the GL. Captures the allocation inputs so
 * the writeoff is reproducible from the row alone.
 *
 * Why a separate table vs reading group_cost_movements?
 *   - Mortality is a recurring KPI; ops need a clean, filterable
 *     listing of all writeoffs without joining through the generic
 *     cost movements ledger.
 *   - Per-pig data (weight at death, allocated share) belongs alongside
 *     the writeoff event, not buried in a generic movement row.
 *   - The group_cost_movements row exists too; both record the same
 *     event from different angles. This table is the operational view;
 *     group_cost_movements is the ledger view.
 *
 * Lifecycle:
 *
 *   1. PigDeathRecorded event arrives at PigDeathMortalitySubscriber.
 *   2. Subscriber loads the dead pig + its group.
 *   3. Subscriber asks GroupCostPoolService::recordMortalityWriteoff
 *      to compute allocated share + write the cost movement.
 *   4. Subscriber posts GL: Dr 5320 / Cr 1230.
 *   5. Subscriber writes a row here, linking pig_death_id + the
 *      group_cost_movement row + the journal batch.
 *
 *   UNIQUE(pig_death_id) ensures one writeoff per death.
 *
 * Edge cases:
 *
 *   - Pig has no group_id: subscriber logs warning, returns null
 *     (skips). The pig_death row still exists, but no writeoff row.
 *     This means the pig was never assigned to a group cohort; its
 *     cost basis is effectively zero (no feed allocated to it). The
 *     PigDeath itself remains as a non-financial record.
 *
 *   - Pig's group pool is empty (0 weight, 0 value): allocated share
 *     is zero. Writeoff row IS written for audit, with zero amount.
 *     No GL posting in that case (zero-value journal is skipped).
 *
 *   - Final weight is null on the death record: subscriber uses the
 *     group's average weight (total_weight / pig_count) as the
 *     fallback. The writeoff row stores final_weight_at_death=null and
 *     used_group_average_weight=true so the audit shows the fallback.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('pig_mortality_writeoffs')) {
            return;
        }

        Schema::create('pig_mortality_writeoffs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')
                  ->references('id')
                  ->on('organizations')
                  ->restrictOnDelete();

            $table->unsignedBigInteger('pig_death_id');
            $table->foreign('pig_death_id', 'pig_mortality_writeoffs_death_fk')
                  ->references('id')
                  ->on('pig_deaths')
                  ->cascadeOnDelete();

            // Denormalised for query convenience (avoid joining through
            // pig_deaths → pigs_management for every report).
            $table->unsignedBigInteger('pig_id');
            $table->foreign('pig_id', 'pig_mortality_writeoffs_pig_fk')
                  ->references('id')
                  ->on('pigs_management');

            $table->unsignedBigInteger('group_id');
            $table->foreign('group_id', 'pig_mortality_writeoffs_group_fk')
                  ->references('id')
                  ->on('groups');

            // Allocation inputs.
            $table->decimal('pig_weight_at_death_kg', 14, 4)->nullable();
            // null when final_weight wasn't recorded on the death row;
            // see used_group_average_weight below.

            $table->decimal('group_weight_before_kg', 14, 4);
            $table->decimal('group_value_before',     18, 4);
            $table->integer('group_pig_count_before');

            $table->boolean('used_group_average_weight')->default(false);

            // The result of the allocation. writeoff_amount =
            // pig_weight × (group_value_before / group_weight_before).
            $table->decimal('writeoff_amount', 18, 4);

            $table->timestamp('occurred_at');

            // Links to the corresponding ledger + GL rows.
            $table->unsignedBigInteger('group_cost_movement_id')->nullable();
            $table->foreign('group_cost_movement_id', 'pig_mortality_writeoffs_movement_fk')
                  ->references('id')
                  ->on('group_cost_movements')
                  ->nullOnDelete();

            $table->unsignedBigInteger('journal_batch_id')->nullable();

            $table->timestamps();

            $table->unique('pig_death_id', 'pig_mortality_writeoffs_death_unique');

            $table->index(['organization_id', 'occurred_at'], 'pig_mortality_writeoffs_date_idx');
            $table->index(['group_id', 'occurred_at'],         'pig_mortality_writeoffs_group_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pig_mortality_writeoffs');
    }
};
