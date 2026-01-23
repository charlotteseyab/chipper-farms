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
        // Add workspace_id to main data tables
        $tables = [
            'pigs_management',
            'farrowing',
            'matings',
            'weanings',
            'pig_weights',
            'pig_sales',
            'pig_deaths',
            'health_records',
            'structures',
            'feed_schedules',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreignId('workspace_id')->nullable()->after('id')->constrained('workspaces')->onDelete('cascade');
                    $table->index('workspace_id');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'pigs_management',
            'farrowing',
            'matings',
            'weanings',
            'pig_weights',
            'pig_sales',
            'pig_deaths',
            'health_records',
            'structures',
            'feed_schedules',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropForeign(['workspace_id']);
                    $table->dropIndex(['workspace_id']);
                    $table->dropColumn('workspace_id');
                });
            }
        }
    }
};
