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
            // Pregnancy tracking fields
            $table->date('pregnancy_check_date')->nullable()->after('notes');
            $table->enum('pregnancy_status', ['pending', 'pregnant', 'not_pregnant', 'uncertain'])->default('pending')->after('pregnancy_check_date');
            $table->enum('pregnancy_check_method', ['physical_exam', 'blood_test', 'ultrasound', 'behavioral_signs', 'other'])->nullable()->after('pregnancy_status');
            $table->text('pregnancy_check_notes')->nullable()->after('pregnancy_check_method');
            $table->enum('pregnancy_confidence', ['high', 'medium', 'low'])->nullable()->after('pregnancy_check_notes');
            $table->unsignedBigInteger('checked_by')->nullable()->after('pregnancy_confidence');

            // Foreign key for user who performed the check
            $table->foreign('checked_by')->references('id')->on('users')->onDelete('set null');

            // Index for better performance on pregnancy status queries
            $table->index(['pregnancy_status', 'pregnancy_check_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matings', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['checked_by']);

            // Drop indexes
            $table->dropIndex(['pregnancy_status', 'pregnancy_check_date']);

            // Drop columns
            $table->dropColumn([
                'pregnancy_check_date',
                'pregnancy_status',
                'pregnancy_check_method',
                'pregnancy_check_notes',
                'pregnancy_confidence',
                'checked_by'
            ]);
        });
    }
};
