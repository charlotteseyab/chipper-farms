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
        Schema::table('structures', function (Blueprint $table) {
            // Drop the existing status column
            $table->dropColumn('status');
        });
        
        Schema::table('structures', function (Blueprint $table) {
            // Recreate with the new enum values
            $table->enum('status', ['active', 'inactive', 'under_maintenance'])->default('active')->after('capacity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('structures', function (Blueprint $table) {
            // Drop the new status column
            $table->dropColumn('status');
        });
        
        Schema::table('structures', function (Blueprint $table) {
            // Recreate with the old enum values
            $table->enum('status', ['active', 'inactive', 'maintenance'])->default('active')->after('capacity');
        });
    }
};
