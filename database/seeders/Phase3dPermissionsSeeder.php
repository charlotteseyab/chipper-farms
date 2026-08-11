<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Phase 3d permissions.
 *
 * Adds:
 *   - view_ap_ageing             : view AP ageing reports + export
 *   - manage_supplier_statements : full supplier-statement reconciliation surface
 *   - manage_wht_certificates    : draft, refresh, view, void, download WHT certs
 *   - issue_wht_certificate      : the formal regulatory act of issuing a cert
 *                                  (treasurer/admin only — narrower than manage_)
 *
 * Why separate `view_ap_ageing` from `view_finance`:
 *   AR ageing has been visible to anyone with view_finance since Phase 2d
 *   (broad). AP ageing exposes supplier balances, payment patterns, and
 *   relationship risk indicators — operationally sensitive. A narrower
 *   default is safer; sites that want it broad can grant the perm to the
 *   relevant roles in one place.
 *
 * Why separate `issue_wht_certificate` from `manage_wht_certificates`:
 *   Drafting / reviewing / refreshing is an accountant-level workflow.
 *   ISSUING is the moment the certificate becomes a regulatory document
 *   that must reconcile with what's been filed with GRA. It also triggers
 *   the allocation-reverse lock (Phase 3d D40). That's a treasurer-grade
 *   responsibility, not a clerical one.
 *
 * Reuses existing perms (no overlap):
 *   - view_finance        : index pages still gated broadly via blades
 *   - export_finance      : CSV exports
 *   - reverse_journal     : (unrelated)
 *   - manage_suppliers    : managing supplier master data (separate)
 *
 * Idempotent. Safe to re-run.
 */
class Phase3dPermissionsSeeder extends Seeder
{
    use \Database\Seeders\Concerns\NormalizesSeededRoleNames;

    public function run(): void
    {
        $perms = [
            'view_ap_ageing' => [
                'name'        => 'View AP Ageing',
                'description' => 'View accounts payable ageing reports and export to CSV.',
                'category'    => 'finance',
            ],
            'manage_supplier_statements' => [
                'name'        => 'Manage Supplier Statements',
                'description' => 'Create, edit, reconcile, and archive supplier statement reconciliations.',
                'category'    => 'finance',
            ],
            'manage_wht_certificates' => [
                'name'        => 'Manage WHT Certificates',
                'description' => 'Draft, refresh, view, void, and download withholding-tax certificates.',
                'category'    => 'finance',
            ],
            'issue_wht_certificate' => [
                'name'        => 'Issue WHT Certificates',
                'description' => 'Formally issue a draft WHT certificate (regulatory act). Locks underlying allocations from reversal.',
                'category'    => 'finance',
            ],
        ];

        foreach ($perms as $code => $attrs) {
            Permission::firstOrCreate(
                ['name' => $code],
                [
                    'name'        => $code,
                    'description' => $attrs['description'],
                    'module'      => $attrs['category'] ?? 'finance',
                ]
            );
        }

        // Role assignments. Adjust role names to match your installation.
        // The default is conservative.
        $assignments = [
            'view_ap_ageing'             => ['admin', 'treasurer', 'accountant', 'superuser'],
            'manage_supplier_statements' => ['admin', 'treasurer', 'accountant', 'superuser'],
            'manage_wht_certificates'    => ['admin', 'treasurer', 'accountant', 'superuser'],
            'issue_wht_certificate'      => ['admin', 'treasurer', 'superuser'], // NOT accountant
        ];

        foreach ($assignments as $code => $roleNames) {
            $perm = Permission::where('name', $code)->first();
            if (! $perm) continue;

            foreach ($roleNames as $roleName) {
                $role = $this->seededRole($roleName);
                if (! $role) continue;
                $role->permissions()->syncWithoutDetaching([$perm->id]);
            }

            $this->command?->info("Phase 3d: granted '{$code}' to: " . implode(', ', $roleNames));
        }

        $this->command?->info(
            "Phase 3d: 'issue_wht_certificate' deliberately NOT granted to 'accountant'. "
            . "Issuance is the formal GRA filing point and locks underlying allocations."
        );
    }
}
