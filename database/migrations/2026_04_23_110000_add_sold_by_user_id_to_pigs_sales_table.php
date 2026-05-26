<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0.2 — sold_by fix.
 *
 * Adds two new columns to pig_sales:
 *   - sold_by_user_id : nullable FK to users.id (the source of truth going forward)
 *   - sold_by_name    : nullable string audit-preserving denormalisation of the
 *                       user's name at the time of sale
 *
 * The legacy `sold_by` column (which stored a user NAME despite the model's
 * belongsTo() relationship pointing to users.id) is NOT dropped by this
 * migration. It is kept for one release as a deprecation window, then retired
 * by a separate migration after all read paths have been updated.
 *
 * This migration is strictly additive: it cannot break existing rows or reads.
 *
 * Deploy sequence (see README):
 *   1. This migration.
 *   2. `php artisan chipper:backfill-pig-sale-sold-by` (idempotent).
 *   3. The drop migration (separate, later deploy).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pig_sales', function (Blueprint $table) {
            $table->unsignedBigInteger('sold_by_user_id')->nullable()->after('sold_by');

            $table->foreign('sold_by_user_id')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete()          // user deletion should not cascade-delete sales
                  ->cascadeOnUpdate();

            $table->index('sold_by_user_id', 'pig_sales_sold_by_user_id_index');

            $table->string('sold_by_name', 150)->nullable()->after('sold_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('pig_sales', function (Blueprint $table) {
            $table->dropForeign(['sold_by_user_id']);
            $table->dropIndex('pig_sales_sold_by_user_id_index');
            $table->dropColumn(['sold_by_user_id', 'sold_by_name']);
        });
    }
};