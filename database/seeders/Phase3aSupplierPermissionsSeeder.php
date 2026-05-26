<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Phase 3a permissions.
 *
 * Adds:
 *   - manage_suppliers : create/edit/deactivate suppliers, edit categories
 *
 * Reuses:
 *   - view_finance     : viewing supplier list and detail (existing)
 *
 * Phase 3b will add create_vendor_invoice and approve_vendor_invoice
 * separately. Phase 3c will add create_payment and approve_payment.
 *
 * Idempotent.
 */
class Phase3aSupplierPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $perm = Permission::firstOrCreate(
            ['code' => 'manage_suppliers'],
            [
                'name'        => 'Manage Suppliers',
                'description' => 'Create, edit, and deactivate suppliers; manage supplier categories',
                'category'    => 'finance',
            ],
        );

        $rolesGetPerm = ['admin', 'accountant', 'superuser'];

        foreach ($rolesGetPerm as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if (! $role) continue;
            if (! $role->permissions()->where('permissions.id', $perm->id)->exists()) {
                $role->permissions()->attach($perm->id);
            }
        }

        $this->command?->info("Phase 3a: granted 'manage_suppliers' to: " . implode(', ', $rolesGetPerm));
    }
}
