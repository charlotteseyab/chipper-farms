<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Role;
use Illuminate\Console\Command;

class SetUserRole extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:set-role {email} {role}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set a user\'s role (superuser, admin, farmmanager, dataentry)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $roleName = $this->argument('role');

        // Ensure roles exist
        $this->ensureRolesExist();

        // Find user
        $user = User::where('email', $email)->first();
        if (!$user) {
            $this->error("User with email {$email} not found.");
            return 1;
        }

        // Find role
        $role = Role::where('name', $roleName)->first();
        if (!$role) {
            $this->error("Role '{$roleName}' not found. Available roles: superuser, admin, farmmanager, dataentry");
            return 1;
        }

        // Set role both ways (role_id and role string)
        $user->role_id = $role->id;
        $user->role = $role->name;
        $user->save();

        $this->info("Successfully set role '{$roleName}' for user {$email}");
        return 0;
    }

    /**
     * Ensure all required roles exist in the database
     */
    protected function ensureRolesExist()
    {
        $roles = ['superuser', 'admin', 'farmmanager', 'dataentry'];
        
        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }
        
        $this->info('Ensured all roles exist in database.');
    }
}
