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
        // Seed the roles table 
        $rolesList = ['admin', 'dataentry', 'farmmanager', 'superuser'];
        foreach ($rolesList as $role) {
            DB::table('roles')->updateOrInsert(['name' => $role]);
        }

        // Get role IDs
        $roles = DB::table('roles')->pluck('id', 'name');

        
        $users = [
            [
                'name' => 'Admin User',
                'email' => 'admin@chipperfarms.com',
                'password' => 'Adm!n#2025$Xy7',
                'role_id' => $roles['admin'],
            ],
            [
                'name' => 'Data Entry',
                'email' => 'dataentry@chipperfarms.com',
                'password' => 'D@t@#2025$EnTry',
                'role_id' => $roles['dataentry'],
            ],
            [
                'name' => 'Farm Manager',
                'email' => 'manager@chipperfarms.com',
                'password' => 'F@rm#2025$Mngr',
                'role_id' => $roles['farmmanager'],
            ],
            [
                'name' => 'Super User',
                'email' => 'superuser@chipperfarms.com',
                'password' => 'Sup3r#2025$User',
                'role_id' => $roles['superuser'],
            ],
        ];

        // Insert or update users
        foreach ($users as $user) {
            DB::table('users')->updateOrInsert(
                ['email' => $user['email']], 
                [
                    'name' => $user['name'],
                    'password' => Hash::make($user['password']),
                    'role_id' => $user['role_id'],
                    'email_verified_at' => now(),
                    'updated_at' => now(),
                    'created_at' => now(), 
                ]
            );
        }
    }
}
