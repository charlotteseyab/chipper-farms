<?php

namespace Tests\Feature\Finance;

use App\Helpers\NavigationHelper;
use App\Services\Organizations\OrganizationFeatureService;
use App\Support\Features\FeatureCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 16 (IMP-070) — the sidebar the user actually sees.
 *
 * WHAT WAS WRONG
 * --------------
 * Phase 3 wrote `FinanceNavigation` to build the finance menu from organisation
 * features and user permissions. It was never wired to anything. A repository
 * sweep in Phase 16 found it referenced by its own tests and nothing else: the
 * live sidebar was still `NavigationHelper::sidebarSections()`, ~240 lines of
 * hardcoded role arrays that knew nothing about feature flags.
 *
 * So Phase 14's gating and Phase 3's menu passed their tests while the running
 * application did neither. A farm on Essentials was offered Vendor Invoices and
 * Chart of Accounts in its sidebar and got a 404 for clicking them — a §46 dead
 * end manufactured by the navigation itself.
 *
 * These tests assert the wiring, not the builder. `FinanceNavigation`'s own
 * rules are covered by `FinanceOverviewScreenTest`; what was missing was any
 * assertion that the thing on screen is the thing that was built.
 */
class FinanceSidebarTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        // The finance fixture builds an admin USER but seeds no permissions —
        // and this file is about what a menu shows, which is decided by
        // permissions. Without them every entry is correctly withheld and the
        // assertions below would be measuring an empty menu.
        $this->seedEveryFinancePermission();
    }

    protected function seedEveryFinancePermission(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $role = \App\Models\Role::firstOrCreate(['name' => 'admin']);
        $role->permissions()->syncWithoutDetaching(\App\Models\Permission::pluck('id')->all());

        $this->user->forceFill(['role' => 'admin', 'role_id' => $role->id])->save();
        $this->user->refresh();
    }

    protected function financeLabels(): array
    {
        $section = collect(NavigationHelper::sidebarSections($this->user))
            ->firstWhere('title', 'Finance');

        return collect($section['items'] ?? [])->pluck('label')->all();
    }

    /** Tab labels of one finance page, as this user sees them (children included). */
    protected function tabLabels(string $hub): array
    {
        $tabs = \App\Support\Features\FinanceHubs::visible($this->user)[$hub]['tabs'] ?? [];

        return collect($tabs)->flatMap(fn ($t) => [$t['label'], ...array_column($t['children'], 'label')])->all();
    }

    public function test_the_finance_section_is_built_from_the_feature_aware_navigation(): void
    {
        // One entry per job; every other screen is a tab on one of them.
        $this->assertSame(
            ['Overview', 'Money in', 'Money out', 'Cash & bank', 'People', 'Reports', 'Advanced Accounting'],
            $this->financeLabels(),
        );
    }

    public function test_an_accountant_without_the_add_on_lands_on_the_overview(): void
    {
        $this->user->syncRoleFields('accountant');
        $this->user->save();
        $this->user->clearPermissionCache();

        foreach (FeatureCatalog::inCategory(FeatureCatalog::CATEGORY_ADVANCED) as $feature) {
            $this->app->make(OrganizationFeatureService::class)->disable($feature, $this->org);
        }

        // The ledger dashboard is the add-on's; /finance opens the overview.
        $this->assertNotContains('Advanced Accounting', $this->financeLabels());
        $this->actingAs($this->user)->get('/finance')->assertRedirect(route('finance.overview'));
        $this->actingAs($this->user)->get(route('finance.dashboard'))->assertNotFound();
    }

    /**
     * The defect, stated as a test: a surface the farm has switched off must not
     * be advertised in its own menu.
     */
    public function test_switching_a_feature_off_removes_its_tabs(): void
    {
        $this->assertContains('Journals', $this->tabLabels('advanced'));
        $this->assertContains('Supplier bills', $this->tabLabels('advanced'));

        $features = $this->app->make(OrganizationFeatureService::class);
        $features->disable(FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING, $this->org);
        $features->disable(FeatureCatalog::FINANCE_ADVANCED_AP, $this->org);

        $this->assertNotContains('Journals', $this->tabLabels('advanced'),
            'Journals is still offered after the farm switched advanced accounting off. Clicking it answers 404.');
        $this->assertNotContains('Chart of accounts', $this->tabLabels('advanced'));
        $this->assertNotContains('Supplier bills', $this->tabLabels('advanced'));
    }

    /**
     * The everyday entries survive, which is the point of hiding the ledger
     * rather than the business.
     */
    public function test_the_everyday_entries_survive_switching_the_advanced_ones_off(): void
    {
        $this->app->make(OrganizationFeatureService::class)
            ->disable(FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING, $this->org);

        $this->assertContains('Overview', $this->financeLabels());
        $this->assertContains('Expenses', $this->tabLabels('money-out'));
        $this->assertContains('Profit & loss', $this->tabLabels('reports'));
    }

    /**
     * The rendered page, not just the array — the wiring is the whole point of
     * this file, and an adapter that returns a shape the partial cannot render
     * would pass every assertion above.
     */
    public function test_the_finance_entries_render_on_a_real_page(): void
    {
        $this->actingAs($this->user)
            ->get(route('finance.expenses.index'))
            ->assertOk()
            ->assertSee('Cash &amp; bank', false)
            // The page's tabs: its siblings, with the open one marked.
            ->assertSee('Finance · Money out')
            ->assertSee('Purchases')
            ->assertSee('aria-current="page"', false);
    }

    /**
     * Advanced Accounting is a paid add-on: everything a farm pays for is on
     * its page, and nothing on the free pages needs it.
     */
    public function test_the_add_on_holds_every_paid_screen_and_only_paid_screens(): void
    {
        $paid = collect(FeatureCatalog::all())
            ->filter(fn ($d) => $d['category'] === FeatureCatalog::CATEGORY_ADVANCED)->keys()->all();
        $featuresOf = function (array $tab): array {
            $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName($tab['route']);
            $gates = collect($route->gatherMiddleware())
                ->filter(fn ($m) => is_string($m) && str_starts_with($m, 'feature:'))
                ->flatMap(fn ($m) => explode(',', substr($m, 8)))->all();

            return isset($tab['feature']) ? [...$gates, $tab['feature']] : $gates;
        };

        $misplaced = [];
        foreach (\App\Support\Features\FinanceHubs::all() as $key => $hub) {
            foreach ($hub['tabs'] as $tab) {
                foreach ($tab['children'] ?: [$tab] as $screen) {
                    $isPaid = array_intersect($featuresOf($screen), $paid) !== [];
                    if ($hub['addon'] && ! $isPaid) {
                        $misplaced[] = "{$screen['label']} is on the add-on page but every farm gets it";
                    }
                    if (! $hub['addon'] && $isPaid) {
                        $misplaced[] = "{$screen['label']} needs the add-on but is on the free {$hub['title']} page";
                    }
                }
            }
        }

        $this->assertSame([], $misplaced);
    }

    public function test_a_farm_without_the_add_on_sees_only_the_free_pages(): void
    {
        $features = $this->app->make(OrganizationFeatureService::class);
        foreach (FeatureCatalog::all() as $key => $definition) {
            if ($definition['category'] === FeatureCatalog::CATEGORY_ADVANCED) {
                $features->disable($key, $this->org);
            }
        }

        $this->assertSame(
            ['Overview', 'Money in', 'Money out', 'Cash & bank', 'People', 'Reports'],
            $this->financeLabels(),
        );
    }

    /**
     * A guest has no menu and must not blow up building one.
     */
    public function test_a_missing_user_yields_no_sections(): void
    {
        $this->assertSame([], NavigationHelper::sidebarSections(null));
    }
}
