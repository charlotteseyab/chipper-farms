<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchased stock batch numbers (PP-2026-001…) are counted per farm, but the
 * column was unique across all farms: a second farm's first intake of a year
 * took a number another farm already had, and could not be saved. Unique per
 * farm instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_stock_intakes', function (Blueprint $table) {
            $table->dropUnique('purchase_stock_intakes_batch_number_unique');
            $table->unique(['organization_id', 'batch_number'], 'purchase_stock_intakes_org_batch_unique');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_stock_intakes', function (Blueprint $table) {
            $table->dropUnique('purchase_stock_intakes_org_batch_unique');
            $table->unique('batch_number');
        });
    }
};
