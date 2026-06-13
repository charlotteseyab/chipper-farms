<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Storefront Phase 2 — quote + commerce-layer permissions. Idempotent and
 * self-sufficient (ensures referenced roles exist, like the Phase 1 seeder).
 */
class StorefrontQuotePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['superuser', 'admin', 'farmmanager', 'accountant', 'salesperson', 'customer'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }

        $perms = [
            'view_storefront_quotes'   => 'View storefront quote requests.',
            'manage_storefront_quotes' => 'Review, price, accept, decline storefront quotes.',
            'manage_storefront_media'  => 'Add/remove/reorder listing media and edit listing content.',
        ];

        foreach ($perms as $code => $description) {
            Permission::firstOrCreate(['name' => $code], [
                'name' => $code, 'description' => $description, 'module' => 'storefront',
            ]);
        }

        $assignments = [
            'view_storefront_quotes'   => ['admin', 'superuser', 'salesperson', 'farmmanager', 'accountant'],
            'manage_storefront_quotes' => ['admin', 'superuser', 'salesperson'],
            'manage_storefront_media'  => ['admin', 'superuser', 'salesperson'],
        ];

        foreach ($assignments as $code => $roleNames) {
            $perm = Permission::where('name', $code)->first();
            if (! $perm) {
                continue;
            }
            foreach ($roleNames as $roleName) {
                Role::where('name', $roleName)->first()?->permissions()->syncWithoutDetaching([$perm->id]);
            }
            $this->command?->info("Storefront P2: granted '{$code}' to: " . implode(', ', $roleNames));
        }
    }
}
