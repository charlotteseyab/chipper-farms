<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 — feed issues.
 *
 * Records each event where finished feed is delivered from the feed
 * store to a pig group. Phase 5 treats this as the moment cost moves
 * from "finished feed inventory" (1220) into "live pig inventory" (1230)
 * via the source group's cost pool.
 *
 * One feed_issues row spawns:
 *
 *   1. inventory_movements row (item_kind='finished_feed', event='usage',
 *      source_type='feed_issue', source_id=this row) — reduces 1220
 *      at finished feed WAC.
 *
 *   2. group_cost_movements row (event_type='feed_issue', source_type
 *      ='feed_issue', source_id=this row) — increases the group pool
 *      by the same amount.
 *
 *   3. Journal batch posting Dr 1230 / Cr 1220 at issuance value.
 *
 * Both ledger writes share the same source_id but DIFFERENT source_type
 * tokens — that's intentional: 'feed_issue' on the cost pool side, and
 * 'feed_issue' on the inventory side (the source_type column on
 * inventory_movements). Each ledger uniqueness constraint is per-ledger,
 * so the same source_id can appear in both without collision.
 *
 * Why a dedicated table vs reusing FeedBatch/FeedBatchItem?
 *   Batch = production event (feed is mixed). Issue = consumption event
 *   (feed leaves the store). Multiple issues typically draw from a
 *   single batch; conversely a single issue may span batches via WAC.
 *   Keeping them separate matches the operational reality and the
 *   business semantics.
 *
 * Posting state:
 *   - 'pending'  : created, awaiting subscriber
 *   - 'posted'   : GL posting succeeded
 *   - 'failed'   : posting failed; review needed
 *   - 'reversed' : reservation for future reversal flow (Phase 5 does
 *                  not ship the reversal UI; the status is here so the
 *                  state machine is forward-compatible)
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('feed_issues')) {
            return;
        }

        Schema::create('feed_issues', function (Blueprint $table) {
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

            $table->unsignedBigInteger('feed_type_id');
            $table->foreign('feed_type_id')
                  ->references('id')
                  ->on('feed_types')
                  ->restrictOnDelete()
                  ->cascadeOnUpdate();

            // Quantity issued, in kg (matches feed batch unit).
            $table->decimal('quantity_kg', 14, 4);

            // WAC at the moment of issuance (snapshot from
            // inventory_balances → finished_feed.weighted_avg_cost).
            $table->decimal('unit_cost_at_issuance', 18, 6);

            // total_cost = quantity_kg * unit_cost_at_issuance.
            // Stored separately for audit and to avoid recomputation
            // drift if WAC changes after the issue.
            $table->decimal('total_cost', 18, 4);

            $table->timestamp('issued_at');

            $table->unsignedBigInteger('issued_by_user_id')->nullable();
            $table->foreign('issued_by_user_id')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();

            $table->string('reference', 200)->nullable();
            $table->text('notes')->nullable();

            $table->string('posting_status', 20)->default('pending');
            $table->text('posting_error')->nullable();
            $table->unsignedBigInteger('journal_batch_id')->nullable();

            $table->timestamps();

            // Read paths.
            $table->index(['organization_id', 'group_id', 'issued_at'], 'feed_issues_group_time_idx');
            $table->index(['feed_type_id', 'issued_at'],                'feed_issues_feed_time_idx');
            $table->index('posting_status',                             'feed_issues_status_idx');
            $table->index('journal_batch_id',                           'feed_issues_journal_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_issues');
    }
};
