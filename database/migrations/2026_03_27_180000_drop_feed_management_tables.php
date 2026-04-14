<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop all tables related to legacy and new feed management features.
        Schema::disableForeignKeyConstraints();

        // New feed management tables
        Schema::dropIfExists('feed_mix_batch_items');
        Schema::dropIfExists('feed_projection_settings');
        Schema::dropIfExists('feed_mix_batches');
        Schema::dropIfExists('feed_formula_items');
        Schema::dropIfExists('feed_formulas');
        Schema::dropIfExists('finished_feeds');
        Schema::dropIfExists('purchase_request_items');
        Schema::dropIfExists('purchase_requests');
        Schema::dropIfExists('raw_material_stock_movements');
        Schema::dropIfExists('raw_materials');
        Schema::dropIfExists('animal_stages');
        Schema::dropIfExists('suppliers');

        // Legacy feed tables from the earlier feed module
        Schema::dropIfExists('feed_recipe_items');
        Schema::dropIfExists('feed_recipes');
        Schema::dropIfExists('feed_schedules');
        Schema::dropIfExists('group_feedings');
        Schema::dropIfExists('grouping_rules');
        Schema::dropIfExists('feed_inventories');
        Schema::dropIfExists('feed_purchase_requests');
        Schema::dropIfExists('feed_types');
        Schema::dropIfExists('feeding_standards');
        Schema::dropIfExists('ingredients');

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally left blank.
        // Restoring these tables would require recreating their full schemas,
        // which is out of scope for this cleanup migration.
    }
};

