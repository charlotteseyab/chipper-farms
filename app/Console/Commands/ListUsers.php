<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Role;
use Illuminate\Console\Command;

class ListUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all users and their roles';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $users = User::all();
        
        if ($users->isEmpty()) {
            $this->info('No users found.');
            return 0;
        }

        $headers = ['ID', 'Email', 'Name', 'Role (string)', 'Role ID', 'Active'];
        $rows = [];

        foreach ($users as $user) {
            $rows[] = [
                $user->id,
                $user->email,
                $user->name ?? ($user->first_name . ' ' . $user->last_name),
                $user->role ?? 'N/A',
                $user->role_id ?? 'N/A',
                $user->active_status ? 'Yes' : 'No',
            ];
        }

        $this->table($headers, $rows);
        
        $this->info("\nTo set a user's role, use: php artisan user:set-role <email> <role>");
        $this->info("Available roles: superuser, admin, farmmanager, dataentry");
        
        return 0;
    }
}
