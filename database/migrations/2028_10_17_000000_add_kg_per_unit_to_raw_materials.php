<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Recipes are written in kg. Materials stocked in another unit (bags,
        // sacks) need their weight per unit before they can be mixed.
        Schema::table('raw_materials', function (Blueprint $table) {
            $table->decimal('kg_per_unit', 12, 4)->nullable()->after('unit');
        });
    }

    public function down(): void
    {
        Schema::table('raw_materials', function (Blueprint $table) {
            $table->dropColumn('kg_per_unit');
        });
    }
};
