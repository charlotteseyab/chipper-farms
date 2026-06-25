<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Phase 3b permissions.
 *
 * Adds two new permissions implementing the two-person rule (Decision 30):
 *
 *   create_vendor_invoice    Allows drafting, submitting for approval,
 *                            cancelling drafts, and editing draft lines.
 *                            Junior accountants get this.
 *
 *   create_purchase_request_vendor_invoice
 *                            Allows farm managers to draft/submit/cancel
 *                            vendor invoices only when linked to a purchase
 *                            request. It does not grant general VI creation.
 *
 *   approve_vendor_invoice   Allows approving and posting a vendor invoice
 *                            to GL. Distinct from create_vendor_invoice
 *                            so the same user can't draft AND post (the
 *                            controller enforces creator != approver).
 *                            Treasury and CFO get this.
 *
 * Role grants:
 *   admin       → both (admin can override two-person via config flag)
 *   accountant  → create only (NOT approve — must hand off to treasury)
 *   treasurer   → both (drafts rare for treasury but technically allowed)
 *   superuser   → both
 *
 * Managers do not get general vendor invoice permissions. They only receive
 * the PR-scoped drafting permission below.
 *
 * Idempotent.
 */
class Phase3bVendorInvoicePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $createPerm = Permission::firstOrCreate(
            ['name' => 'create_vendor_invoice'],
            [
                'description' => 'Draft, edit, submit for approval, and cancel vendor invoices',
                'module'      => 'finance',
            ],
        );

        $approvePerm = Permission::firstOrCreate(
            ['name' => 'approve_vendor_invoice'],
            [
                'description' => 'Approve a vendor invoice and post it to GL. Subject to two-person rule.',
                'module'      => 'finance',
            ],
        );

        $prScopedCreatePerm = Permission::firstOrCreate(
            ['name' => 'create_purchase_request_vendor_invoice'],
            [
                'description' => 'Draft, submit, and cancel vendor invoices only when linked to a purchase request',
                'module'      => 'finance',
            ],
        );

        // Role grants
        $createGrants = ['admin', 'accountant', 'treasurer', 'superuser'];
        foreach ($createGrants as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if (! $role) continue;
            if (! $role->permissions()->where('permissions.id', $createPerm->id)->exists()) {
                $role->permissions()->attach($createPerm->id);
            }
        }

        $approveGrants = ['admin', 'treasurer', 'superuser'];
        foreach ($approveGrants as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if (! $role) continue;
            if (! $role->permissions()->where('permissions.id', $approvePerm->id)->exists()) {
                $role->permissions()->attach($approvePerm->id);
            }
        }

        $prScopedCreateGrants = ['manager', 'farmmanager'];
        foreach ($prScopedCreateGrants as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if (! $role) continue;
            if (! $role->permissions()->where('permissions.id', $prScopedCreatePerm->id)->exists()) {
                $role->permissions()->attach($prScopedCreatePerm->id);
            }
        }

        $this->command?->info("Phase 3b: granted 'create_vendor_invoice' to: " . implode(', ', $createGrants));
        $this->command?->info("Phase 3b: granted 'approve_vendor_invoice' to: " . implode(', ', $approveGrants));
        $this->command?->info("Phase 3b: granted 'create_purchase_request_vendor_invoice' to: " . implode(', ', $prScopedCreateGrants));
        $this->command?->warn("Note: 'accountant' has create but NOT approve — two-person rule per Decision 30.");
    }
}
