<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Organization and roles first — downstream seeders depend on them.
        $this->call(OrganizationSeeder::class);

        $defaultOrgId = DB::table('organizations')
            ->where('slug', 'chipper-farms')
            ->value('id');

        $rolesList = [
            'admin',
            'dataentry',
            'farmmanager',
            'manager',
            'superuser',
            'accountant',
            'treasurer',
            'mill_operator',
            'salesperson',
            'customer',
        ];

        foreach ($rolesList as $role) {
            DB::table('roles')->updateOrInsert(
                ['name' => $role],
                ['name' => $role, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        // 2. Every finance permission phase, in business-flow order.
        $this->call([
            FinancePermissionsSeeder::class,
            Phase2aFinancePermissionsSeeder::class,
            Phase2bInvoicePermissionsSeeder::class,
            Phase2cReceiptPermissionsSeeder::class,
            Phase2dPermissionsSeeder::class,
            Phase3aSupplierPermissionsSeeder::class,
            Phase3bVendorInvoicePermissionsSeeder::class,
            Phase3cPaymentPermissionsSeeder::class,
            Phase3dPermissionsSeeder::class,
            Phase3ePermissionsSeeder::class,
            Phase4InventoryPermissionsSeeder::class,
            Phase5PigCostingPermissionsSeeder::class,
            Phase6ReportingClosePermissionsSeeder::class,
            Phase8LoansTreasuryPermissionsSeeder::class,
            Phase9InvestorPermissionsSeeder::class,
            FeedSalesPermissionsSeeder::class,
            // Farm-operations catalogue (livestock, facilities, feed). Added in
            // remediation Phase 2 — before this, no permission existed to gate
            // health, medication, weights, farrowing, facilities, feed
            // production or raw materials (CF-P0-002).
            FarmOperationsPermissionsSeeder::class,
        ]);

        // 3. Storefront permissions.
        $this->call([
            StorefrontPermissionsSeeder::class,
            StorefrontQuotePermissionsSeeder::class,
            StorefrontOrderPermissionsSeeder::class,
            StorefrontRefundPermissionsSeeder::class,
        ]);

        // 3b. Organisation module/feature administration (Phase 1).
        $this->call([
            OrganizationFeaturePermissionsSeeder::class,
            QuickExpensePermissionsSeeder::class,
            SimplePurchasePermissionsSeeder::class,
        ]);

        // 4. Organization-scoped accounting and reference data.
        $this->call([
            ChartOfAccountsSeeder::class,
            FiscalYearSeeder::class,
            PaymentMethodSeeder::class,
            TaxCodeSeeder::class,
            // Ghana tax profiles group the individual tax codes above into the
            // selectable treatments users actually pick. Must run after
            // TaxCodeSeeder so components can link to their codes.
            GhanaTaxProfileSeeder::class,
            TaxSettingsPermissionsSeeder::class,
            RawMaterialSeeder::class,
            CustomerCategorySeeder::class,
            SupplierCategoriesSeeder::class,
            StorefrontCatalogSeeder::class,
        ]);

        $seedDefaultUsers = app()->environment(['local', 'testing'])
            || filter_var(env('CHIPPER_SEED_DEFAULT_USERS', false), FILTER_VALIDATE_BOOL);

        if (! $seedDefaultUsers) {
            return;
        }

        $roles = DB::table('roles')->pluck('id', 'name');

        $users = [
            [
                'name' => 'Admin',
                'email' => 'admin@chipperfarms.com',
                'password' => env('CHIPPER_ADMIN_PASSWORD'),
                'role' => 'admin',
                'role_id' => $roles['admin'],
            ],
            [
                'name' => 'Data Entry',
                'email' => 'dataentry@chipperfarms.com',
                'password' => env('CHIPPER_DATAENTRY_PASSWORD'),
                'role' => 'dataentry',
                'role_id' => $roles['dataentry'],
            ],
            [
                'name' => 'Farm Manager',
                'email' => 'manager@chipperfarms.com',
                'password' => env('CHIPPER_MANAGER_PASSWORD'),
                'role' => 'farmmanager',
                'role_id' => $roles['farmmanager'],
            ],
            [
                'name' => 'Super User',
                'email' => 'superuser@chipperfarms.com',
                'password' => env('CHIPPER_SUPERUSER_PASSWORD'),
                'role' => 'superuser',
                'role_id' => $roles['superuser'],
            ],
            [
                'name' => 'Accountant',
                'email' => 'accountant@chipperfarms.com',
                'password' => env('CHIPPER_ACCOUNTANT_PASSWORD'),
                'role' => 'accountant',
                'role_id' => $roles['accountant'],
            ],
        ];

        foreach ($users as $user) {
            $existingPassword = DB::table('users')
                ->where('email', $user['email'])
                ->value('password');

            $password = $user['password']
                ? Hash::make($user['password'])
                : ($existingPassword ?: Hash::make(Str::random(24)));

            DB::table('users')->updateOrInsert(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => $password,
                    'role' => $user['role'],
                    'role_id' => $user['role_id'],
                    'organization_id' => $defaultOrgId,
                    'email_verified_at' => now(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        // Reference standards need a user for their created_by foreign key.
        $this->call(ExpectedWeightStandardSeeder::class);

        // Optional operational demo data. These seeders create fictional barns,
        // pigs, and weight records, so they are never enabled implicitly.
        if (filter_var(env('CHIPPER_SEED_DEMO_DATA', false), FILTER_VALIDATE_BOOL)) {
            $this->call([
                StructureSeeder::class,
                PigManagementSeeder::class,
                PigWeightSeeder::class,
            ]);
        }
    }
}
