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
        Schema::table('pig_weights', function (Blueprint $table) {
            $table->enum('health_status', ['excellent', 'good', 'fair', 'poor'])->nullable()->after('body_condition_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pig_weights', function (Blueprint $table) {
            $table->dropColumn('health_status');
        });
    }
};
