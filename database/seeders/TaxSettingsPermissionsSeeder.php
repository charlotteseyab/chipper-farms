<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Permission to configure tax (completes Phase 2).
 *
 * Distinct from manage_feature_flags and from view_finance, as prompt §119
 * requires. Tax configuration decides what every future sale and purchase
 * charges — it is closer to editing the chart of accounts than to recording a
 * transaction, and a wrong rate is not a data-entry error but a compliance one.
 *
 * Granted to admin, superuser and accountant. An accountant is precisely who
 * should hold this, and is the one role likely to know whether a levy sits on
 * the net or compounds.
 */
class TaxSettingsPermissionsSeeder extends Seeder
{
    use \Database\Seeders\Concerns\NormalizesSeededRoleNames;

    public function run(): void
    {
        foreach (['superuser', 'admin', 'accountant'] as $roleName) {
            $this->seededRoleOrCreate($roleName);
        }

        $perm = Permission::firstOrCreate(['name' => 'manage_tax_settings'], [
            'name'        => 'manage_tax_settings',
            'description' => 'Define tax profiles and what they charge. Affects every future '
                . 'sale and purchase.',
            'module'      => 'finance',
        ]);

        foreach (['superuser', 'admin', 'accountant'] as $roleName) {
            $this->seededRole($roleName)?->permissions()->syncWithoutDetaching([$perm->id]);
        }

        $this->command?->info("Phase 2: granted 'manage_tax_settings' to admin, superuser, accountant.");
    }
}
