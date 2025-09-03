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
        Schema::table('pig_sales', function (Blueprint $table) {
            $table->enum('weight_type', ['live', 'carcass'])->default('live')->after('sale_weight');
            $table->decimal('price_per_kg', 8, 2)->nullable()->after('weight_type'); // Store the rate used
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pig_sales', function (Blueprint $table) {
            $table->dropColumn(['weight_type', 'price_per_kg']);
        });
    }
};