<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 — extend feed_batches with richer posting state.
 *
 * Phase 0.8 added is_posted (bool) + posted_at. Phase 4 needs the same
 * three-state machine we added to stock logs:
 *   - 'pending'  : produced, waiting for the GL subscriber
 *   - 'posted'   : subscriber posted Dr Finished Feed / Cr Raw Materials
 *   - 'failed'   : subscriber failed; manual review required
 *   - 'skipped'  : the subscriber decided not to post (no current case)
 *
 * is_posted remains as a denormalised convenience boolean (true when
 * posting_status='posted'). Backward-compatible with existing reports.
 *
 * Also adds journal_batch_id to forward-link to GL.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('feed_batches')) {
            return;
        }

        Schema::table('feed_batches', function (Blueprint $table) {
            if (! Schema::hasColumn('feed_batches', 'posting_status')) {
                $table->string('posting_status', 20)
                      ->default('pending')
                      ->after('is_posted');

                $table->index('posting_status', 'feed_batches_posting_status_v2_idx');
            }

            if (! Schema::hasColumn('feed_batches', 'posting_error')) {
                $table->text('posting_error')->nullable()->after('posting_status');
            }

            if (! Schema::hasColumn('feed_batches', 'journal_batch_id')) {
                $table->unsignedBigInteger('journal_batch_id')->nullable()->after('posted_at');
                $table->index('journal_batch_id', 'feed_batches_journal_batch_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('feed_batches')) {
            return;
        }

        Schema::table('feed_batches', function (Blueprint $table) {
            foreach ([
                'feed_batches_posting_status_v2_idx',
                'feed_batches_journal_batch_idx',
            ] as $idx) {
                try { $table->dropIndex($idx); } catch (\Throwable) {}
            }

            foreach (['posting_status', 'posting_error', 'journal_batch_id'] as $col) {
                if (Schema::hasColumn('feed_batches', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
