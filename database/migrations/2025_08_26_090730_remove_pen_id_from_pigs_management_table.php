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
        if (Schema::hasColumn('pigs_management', 'pen_id')) {
            Schema::table('pigs_management', function (Blueprint $table) {
                // Drop the column if it exists (foreign key constraint will be removed as needed)
                $table->dropColumn('pen_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pigs_management', function (Blueprint $table) {
            $table->foreignId('pen_id')->nullable()->constrained('pens')->nullOnDelete()->after('mother_id');
        });
    }
};
