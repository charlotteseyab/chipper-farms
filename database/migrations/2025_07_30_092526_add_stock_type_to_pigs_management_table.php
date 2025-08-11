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
        Schema::table('pigs_management', function (Blueprint $table) {
            $table->string('stock_type')->default('breeding')->after('gender');
        });

        // Update existing records to be 'breeding' stock (since current page is breeding stocks)
        \DB::table('pigs_management')->update(['stock_type' => 'breeding']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pigs_management', function (Blueprint $table) {
            $table->dropColumn('stock_type');
        });
    }
};
