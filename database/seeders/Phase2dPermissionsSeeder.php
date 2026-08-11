<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Phase 2d permissions.
 *
 * Adds:
 *   - create_refund   : draft a refund (no GL impact)
 *   - approve_refund  : post a draft refund to GL — separate by design
 *                       (two-person rule for outgoing money)
 *
 * Reuses existing perms:
 *   - export_finance  : ageing CSV export (Phase 1d)
 *   - reverse_journal : refund reversal (Phase 1c)
 *   - view_finance    : ageing report viewing, statements, attachments download
 *   - create_invoice  : attachment upload to invoices
 *   - create_receipt  : attachment upload to receipts
 *
 * Idempotent.
 */
class Phase2dPermissionsSeeder extends Seeder
{
    use \Database\Seeders\Concerns\NormalizesSeededRoleNames;

    public function run(): void
    {
        $createRefund = Permission::firstOrCreate(
            ['name' => 'create_refund'],
            [
                'description' => 'Draft refunds. Posting requires a separate approval permission.',
                'module'      => 'finance',
            ],
        );

        $approveRefund = Permission::firstOrCreate(
            ['name' => 'approve_refund'],
            [
                'description' => 'Post draft refunds to GL. Two-person rule: distinct from create_refund.',
                'module'      => 'finance',
            ],
        );

        // create_refund: admin, accountant, superuser
        // approve_refund: admin, superuser ONLY (NOT accountant — two-person rule)
        $assignments = [
            'create_refund'  => ['admin', 'accountant', 'superuser'],
            'approve_refund' => ['admin', 'superuser'],
        ];

        foreach ($assignments as $code => $roleNames) {
            $perm = Permission::where('name', $code)->first();
            if (! $perm) continue;

            foreach ($roleNames as $roleName) {
                $role = $this->seededRole($roleName);
                if (! $role) continue;
                if (! $role->permissions()->where('permissions.id', $perm->id)->exists()) {
                    $role->permissions()->attach($perm->id);
                }
            }

            $this->command?->info("Phase 2d: granted '{$code}' to: " . implode(', ', $roleNames));
        }
    }
}
