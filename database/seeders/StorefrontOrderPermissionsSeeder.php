<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Storefront Phase 3 — order/checkout permissions. Idempotent and
 * self-sufficient (ensures roles exist before granting).
 *
 * Confirmation and cancellation are split into their own permissions so an
 * organisation can let a junior salesperson manage/view orders while reserving
 * confirm/cancel for seniors — though by default Salesperson gets all three.
 */
class StorefrontOrderPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['superuser', 'admin', 'farmmanager', 'accountant', 'salesperson', 'customer'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }

        $perms = [
            'view_storefront_orders'    => 'View storefront orders in the back office.',
            'manage_storefront_orders'  => 'Update fulfilment status and manage storefront orders.',
            'confirm_storefront_orders' => 'Confirm storefront orders (reserves inventory).',
            'cancel_storefront_orders'  => 'Cancel storefront orders (releases inventory).',
        ];

        foreach ($perms as $code => $description) {
            Permission::firstOrCreate(['name' => $code], [
                'name' => $code, 'description' => $description, 'module' => 'storefront',
            ]);
        }

        $assignments = [
            'view_storefront_orders'    => ['admin', 'superuser', 'salesperson', 'farmmanager', 'accountant'],
            'manage_storefront_orders'  => ['admin', 'superuser', 'salesperson'],
            'confirm_storefront_orders' => ['admin', 'superuser', 'salesperson'],
            'cancel_storefront_orders'  => ['admin', 'superuser', 'salesperson'],
        ];

        foreach ($assignments as $code => $roleNames) {
            $perm = Permission::where('name', $code)->first();
            if (! $perm) {
                continue;
            }
            foreach ($roleNames as $roleName) {
                Role::where('name', $roleName)->first()?->permissions()->syncWithoutDetaching([$perm->id]);
            }
            $this->command?->info("Storefront P3: granted '{$code}' to: " . implode(', ', $roleNames));
        }
    }
}
