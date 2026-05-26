<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Phase 3c — payment permissions.
 *
 * Adds:
 *   create_payment   Allows creating payments AND payment runs, including
 *                    allocations, reconciliation, and FIFO auto-allocate.
 *
 * Critical: NOT granted to 'accountant' (Decision 33).
 *   - Accountants can DRAFT vendor invoices (Phase 3b — create_vendor_invoice)
 *     but cannot release cash. Cash-out authorisation is treasury's job.
 *   - This is the AP segregation-of-duties control: drafting and paying
 *     are separate.
 *
 * Granted to: admin, treasurer, superuser.
 *
 * Reversal of payments still uses the existing `reverse_journal`
 * permission from Phase 2d, granted to admin/treasurer/superuser.
 *
 * Idempotent.
 */
class Phase3cPaymentPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $createPaymentPerm = Permission::firstOrCreate(
            ['code' => 'create_payment'],
            [
                'name'        => 'Create Payment',
                'description' => 'Create payments and payment runs, allocate to vendor invoices, reconcile against bank statements. Cash-out authorisation.',
                'category'    => 'finance',
            ],
        );

        $grants = ['admin', 'treasurer', 'superuser'];
        foreach ($grants as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if (! $role) continue;
            if (! $role->permissions()->where('permissions.id', $createPaymentPerm->id)->exists()) {
                $role->permissions()->attach($createPaymentPerm->id);
            }
        }

        $this->command?->info("Phase 3c: granted 'create_payment' to: " . implode(', ', $grants));
        $this->command?->warn("Note: 'accountant' is EXCLUDED from create_payment by design (Decision 33). " .
            "Accountants draft VIs; treasury releases cash.");
    }
}
