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
        // Adds the 'bag_weight_kg' column to the 'ingredients' table.
        // This decimal field stores the default weight (in kg) for one stock unit (e.g., 1 bag).
        Schema::table('ingredients', function (Blueprint $table) {
            $table->decimal('bag_weight_kg', 8, 2)->nullable()->after('unit')->comment('Default weight in kg for one stock unit (e.g., 50kg per bag)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropColumn('bag_weight_kg');
        });
    }
};
