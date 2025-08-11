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
            $table->integer('target_week')->nullable()->after('weigh_date')->comment('Target week number for this weight record (e.g., Week 20)');
            $table->integer('actual_age_weeks')->nullable()->after('target_week')->comment('Actual age in weeks when weighed (calculated from birth date)');
            $table->text('week_notes')->nullable()->after('notes')->comment('Notes specific to this weekly measurement');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pig_weights', function (Blueprint $table) {
            $table->dropColumn(['target_week', 'actual_age_weeks', 'week_notes']);
        });
    }
};
