<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Phase 1 — permission to manage organisation modules and features.
 *
 * ADMIN-GRADE, DELIBERATELY NARROW
 * --------------------------------
 * Blueprint §49 places "storefront settings" and equivalent configuration at
 * Admin only, and this is broader than that: turning a module off changes what
 * every user in the organisation can reach, and applying a preset exclusively
 * can switch off an accountant's entire ledger surface in one action.
 *
 * It is therefore granted to admin and superuser only — not to farmmanager,
 * accountant or treasurer. An accountant who needs Advanced Accounting switched
 * on asks an administrator; that is a one-off request, and the alternative is
 * letting any finance role reshape the product for everyone else.
 *
 * A separate permission rather than reusing an existing one, because prompt
 * §119 explicitly asks for `manage feature flags` to be distinct and warns
 * against broad `manage_finance`-style grants.
 */
class OrganizationFeaturePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['superuser', 'admin'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }

        $perm = Permission::firstOrCreate(['name' => 'manage_feature_flags'], [
            'name'        => 'manage_feature_flags',
            'description' => 'Turn organisation modules and features on or off, and apply module presets. '
                . 'Changes what every user in the organisation can reach. Admin-grade.',
            'module'      => 'settings',
        ]);

        foreach (['admin', 'superuser'] as $roleName) {
            Role::where('name', $roleName)->first()?->permissions()->syncWithoutDetaching([$perm->id]);
        }

        $this->command?->info(
            "Phase 1: granted 'manage_feature_flags' to: admin, superuser "
            . '(NOT farmmanager, accountant or treasurer).'
        );
    }
}
