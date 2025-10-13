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
        Schema::table('feed_schedules', function (Blueprint $table) {
            // Add the new column for the skip reason. 
            // It uses text for potentially longer reasons and is nullable since most entries won't be skipped.
            $table->text('skip_reason')->nullable()->after('status');

            // Also, ensure we have a place to log who skipped or fed the schedule
            // Assuming 'users' is the table for staff members (if not already present)
            $table->foreignId('feeder_id')->nullable()->constrained('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feed_schedules', function (Blueprint $table) {
            // Drop the foreign key constraint first
            $table->dropConstrainedForeignId('feeder_id');
            
            // Then drop the column we added
            $table->dropColumn('skip_reason');
        });
    }
};
