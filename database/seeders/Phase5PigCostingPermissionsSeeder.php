<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Phase 5 — pig costing permissions.
 *
 * Adds:
 *   - view_group_pools          Read group cost pools + movement history.
 *                               KPI/reporting role.
 *
 *   - manage_feed_issues        Record feed issuances (group + qty + WAC).
 *                               Operational role — feed clerks.
 *
 *   - manage_pig_mortality      Read mortality writeoff audit. (Recording
 *                               deaths is separately gated by the existing
 *                               manage_pig_deaths permission.)
 *
 *   - manage_opening_pig_pool   Open / import opening pig pools at cutover.
 *                               Privileged: writes to the live pig
 *                               inventory ledger.
 *
 *   - view_pig_cogs_audit       Inspect pig sale COGS posting state.
 *                               Reporting/finance role.
 *
 * Role policy:
 *   admin / superuser           : everything
 *   accountant                  : view_group_pools, view_pig_cogs_audit,
 *                                 manage_pig_mortality
 *   manager / farmmanager       : view_group_pools, manage_feed_issues,
 *                                 manage_pig_mortality
 *   dataentry                   : manage_feed_issues
 *
 * Idempotent.
 */
class Phase5PigCostingPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $perms = [
            'view_group_pools' => [
                'description' => 'View group cost pools and per-group movement history.',
            ],
            'manage_feed_issues' => [
                'description' => 'Record feed issuance from store to pig group.',
            ],
            'manage_pig_mortality' => [
                'description' => 'View the pig mortality writeoff audit log.',
            ],
            'manage_opening_pig_pool' => [
                'description' => 'Import or manually enter opening pig pool balances at cutover.',
            ],
            'view_pig_cogs_audit' => [
                'description' => 'Inspect pig sale COGS posting state and history.',
            ],
        ];

        foreach ($perms as $code => $attrs) {
            Permission::firstOrCreate(
                ['name' => $code],
                [
                    'name'        => $code,
                    'description' => $attrs['description'],
                    'module'      => 'finance',
                ]
            );
        }

        $assignments = [
            'view_group_pools'          => ['admin', 'accountant', 'manager', 'farmmanager', 'superuser'],
            'manage_feed_issues'        => ['admin', 'manager', 'farmmanager', 'dataentry', 'superuser'],
            'manage_pig_mortality'      => ['admin', 'accountant', 'manager', 'farmmanager', 'superuser'],
            'manage_opening_pig_pool'   => ['admin', 'superuser'],
            'view_pig_cogs_audit'       => ['admin', 'accountant', 'superuser'],
        ];

        foreach ($assignments as $code => $roleNames) {
            $perm = Permission::where('name', $code)->first();
            if (! $perm) {
                continue;
            }

            foreach ($roleNames as $roleName) {
                $role = Role::where('name', $roleName)->first();
                if (! $role) {
                    continue;
                }
                $role->permissions()->syncWithoutDetaching([$perm->id]);
            }

            $this->command?->info("Phase 5: granted '{$code}' to: " . implode(', ', $roleNames));
        }

        $this->command?->info(
            "Phase 5: 'manage_opening_pig_pool' restricted to admin/superuser — "
            . "the opening pool write goes directly to the live pig inventory "
            . "ledger and is hard to undo."
        );
    }
}
