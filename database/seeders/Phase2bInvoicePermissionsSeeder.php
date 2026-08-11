<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Phase 2b — invoice permissions.
 *
 * Adds:
 *   create_invoice  — author a new invoice (any user with this can also
 *                     cancel a draft they created).
 *   post_invoice    — finalise a draft and post to GL.
 *
 * Why split create from post?
 *   Two-person rule: a junior accountant authors invoices but cannot
 *   commit to GL alone. A senior accountant or admin posts. Same
 *   pattern as journals (propose / approve).
 *
 * Role mapping:
 *   admin       both
 *   accountant  both (small farms typically don't have separate authoring
 *               vs posting accountants — settings can split later if needed)
 *   manager     none (view_finance only, sees invoices read-only)
 *   dataentry   none
 *   superuser   both (also bypasses via Gate::before)
 */
class Phase2bInvoicePermissionsSeeder extends Seeder
{
    use \Database\Seeders\Concerns\NormalizesSeededRoleNames;

    public function run(): void
    {
        $perms = [
            'create_invoice' => [
                'description' => 'Create draft sales invoices and cancel drafts.',
                'module'      => 'finance',
            ],
            'post_invoice' => [
                'description' => 'Post a draft invoice to the GL (finalise and recognise revenue).',
                'module'      => 'finance',
            ],
        ];

        $created = [];
        foreach ($perms as $name => $meta) {
            $created[$name] = Permission::firstOrCreate(
                ['name' => $name],
                $meta,
            );
        }

        foreach (['admin', 'accountant', 'superuser'] as $roleName) {
            $role = $this->seededRoleOrCreate($roleName);
            $role->permissions()->syncWithoutDetaching([
                $created['create_invoice']->id,
                $created['post_invoice']->id,
            ]);
        }
    }
}
