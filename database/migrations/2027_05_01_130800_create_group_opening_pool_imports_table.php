<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 — group opening pool imports.
 *
 * Cutover audit table — exactly mirrors Phase 4's
 * inventory_opening_balance_imports for pig pools.
 *
 * Workflow:
 *   1. Operations identifies existing groups that need an opening
 *      carrying value at cutover.
 *   2. For each group, declares: pig_count, total_weight_kg,
 *      total_carrying_value (the best estimate of cumulative cost
 *      already invested in that group).
 *   3. Submits the batch via the OpeningPigPoolController UI.
 *   4. OpeningPigPoolService computes a SHA-256 hash of the
 *      canonicalised payload, audits the attempt here, then writes
 *      a group_cost_movements row per group via GroupCostPoolService.
 *
 *   Idempotency: UNIQUE(organization_id, import_hash) catches
 *   resubmitted identical payloads.
 *
 * GL impact at opening:
 *   Each opening pool row produces a Dr 1230 Live Pig Inventory /
 *   Cr 3010 Retained Earnings (or 3020 Opening Balance Equity if the
 *   COA has one) journal. This places the carrying value on the
 *   balance sheet without impacting current-period P&L.
 *
 * Status lifecycle: identical to Phase 4 — pending, completed, failed,
 * archived.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('group_opening_pool_imports')) {
            return;
        }

        Schema::create('group_opening_pool_imports', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('organization_id');
            $table->foreign('organization_id')
                  ->references('id')
                  ->on('organizations')
                  ->restrictOnDelete();

            $table->string('import_mode', 20);   // 'manual' | 'csv'
            $table->string('import_hash', 64);

            $table->string('original_filename', 255)->nullable();

            $table->date('cutover_date');

            $table->unsignedInteger('row_count');

            $table->string('status', 20);  // pending | completed | failed | archived

            $table->text('error_message')->nullable();

            $table->unsignedBigInteger('performed_by_user_id')->nullable();
            $table->foreign('performed_by_user_id')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
                ['organization_id', 'import_hash'],
                'group_opening_pool_imports_hash_unique',
            );

            $table->index(['organization_id', 'status'], 'group_opening_pool_imports_status_idx');
            $table->index(['cutover_date'],              'group_opening_pool_imports_date_idx');
        });

        // Deferred FK on group_cost_movements.opening_import_id.
        if (Schema::hasTable('group_cost_movements') &&
            Schema::hasColumn('group_cost_movements', 'opening_import_id')) {
            try {
                Schema::table('group_cost_movements', function (Blueprint $table) {
                    $table->foreign('opening_import_id', 'group_cost_movements_opening_fk')
                          ->references('id')
                          ->on('group_opening_pool_imports')
                          ->nullOnDelete();
                });
            } catch (\Throwable $e) {
                logger()->warning(
                    'Could not add group_cost_movements.opening_import_id FK: ' . $e->getMessage()
                );
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('group_cost_movements')) {
            try {
                Schema::table('group_cost_movements', function (Blueprint $table) {
                    $table->dropForeign('group_cost_movements_opening_fk');
                });
            } catch (\Throwable) {}
        }

        Schema::dropIfExists('group_opening_pool_imports');
    }
};
