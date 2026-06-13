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
        // 1. Organization first — every downstream record depends on it.
        $this->call(OrganizationSeeder::class);
        // 2. Finance permissions and role mappings.
        // Ensures permission middleware works for finance routes.
        $this->call(FinancePermissionsSeeder::class);
        // 2b. Phase-specific finance permissions (3d/3e).
        $this->call(Phase3dPermissionsSeeder::class);
        $this->call(Phase3ePermissionsSeeder::class);
        // 2c. Finance module permissions — Phases 4–9 + feed sales. These grant
        // access to inventory, pig/feed costing, reporting & close, treasury &
        // loans, investor/fund tracking, and feed sales. Without these calls a
        // fresh install cannot reach those finance routes.
        $this->call(Phase4InventoryPermissionsSeeder::class);
        $this->call(Phase5PigCostingPermissionsSeeder::class);
        $this->call(Phase6ReportingClosePermissionsSeeder::class);
        $this->call(Phase8LoansTreasuryPermissionsSeeder::class);
        $this->call(Phase9InvestorPermissionsSeeder::class);
        $this->call(FeedSalesPermissionsSeeder::class);
        // 2d. Storefront permissions + Salesperson/Customer roles (Phase 1).
        $this->call(StorefrontPermissionsSeeder::class);
        // 2e. Storefront Phase 2 — quote + media permissions.
        $this->call(StorefrontQuotePermissionsSeeder::class);
        // 2f. Storefront Phase 3 — order/checkout permissions.
        $this->call(StorefrontOrderPermissionsSeeder::class);
        // 3. Ensure each organization has current fiscal year + monthly periods.
        $this->call(FiscalYearSeeder::class);
        // 3b. Base chart of accounts (also seeded by migration 2026_05_01_120150).
        $this->call(ChartOfAccountsSeeder::class);
        // 3c. Storefront catalogue (categories + placement slots) — Phase 1.
        $this->call(StorefrontCatalogSeeder::class);

        $defaultOrgId = DB::table('organizations')
            ->where('slug', 'chipper-farms')
            ->value('id');

        // 4. Roles. Idempotent. Includes accountant.
        $rolesList = ['admin', 'dataentry', 'farmmanager', 'superuser', 'accountant', 'salesperson', 'customer'];
        foreach ($rolesList as $role) {
            DB::table('roles')->updateOrInsert(
                ['name' => $role],
                ['name' => $role, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        if (!app()->environment(['local', 'testing']) && !filter_var(env('CHIPPER_SEED_DEFAULT_USERS', false), FILTER_VALIDATE_BOOL)) {
            return;
        }

        $roles = DB::table('roles')->pluck('id', 'name');

        $users = [
            [
                'name' => 'Admin',
                'email' => 'admin@chipperfarms.com',
                'password' => env('CHIPPER_ADMIN_PASSWORD', Str::random(24)),
                'role' => 'admin',
                'role_id' => $roles['admin'],
            ],
            [
                'name' => 'Data Entry',
                'email' => 'dataentry@chipperfarms.com',
                'password' => env('CHIPPER_DATAENTRY_PASSWORD', Str::random(24)),
                'role' => 'dataentry',
                'role_id' => $roles['dataentry'],
            ],
            [
                'name' => 'Farm Manager',
                'email' => 'manager@chipperfarms.com',
                'password' => env('CHIPPER_MANAGER_PASSWORD', Str::random(24)),
                'role' => 'farmmanager',
                'role_id' => $roles['farmmanager'],
            ],
            [
                'name' => 'Super User',
                'email' => 'superuser@chipperfarms.com',
                'password' => env('CHIPPER_SUPERUSER_PASSWORD', Str::random(24)),
                'role' => 'superuser',
                'role_id' => $roles['superuser'],
            ],
            [
                'name' => 'Accountant',
                'email' => 'accountant@chipperfarms.com',
                'password' => env('CHIPPER_ACCOUNTANT_PASSWORD', Str::random(24)),
                'role' => 'accountant',
                'role_id' => $roles['accountant'],
            ],
        ];

        foreach ($users as $user) {
            DB::table('users')->updateOrInsert(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => Hash::make($user['password']),
                    'role' => $user['role'],
                    'role_id' => $user['role_id'],
                    'organization_id' => $defaultOrgId,
                    'email_verified_at' => now(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}