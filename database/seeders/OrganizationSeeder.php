<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;

/**
 * Seeds the default Chipper Farms organization.
 *
 * Idempotent: keyed on slug so re-running is a no-op.
 */
class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        Organization::firstOrCreate(
            ['slug' => 'chipper-farms'],
            [
                'name' => 'Chipper Farms',
                'legal_name' => 'Chipper Farms Limited',
                'base_currency' => 'GHS',
                'currency_decimals' => 2,
                'country_code' => 'GH',
                'timezone' => 'Africa/Accra',
                'is_active' => true,
            ]
        );
    }
}

