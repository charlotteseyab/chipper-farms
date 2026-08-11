<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Phase 9 — investor / fund permissions. Idempotent.
 *
 *   view_investors    view investors, statements, cap table, reconciliation
 *   manage_investors  create investors, record contributions/distributions,
 *                     run profit allocation, preferred-share returns
 */
class Phase9InvestorPermissionsSeeder extends Seeder
{
    use \Database\Seeders\Concerns\NormalizesSeededRoleNames;

    public function run(): void
    {
        $perms = [
            'view_investors'   => 'View investors, capital statements, cap table, and reconciliation.',
            'manage_investors' => 'Create investors and post contributions, distributions, profit allocation, and preferred returns.',
        ];

        foreach ($perms as $code => $description) {
            Permission::firstOrCreate(['name' => $code], [
                'name' => $code, 'description' => $description, 'module' => 'finance',
            ]);
        }

        $assignments = [
            'view_investors'   => ['admin', 'accountant', 'superuser'],
            'manage_investors' => ['admin', 'accountant', 'superuser'],
        ];

        foreach ($assignments as $code => $roleNames) {
            $perm = Permission::where('name', $code)->first();
            if (! $perm) {
                continue;
            }
            foreach ($roleNames as $roleName) {
                $role = $this->seededRole($roleName);
                $role?->permissions()->syncWithoutDetaching([$perm->id]);
            }
            $this->command?->info("Phase 9: granted '{$code}' to: " . implode(', ', $roleNames));
        }
    }
}
