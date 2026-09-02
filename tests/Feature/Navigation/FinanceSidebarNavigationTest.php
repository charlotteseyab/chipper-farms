<?php

namespace Tests\Feature\Navigation;

use App\Helpers\NavigationHelper;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\Phase6ReportingClosePermissionsSeeder;
use Database\Seeders\Phase8LoansTreasuryPermissionsSeeder;
use Database\Seeders\Phase9InvestorPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceSidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_farm_manager_sees_finance_reporting_close_loans_and_treasury_links(): void
    {
        foreach (['admin', 'accountant', 'manager', 'farmmanager', 'superuser'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }

        $this->seed(Phase6ReportingClosePermissionsSeeder::class);
        $this->seed(Phase8LoansTreasuryPermissionsSeeder::class);

        $manager = $this->makeUser('farmmanager');

        // An established farm that uses period close, treasury and loans — the
        // same reasoning Phase 14 applied to FinanceTestSetup. Advanced features
        // default OFF for a brand-new organisation, and since Phase 16 the
        // sidebar honours that, so a fixture asserting these entries has to say
        // its farm uses them.
        $this->enableAdvancedFinanceFeatures($manager);

        $this->actingAs($manager);

        $items = $this->flattenNavigationItems(NavigationHelper::sidebarSections($manager));
        $labels = array_column($items, 'label');
        $routes = array_column($items, 'route');

        $this->assertContains('Reports', $labels);
        $this->assertContains('Income Statement', $labels);
        $this->assertContains('Balance Sheet', $labels);
        $this->assertContains('Cash Flow', $labels);
        $this->assertContains('Period Close', $labels);
        $this->assertContains('Year End', $labels);
        $this->assertContains('Treasury', $labels);
        $this->assertContains('Loans', $labels);
        $this->assertContains('Reclassification', $labels);

        $this->assertContains('finance.reports.income-statement', $routes);
        $this->assertContains('finance.reports.balance-sheet', $routes);
        $this->assertContains('finance.reports.cash-flow', $routes);
        $this->assertContains('finance.close.index', $routes);
        $this->assertContains('finance.year-end.index', $routes);
        $this->assertContains('finance.treasury', $routes);
        $this->assertContains('finance.loans.index', $routes);
        $this->assertContains('finance.loans.reclassification', $routes);
    }

    public function test_farm_manager_seeded_role_has_required_read_permissions(): void
    {
        foreach (['admin', 'accountant', 'manager', 'farmmanager', 'superuser'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }

        $this->seed(Phase6ReportingClosePermissionsSeeder::class);
        $this->seed(Phase8LoansTreasuryPermissionsSeeder::class);

        $permissionNames = Role::where('name', 'farmmanager')
            ->firstOrFail()
            ->permissions()
            ->pluck('name')
            ->all();

        $this->assertContains('view_financial_reports', $permissionNames);
        $this->assertContains('view_period_close', $permissionNames);
        $this->assertContains('view_loans', $permissionNames);
        $this->assertContains('view_treasury', $permissionNames);
    }

    public function test_accountant_sees_investor_cap_table_and_allocate_links(): void
    {
        foreach (['admin', 'accountant', 'superuser'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }

        $this->seed(Phase9InvestorPermissionsSeeder::class);

        $accountant = $this->makeUser('accountant');

        $this->enableAdvancedFinanceFeatures($accountant);

        $this->actingAs($accountant);

        $items = $this->flattenNavigationItems(NavigationHelper::sidebarSections($accountant));
        $labels = array_column($items, 'label');
        $routes = array_column($items, 'route');

        $this->assertContains('Investors', $labels);
        $this->assertContains('Investors', $labels);
        $this->assertContains('Investor Cap Table', $labels);
        $this->assertContains('Allocate Investor Profit', $labels);

        $this->assertContains('finance.investors.index', $routes);
        $this->assertContains('finance.investors.cap-table', $routes);
        $this->assertContains('finance.investors.allocate.form', $routes);
    }

    public function test_accountant_sidebar_contains_only_the_finance_section(): void
    {
        Role::firstOrCreate(['name' => 'accountant']);
        $accountant = $this->makeUser('accountant');
        $this->actingAs($accountant);

        $sections = NavigationHelper::sidebarSections($accountant);

        $this->assertSame(['Finance'], array_column($sections, 'title'));
    }

    /**
     * Switch on the advanced finance features this fixture's farm uses.
     *
     * Before Phase 16 the sidebar was built from hardcoded role arrays and knew
     * nothing about organisation features, so these entries appeared for any
     * organisation. Now that it is built by FinanceNavigation the flags decide,
     * and a NEW organisation has the advanced ones off by default.
     */
    private function enableAdvancedFinanceFeatures(User $user): void
    {
        // Bind the organisation the way a real request does.
        //
        // `FinanceNavigation` asks `OrganizationFeatureService::usable()` WITHOUT
        // an organisation id, so the service resolves it from
        // `CurrentOrganization`. Enabling the flags on `$user->organization`
        // while nothing is bound leaves the navigation reading a different
        // organisation — in practice none — so every flag falls back to its
        // catalogue default and the advanced entries vanish.
        //
        // This test passed anyway for a while, because when it ran after other
        // tests in the same process something else had already bound an
        // organisation. Run on its own it failed on both engines. The MariaDB
        // gate surfaced it; the order-dependence was the real defect.
        app(\App\Support\CurrentOrganization::class)->bind($user->organization);

        $features = app(\App\Services\Organizations\OrganizationFeatureService::class);

        foreach (\App\Support\Features\FeatureCatalog::all() as $key => $definition) {
            $features->enable($key, $user->organization);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function flattenNavigationItems(array $sections): array
    {
        $items = [];

        foreach ($sections as $section) {
            foreach ($section['items'] as $item) {
                $items[] = $item;

                foreach ($item['children'] ?? [] as $child) {
                    $items[] = $child;
                }
            }
        }

        return $items;
    }

    private function makeUser(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);

        foreach (['view_financial_reports', 'view_period_close', 'view_loans', 'view_treasury'] as $permissionName) {
            $permission = Permission::firstOrCreate(['name' => $permissionName], ['module' => 'finance']);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        return User::forceCreate([
            'name' => ucfirst($roleName),
            'email' => $roleName . '_' . uniqid() . '@example.com',
            'password' => bcrypt('x'),
            'role' => $roleName,
            'role_id' => $role->id,
            // users.organization_id is NOT NULL. Omitting it errored with
            // "Field doesn't have a default value" — a different failure from
            // passing an explicit null, and one that only appears on a database
            // that actually carries the constraint.
            'organization_id' => \App\Models\Organization::query()->value('id')
                ?? \App\Models\Organization::create([
                    'slug' => 'nav-' . uniqid(), 'name' => 'Nav Test Farm', 'is_active' => true,
                ])->id,
        ]);
    }
}
