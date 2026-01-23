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
        // Get or create default workspace
        $defaultWorkspace = \App\Models\Workspace::firstOrCreate(
            ['code' => 'default'],
            [
                'name' => 'Default Workspace',
                'description' => 'Default workspace for existing data',
                'is_default' => true,
            ]
        );

        // Assign existing users to default workspace if they don't have one
        \App\Models\User::whereNull('workspace_id')->update(['workspace_id' => $defaultWorkspace->id]);

        // Assign existing data to default workspace
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
            if (\Illuminate\Support\Facades\Schema::hasTable($tableName) && \Illuminate\Support\Facades\Schema::hasColumn($tableName, 'workspace_id')) {
                \Illuminate\Support\Facades\DB::table($tableName)
                    ->whereNull('workspace_id')
                    ->update(['workspace_id' => $defaultWorkspace->id]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration doesn't need a down method as it's just assigning data
    }
};
