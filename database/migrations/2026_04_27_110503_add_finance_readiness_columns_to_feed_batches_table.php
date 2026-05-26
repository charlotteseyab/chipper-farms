<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0.8 — finance-readiness columns on feed_batches.
 *
 * Feed batches become Phase 1 inventory transfers (Raw Materials → Feed WIP)
 * and then further transfers (Feed WIP → Biological Asset Feed Cost or
 * Feed Sales, depending on the downstream use).
 *
 * Safe and idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('feed_batches')) {
            return;
        }

        Schema::table('feed_batches', function (Blueprint $table) {
            if (! Schema::hasColumn('feed_batches', 'organization_id')) {
                $table->unsignedBigInteger('organization_id')->nullable()->after('id');
                $table->index('organization_id', 'feed_batches_organization_id_idx');
            }

            if (! Schema::hasColumn('feed_batches', 'document_number')) {
                $table->string('document_number', 50)->nullable();
                $table->index('document_number', 'feed_batches_document_number_idx');
            }

            if (! Schema::hasColumn('feed_batches', 'is_posted')) {
                $table->boolean('is_posted')->default(false);
            }

            if (! Schema::hasColumn('feed_batches', 'posted_at')) {
                $table->timestamp('posted_at')->nullable();
                $table->index(['is_posted', 'posted_at'], 'feed_batches_posting_status_idx');
            }

            if (! Schema::hasColumn('feed_batches', 'cost_centre_id')) {
                $table->unsignedBigInteger('cost_centre_id')->nullable();
                $table->index('cost_centre_id', 'feed_batches_cost_centre_id_idx');
            }

            if (! Schema::hasColumn('feed_batches', 'legacy_source_id')) {
                $table->string('legacy_source_id', 100)->nullable();
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
                'feed_batches_organization_id_idx',
                'feed_batches_document_number_idx',
                'feed_batches_posting_status_idx',
                'feed_batches_cost_centre_id_idx',
            ] as $idx) {
                try { $table->dropIndex($idx); } catch (\Throwable $e) { /* ignore */ }
            }

            foreach ([
                'organization_id',
                'document_number',
                'is_posted',
                'posted_at',
                'cost_centre_id',
                'legacy_source_id',
            ] as $col) {
                if (Schema::hasColumn('feed_batches', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};