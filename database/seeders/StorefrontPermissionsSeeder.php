<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Storefront Phase 1 — permissions, the Salesperson role, and the Customer
 * role. Idempotent: safe to run repeatedly and on a live system.
 *
 * Design notes
 * ------------
 * - Back-office storefront routes are gated by `permission:` middleware (the
 *   finance-module pattern), NOT `role:`. This keeps gating granular and lets
 *   admin/superuser inherit everything via the superuser bypass + explicit
 *   grants.
 * - The Salesperson role manages listings/placements/orders within scope but
 *   is deliberately NOT granted sensitive finance permissions (refund approval,
 *   payment configuration) — those stay with admin/accountant per the brief.
 * - The Customer role holds NO operational/finance permissions. It exists so
 *   storefront customers can authenticate and reach their account area while
 *   being naturally locked out of every back-office route.
 */
class StorefrontPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure every role referenced below exists. The DatabaseSeeder
        //    creates the standard roles AFTER the permission seeders run, so a
        //    permission seeder must be self-sufficient and not assume they are
        //    present yet — otherwise grants are silently skipped on a fresh
        //    install.
        foreach (['superuser', 'admin', 'farmmanager', 'accountant', 'salesperson', 'customer'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }

        // 2. Storefront permissions (Phase 1 surface; later phases extend).
        $perms = [
            // Back-office workspace + catalogue management.
            'view_storefront_dashboard'   => 'View the storefront back-office dashboard.',
            'view_storefront_listings'    => 'View storefront listings in the back office.',
            'manage_storefront_listings'  => 'Create, edit, publish, unpublish and archive listings.',
            'push_to_storefront'          => 'Push pigs / lots / products from operations into a storefront listing.',
            'manage_storefront_placements'=> 'Manage homepage / featured placement slots.',
            'manage_storefront_settings'  => 'Change storefront settings (store info, pricing defaults, delivery).',
            'view_storefront_reports'     => 'View storefront sales reports relevant to the seller.',
        ];

        foreach ($perms as $code => $description) {
            Permission::firstOrCreate(['name' => $code], [
                'name' => $code, 'description' => $description, 'module' => 'storefront',
            ]);
        }

        // 3. Role → permission assignments.
        $assignments = [
            'view_storefront_dashboard'    => ['admin', 'superuser', 'salesperson', 'manager', 'accountant'],
            'view_storefront_listings'     => ['admin', 'superuser', 'salesperson', 'manager', 'accountant'],
            'manage_storefront_listings'   => ['admin', 'superuser', 'salesperson'],
            'push_to_storefront'           => ['admin', 'superuser', 'salesperson', 'manager'],
            'manage_storefront_placements' => ['admin', 'superuser', 'salesperson'],
            'manage_storefront_settings'   => ['admin', 'superuser'],
            'view_storefront_reports'      => ['admin', 'superuser', 'salesperson', 'accountant', 'manager'],
        ];

        // 'manager' is stored as 'farmmanager' in this system.
        $roleAlias = ['manager' => 'farmmanager'];

        foreach ($assignments as $code => $roleNames) {
            $perm = Permission::where('name', $code)->first();
            if (! $perm) {
                continue;
            }

            foreach ($roleNames as $roleName) {
                $name = $roleAlias[$roleName] ?? $roleName;
                $role = Role::where('name', $name)->first();
                $role?->permissions()->syncWithoutDetaching([$perm->id]);
            }

            $this->command?->info("Storefront: granted '{$code}' to: " . implode(', ', $roleNames));
        }
    }
}
