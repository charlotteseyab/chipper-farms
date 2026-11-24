<?php

namespace Tests\Feature\Finance;

use App\Services\Organizations\OrganizationFeatureService;
use App\Support\Features\FeatureCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The accounting screens moved into the Advanced Accounting add-on are
 * refused to a farm without it, not just hidden from its menu; the everyday
 * screens beside them stay open.
 */
class AdvancedAccountingAddonTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $role = \App\Models\Role::firstOrCreate(['name' => 'admin']);
        $role->permissions()->syncWithoutDetaching(\App\Models\Permission::pluck('id')->all());
        $this->user->forceFill(['role' => 'admin', 'role_id' => $role->id])->save();
        $this->user->refresh();
    }

    private const MOVED = [
        'finance.reports.balance-sheet',
        'finance.reports.cash-flow',
        'finance.loans.reclassification',
        'finance.credit-notes.index',
        'finance.refunds.index',
    ];

    public function test_the_moved_screens_open_with_the_add_on(): void
    {
        foreach (self::MOVED as $route) {
            $this->actingAs($this->user)->get(route($route))->assertOk();
        }
    }

    public function test_a_farm_without_the_add_on_is_refused_them_but_keeps_the_everyday_screens(): void
    {
        $this->app->make(OrganizationFeatureService::class)
            ->disable(FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING, $this->org);

        foreach (self::MOVED as $route) {
            $this->actingAs($this->user)->get(route($route))->assertNotFound();
        }
        foreach (['finance.reports.income-statement', 'finance.money-in.index', 'finance.returns.index', 'finance.loans.index'] as $route) {
            $this->actingAs($this->user)->get(route($route))->assertOk();
        }
    }
}
