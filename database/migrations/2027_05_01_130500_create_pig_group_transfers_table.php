<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 — pig group transfers.
 *
 * When a pig moves between groups (e.g. weaning → nursery → grower →
 * finisher promotion), its allocated share of the source group's cost
 * pool must move WITH the pig. Otherwise the source pool would be
 * over-stated and the destination pool would never accumulate any
 * cost for that pig.
 *
 * One transfer row records the event. Spawns two group_cost_movements
 * rows (transfer_out on source, transfer_in on dest) and ONE journal
 * batch posting (an internal-account-class transfer; Dr 1230 / Cr 1230
 * for the same code is a no-op at the GL level, so we skip the GL
 * batch entirely — the asset stays in the same chart-of-accounts
 * bucket).
 *
 * Idempotency is at (pig_id, transferred_at) — a pig can only be in one
 * group at a time, so the same pig can't transfer twice at the exact
 * same moment.
 *
 * Why no GL batch?
 *   Both source and destination pools roll up to 1230 Live Pig
 *   Inventory. A Dr 1230 / Cr 1230 batch is technically valid but
 *   adds noise to the GL with no information content. The cost
 *   movement subledger captures the per-group reallocation; that's
 *   sufficient for audit.
 *
 *   IF a future deployment configures different inventory accounts
 *   per group type (e.g. nursery vs grower under different sub-accounts),
 *   this assumption breaks. Phase 5 documents this and leaves the
 *   adjustment as a future enhancement.
 *
 * Bulk-group movement note:
 *   When operations promote a whole group ("nursery batch 3 →
 *   grower batch 3"), the cleaner workflow is to RENAME the existing
 *   group rather than transfer each pig individually. The rename
 *   keeps one pool intact. Phase 5 supports both flows; the UI
 *   guides ops toward rename for whole-group moves.
 *
 * Mass operations:
 *   For multi-pig transfers (e.g. moving 50 pigs from one pen to
 *   another), a single batch creates 50 rows here. Each row carries
 *   the per-pig allocated share. The DB layer handles 50 inserts
 *   cleanly; no special bulk-insert path needed at Phase 5 scale.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('pig_group_transfers')) {
            return;
        }

        Schema::create('pig_group_transfers', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')
                  ->references('id')
                  ->on('organizations')
                  ->restrictOnDelete();

            $table->unsignedBigInteger('pig_id');
            $table->foreign('pig_id', 'pig_group_transfers_pig_fk')
                  ->references('id')
                  ->on('pigs_management');

            $table->unsignedBigInteger('source_group_id');
            $table->foreign('source_group_id', 'pig_group_transfers_source_fk')
                  ->references('id')
                  ->on('groups');

            $table->unsignedBigInteger('destination_group_id');
            $table->foreign('destination_group_id', 'pig_group_transfers_dest_fk')
                  ->references('id')
                  ->on('groups');

            // The pig's weight at transfer time, used as allocation key.
            $table->decimal('pig_weight_at_transfer_kg', 14, 4);

            // Allocated value transferred = pig_weight × source pool's
            // per-kg basis at the moment of transfer.
            $table->decimal('transferred_value', 18, 4);

            // Source pool snapshots at transfer time (for audit).
            $table->decimal('source_per_kg_basis_at_transfer', 18, 6);

            $table->timestamp('transferred_at');

            $table->unsignedBigInteger('performed_by_user_id')->nullable();
            $table->foreign('performed_by_user_id')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();

            $table->string('reason', 100)->nullable();
            // e.g. 'weaning_promotion', 'pen_reorganisation',
            //      'health_isolation', 'manual_override'

            $table->text('notes')->nullable();

            $table->timestamps();

            // One transfer per pig per moment in time.
            $table->unique(
                ['pig_id', 'transferred_at'],
                'pig_group_transfers_pig_time_unique',
            );

            $table->index(['source_group_id', 'transferred_at'],      'pig_group_transfers_source_time_idx');
            $table->index(['destination_group_id', 'transferred_at'], 'pig_group_transfers_dest_time_idx');
            $table->index(['organization_id', 'transferred_at'],      'pig_group_transfers_org_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pig_group_transfers');
    }
};
