<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 — extend pigs_management with weight cache.
 *
 * Adds:
 *   - current_weight_kg_cache : decimal(14,4), nullable.
 *                               The pig's most-recent recorded weight,
 *                               cached for fast allocation at sale or
 *                               mortality time. Updated by an observer
 *                               on PigWeight create/update.
 *
 *   - weight_cache_recorded_at : timestamp, nullable.
 *                                When the cached weight was captured.
 *                                Used to flag stale caches in reports.
 *
 * Why a cache when pig_weights table exists?
 *   At sale time, the COGS subscriber needs to know the pig's weight
 *   to compute the allocation. Reaching into pig_weights to find the
 *   latest row per pig is a per-pig subquery; with 10,000+ pigs in
 *   prod the lookup gets slow. A cached column on pigs_management is
 *   O(1) per pig.
 *
 *   The cache is for performance, not authority. The authoritative
 *   record is still pig_weights. If the cache is stale or null,
 *   subscribers fall back to the latest pig_weights row, or to
 *   sale_weight on PigSale, or to the group's average weight (in
 *   priority order).
 *
 * No group_id column added here — pigs_management.group_id already
 * exists (Phase 1 of the lifecycle module). The Phase 5 model patch
 * adds it to the fillable + adds a relation; no schema change needed.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('pigs_management')) {
            return;
        }

        Schema::table('pigs_management', function (Blueprint $table) {
            if (! Schema::hasColumn('pigs_management', 'current_weight_kg_cache')) {
                $table->decimal('current_weight_kg_cache', 14, 4)->nullable()->after('local_id');
            }

            if (! Schema::hasColumn('pigs_management', 'weight_cache_recorded_at')) {
                $table->timestamp('weight_cache_recorded_at')->nullable()->after('current_weight_kg_cache');
                $table->index('weight_cache_recorded_at', 'pigs_management_weight_cache_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pigs_management')) {
            return;
        }

        Schema::table('pigs_management', function (Blueprint $table) {
            try { $table->dropIndex('pigs_management_weight_cache_idx'); } catch (\Throwable) {}

            foreach (['current_weight_kg_cache', 'weight_cache_recorded_at'] as $col) {
                if (Schema::hasColumn('pigs_management', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
