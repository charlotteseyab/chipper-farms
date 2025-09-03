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
            $table->text('description')->nullable()->after('code');
            $table->unsignedInteger('capacity')->nullable()->after('description');
            $table->enum('status', ['active', 'inactive', 'under_maintenance'])->default('active')->after('capacity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('structures', function (Blueprint $table) {
            $table->dropColumn(['description', 'capacity', 'status']);
        });
    }
};
