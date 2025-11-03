<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * This adds the 'purpose' column to track why medication was given.
     */
    public function up(): void
    {
        Schema::table('medication_administrations', function (Blueprint $table) {
            // Add the new 'purpose' column, allowing it to be nullable and placing it after 'medication_name'
            $table->string('purpose', 255)->nullable()->after('medication_name');
        });
    }

    /**
     * Reverse the migrations.
     * This removes the 'purpose' column if the migration is rolled back.
     */
    public function down(): void
    {
        Schema::table('medication_administrations', function (Blueprint $table) {
            // Use dropColumn to reverse the change
            $table->dropColumn('purpose');
        });
    }
};
