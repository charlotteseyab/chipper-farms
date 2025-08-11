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
        Schema::table('matings', function (Blueprint $table) {
            // Farrowing tracking fields
            $table->date('actual_farrowing_date')->nullable()->after('expected_farrowing_date');
            $table->integer('total_piglets')->nullable()->after('actual_farrowing_date');
            $table->integer('live_piglets')->nullable()->after('total_piglets');
            $table->integer('stillborn_piglets')->nullable()->after('live_piglets');
            $table->text('farrowing_notes')->nullable()->after('stillborn_piglets');

            // Pregnancy status tracking
            $table->date('pregnancy_status_date')->nullable()->after('pregnancy_confidence');
            $table->text('pregnancy_status_notes')->nullable()->after('pregnancy_status_date');

            // Expected weaning date
            $table->date('expected_weaning_date')->nullable()->after('farrowing_notes');

            // Index for better performance on farrowing queries
            $table->index(['pregnancy_status', 'expected_farrowing_date']);
            $table->index(['actual_farrowing_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matings', function (Blueprint $table) {
            // Drop indexes
            $table->dropIndex(['pregnancy_status', 'expected_farrowing_date']);
            $table->dropIndex(['actual_farrowing_date']);

            // Drop columns
            $table->dropColumn([
                'actual_farrowing_date',
                'total_piglets',
                'live_piglets',
                'stillborn_piglets',
                'farrowing_notes',
                'pregnancy_status_date',
                'pregnancy_status_notes',
                'expected_weaning_date'
            ]);
        });
    }
};
