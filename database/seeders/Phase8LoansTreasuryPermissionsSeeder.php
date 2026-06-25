<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Phase 8 — loans + treasury permissions.
 *
 *   view_loans      view loans, schedules, reclassification report
 *   manage_loans    create, disburse, record repayments
 *   view_treasury   cash-position dashboard
 *
 * Idempotent.
 */
class Phase8LoansTreasuryPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $perms = [
            'view_loans'    => ['description' => 'View loans, amortisation schedules, and the current/long-term reclassification report.'],
            'manage_loans'  => ['description' => 'Create, disburse, and record repayments on loans (posts to the GL).'],
            'view_treasury' => ['description' => 'View the treasury cash-position dashboard.'],
        ];

        foreach ($perms as $code => $attrs) {
            Permission::firstOrCreate(['name' => $code], [
                'name' => $code, 'description' => $attrs['description'], 'module' => 'finance',
            ]);
        }

        $assignments = [
            'view_loans'    => ['admin', 'accountant', 'manager', 'farmmanager', 'superuser'],
            'manage_loans'  => ['admin', 'accountant', 'superuser'],
            'view_treasury' => ['admin', 'accountant', 'manager', 'farmmanager', 'superuser'],
        ];

        foreach ($assignments as $code => $roleNames) {
            $perm = Permission::where('name', $code)->first();
            if (! $perm) {
                continue;
            }
            foreach ($roleNames as $roleName) {
                $role = Role::where('name', $roleName)->first();
                if ($role) {
                    $role->permissions()->syncWithoutDetaching([$perm->id]);
                }
            }
            $this->command?->info("Phase 8: granted '{$code}' to: " . implode(', ', $roleNames));
        }
    }
}
