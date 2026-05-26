<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Phase 4 — inventory + procurement permissions.
 *
 * Adds:
 *   - view_inventory_value          List the unified inventory ledger and
 *                                   drill into per-item movement history.
 *   - manage_inventory_mappings     CRUD on the inventory_account_mappings
 *                                   table. Privileged: changes GL routing
 *                                   for the whole org.
 *   - manage_opening_balances       Import / manually-enter opening
 *                                   balances. Privileged: writes to the
 *                                   ledger.
 *   - override_negative_inventory   Bypass the on-hand-cannot-go-negative
 *                                   guard at adjustment time. Used for
 *                                   end-of-period reconciliation when
 *                                   real-world stock is somehow less than
 *                                   the system says.
 *   - view_gr_ir_ageing             Read the GR/IR ageing report.
 *   - receive_against_pr            Use the guided receive-against-PR flow.
 *   - override_match_variance       Force-post a vendor invoice line whose
 *                                   variance exceeds tolerance, OR
 *                                   over-receive against a PR line.
 *
 * Role policy:
 *   admin / superuser              : everything.
 *   accountant                     : view_inventory_value, view_gr_ir_ageing,
 *                                    manage_inventory_mappings.
 *   manager / farmmanager          : view_inventory_value,
 *                                    receive_against_pr.
 *   dataentry                      : receive_against_pr (the most common
 *                                    operational task).
 *   mill_operator                  : view_inventory_value (read-only).
 *
 * Idempotent.
 */
class Phase4InventoryPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $perms = [
            'view_inventory_value' => [
                'description' => 'List unified inventory balances and drill into per-item movement history.',
            ],
            'manage_inventory_mappings' => [
                'description' => 'CRUD on inventory_account_mappings (GL routing for inventory).',
            ],
            'manage_opening_balances' => [
                'description' => 'Import or manually enter inventory opening balances.',
            ],
            'override_negative_inventory' => [
                'description' => 'Bypass the on-hand-cannot-go-negative guard at adjustment time.',
            ],
            'view_gr_ir_ageing' => [
                'description' => 'View the GR/IR (Goods Received Not Invoiced) ageing report.',
            ],
            'receive_against_pr' => [
                'description' => 'Use the guided receive-against-PR flow.',
            ],
            'override_match_variance' => [
                'description' => 'Force-post a vendor invoice line whose variance exceeds tolerance, or over-receive against a PR line.',
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
            'view_inventory_value'         => ['admin', 'accountant', 'manager', 'farmmanager', 'superuser', 'mill_operator'],
            'manage_inventory_mappings'    => ['admin', 'accountant', 'superuser'],
            'manage_opening_balances'      => ['admin', 'superuser'],
            'override_negative_inventory'  => ['admin', 'superuser'],
            'view_gr_ir_ageing'            => ['admin', 'accountant', 'superuser'],
            'receive_against_pr'           => ['admin', 'accountant', 'farmmanager', 'manager', 'dataentry', 'superuser'],
            'override_match_variance'      => ['admin', 'superuser'],
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

            $this->command?->info("Phase 4: granted '{$code}' to: " . implode(', ', $roleNames));
        }

        $this->command?->info(
            "Phase 4: 'manage_opening_balances', 'override_negative_inventory', "
            . "and 'override_match_variance' restricted to admin/superuser — these "
            . "are bookkeeping overrides that can corrupt the ledger if misused."
        );
    }
}
