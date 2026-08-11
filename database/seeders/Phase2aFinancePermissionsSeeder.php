<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Phase 2a — adds the manage_customers permission to the Phase 1d
 * permission catalogue. Idempotent.
 *
 * Run order:
 *   1. Phase 1d FinancePermissionsSeeder (already deployed)
 *   2. THIS seeder, which extends the catalogue
 *
 * Why a separate seeder rather than re-editing FinancePermissionsSeeder?
 *   - The 1d seeder ran once on production. Re-editing it and re-running
 *     would work (it's idempotent), but tracking which Phase added what
 *     becomes harder over time. A separate seeder per phase keeps the
 *     audit trail explicit.
 */
class Phase2aFinancePermissionsSeeder extends Seeder
{
    use \Database\Seeders\Concerns\NormalizesSeededRoleNames;

    public function run(): void
    {
        $manageCustomers = Permission::firstOrCreate(
            ['name' => 'manage_customers'],
            [
                'description' => 'Create, edit, deactivate customers; manage their addresses, contacts, credit holds.',
                'module'      => 'finance',
            ],
        );

        // Attach to admin, accountant, superuser. Manager and dataentry
        // get view-only via existing 'view_finance' permission.
        foreach (['admin', 'accountant', 'superuser'] as $roleName) {
            $role = $this->seededRoleOrCreate($roleName);
            $role->permissions()->syncWithoutDetaching([$manageCustomers->id]);
        }
    }
}
