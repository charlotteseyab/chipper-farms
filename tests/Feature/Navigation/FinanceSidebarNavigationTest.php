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

        $this->actingAs($manager);

        $items = $this->flattenNavigationItems(NavigationHelper::sidebarSections($manager));
        $labels = array_column($items, 'label');
        $routes = array_column($items, 'route');

        $this->assertContains('Reports', $labels);
        $this->assertContains('Income Statement', $labels);
        $this->assertContains('Balance Sheet', $labels);
        $this->assertContains('Cash Flow', $labels);
        $this->assertContains('Close', $labels);
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

        $this->actingAs($accountant);

        $items = $this->flattenNavigationItems(NavigationHelper::sidebarSections($accountant));
        $labels = array_column($items, 'label');
        $routes = array_column($items, 'route');

        $this->assertContains('Investors', $labels);
        $this->assertContains('All Investors', $labels);
        $this->assertContains('Cap Table', $labels);
        $this->assertContains('Allocate Profit', $labels);

        $this->assertContains('finance.investors.index', $routes);
        $this->assertContains('finance.investors.cap-table', $routes);
        $this->assertContains('finance.investors.allocate.form', $routes);
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
        ]);
    }
}
