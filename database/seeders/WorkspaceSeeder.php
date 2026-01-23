<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class WorkspaceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create default workspace if it doesn't exist
        \App\Models\Workspace::firstOrCreate(
            ['code' => 'default'],
            [
                'name' => 'Default Workspace',
                'description' => 'Default workspace for existing data',
                'is_default' => true,
            ]
        );
    }
}
