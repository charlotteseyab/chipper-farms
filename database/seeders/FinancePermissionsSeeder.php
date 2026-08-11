<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Phase 1d — Finance permissions seed (corrected from the Phase 1a
 * placeholder which referenced a Permission model that didn't exist).
 *
 * Decision 8: two-step approval with permission-based override.
 *
 * Permission set:
 *   view_finance              View dashboard, trial balance, GL
 *   manage_coa                Create/edit/deactivate accounts (admin-only)
 *   propose_manual_journal    Create draft manual journals
 *   approve_journal           Approve someone else's draft journal
 *   approve_own_journals      Override — propose AND approve in one step
 *   reverse_journal           Reverse a posted batch
 *   lock_period               Place period_lock_dates entries (admin-only)
 *   bypass_period_lock        Override soft locks
 *   manage_fiscal_periods     Open / close / reopen periods (admin-only)
 *   process_outbox            Run finance:process-events command (admin-only)
 *
 * Role mapping:
 *   superuser   All permissions (also bypassed via Gate::before)
 *   admin       All except — actually admin gets ALL too. The reason:
 *               admin = farm owner / GM. Distinct from accountant who is
 *               external/contractor.
 *   accountant  view_finance, propose_manual_journal, approve_journal,
 *               reverse_journal, manage_coa, view-only on periods.
 *               Does NOT have approve_own_journals (segregation of duties).
 *               Does NOT have lock_period or manage_fiscal_periods.
 *   manager     view_finance only.
 *   dataentry   view_finance only.
 *
 * Idempotent — uses firstOrCreate + syncWithoutDetaching.
 */
class FinancePermissionsSeeder extends Seeder
{
    use \Database\Seeders\Concerns\NormalizesSeededRoleNames;

    public function run(): void
    {
        $permissions = $this->definitions();

        $created = [];
        foreach ($permissions as $name => $meta) {
            $created[$name] = Permission::firstOrCreate(
                ['name' => $name],
                [
                    'description' => $meta['description'],
                    'module'      => $meta['module'],
                ]
            );
        }

        $this->attachToRole('admin', array_keys($permissions), $created);

        $this->attachToRole('accountant', [
            'view_finance',
            'manage_coa',
            'propose_manual_journal',
            'approve_journal',
            'reverse_journal',
            'process_outbox',
        ], $created);

        $this->attachToRole('treasurer', [
            'view_finance',
            'reverse_journal',
        ], $created);

        $this->attachToRole('manager', [
            'view_finance',
        ], $created);

        $this->attachToRole('dataentry', [
            'view_finance',
        ], $created);

        // Superuser is also given the explicit permissions (in addition to
        // the Gate::before bypass) so user-management UI shows the full set.
        $this->attachToRole('superuser', array_keys($permissions), $created);
    }

    /** @return array<string, array{description:string, module:string}> */
    protected function definitions(): array
    {
        return [
            'view_finance' => [
                'description' => 'View finance dashboard, trial balance, GL, AR/AP, reports',
                'module'      => 'finance',
            ],
            'manage_coa' => [
                'description' => 'Create, edit, deactivate chart-of-accounts entries',
                'module'      => 'finance',
            ],
            'propose_manual_journal' => [
                'description' => 'Create draft manual journal entries',
                'module'      => 'finance',
            ],
            'approve_journal' => [
                'description' => 'Approve draft journals proposed by other users',
                'module'      => 'finance',
            ],
            'approve_own_journals' => [
                'description' => 'Bypass two-step approval — propose AND approve own journals',
                'module'      => 'finance',
            ],
            'reverse_journal' => [
                'description' => 'Reverse a posted journal or batch',
                'module'      => 'finance',
            ],
            'lock_period' => [
                'description' => 'Place period lock dates',
                'module'      => 'finance',
            ],
            'bypass_period_lock' => [
                'description' => 'Override soft period locks for specific late entries',
                'module'      => 'finance',
            ],
            'manage_fiscal_periods' => [
                'description' => 'Open, close, and reopen fiscal periods',
                'module'      => 'finance',
            ],
            'process_outbox' => [
                'description' => 'Run finance:process-events command',
                'module'      => 'finance',
            ],
        ];
    }

    private function attachToRole(string $roleName, array $permissionNames, array $permissionLookup): void
    {
        $role = $this->seededRoleOrCreate($roleName);

        $ids = collect($permissionNames)
            ->map(fn ($n) => $permissionLookup[$n]?->id ?? null)
            ->filter()
            ->all();

        if (empty($ids)) {
            return;
        }

        $role->permissions()->syncWithoutDetaching($ids);
    }
}
