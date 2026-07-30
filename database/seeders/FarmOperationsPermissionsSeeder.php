<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Farm-operations permission catalogue (CF-P0-002).
 *
 * Why this seeder exists
 * ----------------------
 * The permission catalogue covered finance (51), sales (2) and storefront (15)
 * and contained NOTHING for farm operations. Health records, medication,
 * weights, farrowing, facilities, feed production and raw materials had no
 * permission to gate them with, which is why 65 routes across seven
 * controllers sat behind nothing but the broad authenticated group: any
 * verified, active user with an organisation could create, edit and delete
 * livestock health and medication records, mix feed, adjust raw-material
 * stock, and add or remove facilities by direct request.
 *
 * CF-P0-002 therefore could not be fixed by wiring existing permissions. The
 * catalogue had to be created first, and this seeder is that catalogue.
 *
 * Design notes
 * ------------
 * View and manage are split throughout, because the read and write risks
 * differ sharply: reading a medication history is routine, while editing or
 * deleting one destroys the audit trail behind a withdrawal period.
 *
 * Where an existing permission already covers a concept it is NOT duplicated.
 * In particular `manage_pig_mortality` (module finance) already governs adult
 * pig mortality and its costing consequences; the permission added here is
 * `manage_piglet_deaths`, which is the distinct pre-weaning case recorded
 * against a farrowing.
 *
 * Idempotent — firstOrCreate + syncWithoutDetaching. Safe to re-run.
 */
class FarmOperationsPermissionsSeeder extends Seeder
{
    /**
     * @return array<string, array{description: string, module: string}>
     */
    protected function definitions(): array
    {
        return [
            // --- Health and medication (module: livestock) ---
            'view_health_records' => [
                'description' => 'View pig health records, diagnoses and treatment history.',
                'module'      => 'livestock',
            ],
            'manage_health_records' => [
                'description' => 'Create and amend pig health records.',
                'module'      => 'livestock',
            ],
            'delete_health_records' => [
                'description' => 'Delete health records. Destroys clinical history; separated deliberately.',
                'module'      => 'livestock',
            ],
            'administer_medication' => [
                'description' => 'Record administration of medication or vaccination to a pig.',
                'module'      => 'livestock',
            ],
            'manage_medication_records' => [
                'description' => 'Amend or delete medication records. Affects withdrawal-period traceability.',
                'module'      => 'livestock',
            ],

            // --- Weights (module: livestock) ---
            'view_weights' => [
                'description' => 'View pig weight records, growth reports and weight exports.',
                'module'      => 'livestock',
            ],
            'record_weights' => [
                'description' => 'Record and amend pig weights, and manage weight alerts.',
                'module'      => 'livestock',
            ],

            // --- Farrowing and piglets (module: livestock) ---
            'view_farrowing' => [
                'description' => 'View farrowing records and piglet tracking.',
                'module'      => 'livestock',
            ],
            'manage_farrowing' => [
                'description' => 'Amend farrowing records including litter counts.',
                'module'      => 'livestock',
            ],
            'manage_piglet_deaths' => [
                'description' => 'Record, amend or remove pre-weaning piglet mortality against a farrowing.',
                'module'      => 'livestock',
            ],

            // --- Facilities (module: facilities) ---
            'view_facilities' => [
                'description' => 'View structures, rooms and pen occupancy.',
                'module'      => 'facilities',
            ],
            'manage_facilities' => [
                'description' => 'Create, amend or remove structures and rooms.',
                'module'      => 'facilities',
            ],

            // --- Feed and raw materials (module: feed) ---
            'view_feed_production' => [
                'description' => 'View feed production, feed types and recipes.',
                'module'      => 'feed',
            ],
            'manage_feed_production' => [
                'description' => 'Mix feed batches and manage feed types and recipes.',
                'module'      => 'feed',
            ],
            'view_raw_materials' => [
                'description' => 'View raw-material stock levels and movement history.',
                'module'      => 'feed',
            ],
            'manage_raw_materials' => [
                'description' => 'Create raw materials and record receipts and adjustments.',
                'module'      => 'feed',
            ],
        ];
    }

    /**
     * Role grants.
     *
     * Deliberately excluded everywhere: `customer` and `salesperson`. Neither
     * has any business writing livestock, feed or facility records, and before
     * this seeder both could.
     *
     * `dataentry` gets record/create rights but NOT the destructive ones
     * (delete_health_records, manage_medication_records, manage_piglet_deaths,
     * manage_facilities) — recording data is the role's purpose; erasing
     * clinical or mortality history is not.
     *
     * `mill_operator` gets feed and raw materials only, matching its existing
     * single grant of view_inventory_value.
     *
     * @return array<string, list<string>>
     */
    protected function assignments(): array
    {
        $farmwide = ['admin', 'superuser', 'farmmanager', 'manager'];
        $withData = ['admin', 'superuser', 'farmmanager', 'manager', 'dataentry'];
        $feed     = ['admin', 'superuser', 'farmmanager', 'manager', 'mill_operator'];
        $feedView = ['admin', 'superuser', 'farmmanager', 'manager', 'dataentry', 'mill_operator'];

        return [
            'view_health_records'       => $withData,
            'manage_health_records'     => $withData,
            'delete_health_records'     => $farmwide,
            'administer_medication'     => $withData,
            'manage_medication_records' => $farmwide,

            'view_weights'              => $withData,
            'record_weights'            => $withData,

            'view_farrowing'            => $withData,
            'manage_farrowing'          => $withData,
            'manage_piglet_deaths'      => $farmwide,

            'view_facilities'           => $withData,
            'manage_facilities'         => $farmwide,

            'view_feed_production'      => $feedView,
            'manage_feed_production'    => $feed,
            'view_raw_materials'        => $feedView,
            'manage_raw_materials'      => $feed,
        ];
    }

    public function run(): void
    {
        $created = [];

        foreach ($this->definitions() as $name => $meta) {
            $created[$name] = Permission::firstOrCreate(
                ['name' => $name],
                [
                    'description' => $meta['description'],
                    'module'      => $meta['module'],
                ],
            );
        }

        foreach ($this->assignments() as $permissionName => $roleNames) {
            $permission = $created[$permissionName] ?? null;

            if ($permission === null) {
                continue;
            }

            foreach ($roleNames as $roleName) {
                Role::firstOrCreate(['name' => $roleName])
                    ->permissions()
                    ->syncWithoutDetaching([$permission->id]);
            }
        }

        $this->command?->info(
            'Farm operations: seeded ' . count($created) . ' permissions across livestock, facilities and feed.',
        );
        $this->command?->warn(
            "Farm operations: 'customer' and 'salesperson' are deliberately granted none of these."
        );
    }
}
