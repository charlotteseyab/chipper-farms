<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feed_recipe_versions', function (Blueprint $table) {
            $table->index(['organization_id', 'feed_type_id', 'effective_from'], 'feed_recipe_effective_index');
        });
        Schema::table('feed_recipe_versions', function (Blueprint $table) {
            $table->dropUnique('feed_recipe_effective_unique');
        });
    }

    public function down(): void
    {
        if (DB::table('feed_recipe_versions')->select('organization_id', 'feed_type_id', 'effective_from')->groupBy('organization_id', 'feed_type_id', 'effective_from')
            ->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Same-day recipe revisions exist; rollback would discard recipe history.');
        }
        Schema::table('feed_recipe_versions', function (Blueprint $table) {
            $table->unique(['organization_id', 'feed_type_id', 'effective_from'], 'feed_recipe_effective_unique');
        });
        Schema::table('feed_recipe_versions', function (Blueprint $table) {
            $table->dropIndex('feed_recipe_effective_index');
        });
    }
};
