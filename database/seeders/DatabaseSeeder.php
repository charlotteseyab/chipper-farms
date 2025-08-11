<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed the roles table first
        DB::table('roles')->insert([
            ['name' => 'admin'],
            ['name' => 'dataentry'],
            ['name' => 'farmmanager'],
            ['name' => 'superuser'],
        ]);

        // Get role IDs
        $roles = DB::table('roles')->pluck('id', 'name');

        // Insert users with role_id
        DB::table('users')->insert([
            [
                'name' => 'Admin User',
                'email' => 'admin@chipperfarms.com',
                'password' => Hash::make('password123'),
                'role_id' => $roles['admin'],
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Data Entry',
                'email' => 'dataentry@chipperfarms.com',
                'password' => Hash::make('password123'),
                'role_id' => $roles['dataentry'],
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Farm Manager',
                'email' => 'manager@chipperfarms.com',
                'password' => Hash::make('password123'),
                'role_id' => $roles['farmmanager'],
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Super User',
                'email' => 'superuser@chipperfarms.com',
                'password' => Hash::make('password123'),
                'role_id' => $roles['superuser'],
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
