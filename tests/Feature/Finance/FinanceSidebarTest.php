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

    public function test_the_finance_section_is_built_from_the_feature_aware_navigation(): void
    {
        $labels = $this->financeLabels();

        // §20's farm-language entries, not the ledger vocabulary the hardcoded
        // block used.
        $this->assertContains('Overview', $labels);
        $this->assertContains('Cash, Bank & MoMo', $labels);
    }

    public function test_accountant_keeps_the_advanced_accounting_dropdown_when_optional_features_are_off(): void
    {
        $this->user->syncRoleFields('accountant');
        $this->user->save();
        $this->user->clearPermissionCache();

        foreach ([
            FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING,
            FeatureCatalog::FINANCE_ADVANCED_AP,
            FeatureCatalog::FINANCE_WHT,
            FeatureCatalog::FINANCE_BANK_RECONCILIATION,
            FeatureCatalog::FINANCE_PERIOD_CLOSE,
            FeatureCatalog::FINANCE_INVESTOR_MANAGEMENT,
            FeatureCatalog::FINANCE_ADVANCED_BIOLOGICAL,
            FeatureCatalog::FINANCE_ADVANCED_TREASURY,
        ] as $feature) {
            $this->app->make(OrganizationFeatureService::class)->disable($feature, $this->org);
        }

        $section = collect(NavigationHelper::sidebarSections($this->user))
            ->firstWhere('title', 'Finance');
        $advanced = collect($section['items'] ?? [])->firstWhere('label', 'Advanced Accounting');

        $this->assertNotNull($advanced);
        $this->assertContains('Accounting Dashboard', collect($advanced['children'] ?? [])->pluck('label')->all());
    }

    /**
     * The defect, stated as a test: a surface the farm has switched off must not
     * be advertised in its own menu.
     */
    public function test_switching_a_feature_off_removes_its_entry_from_the_sidebar(): void
    {
        $this->assertContains('Advanced Accounting', $this->financeLabels());

        $features = $this->app->make(OrganizationFeatureService::class);
        $features->disable(FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING, $this->org);
        $features->disable(FeatureCatalog::FINANCE_ADVANCED_AP, $this->org);
        $features->disable(FeatureCatalog::FINANCE_WHT, $this->org);
        $features->disable(FeatureCatalog::FINANCE_BANK_RECONCILIATION, $this->org);
        $features->disable(FeatureCatalog::FINANCE_PERIOD_CLOSE, $this->org);
        $features->disable(FeatureCatalog::FINANCE_INVESTOR_MANAGEMENT, $this->org);
        $features->disable(FeatureCatalog::FINANCE_ADVANCED_BIOLOGICAL, $this->org);
        $features->disable(FeatureCatalog::FINANCE_ADVANCED_TREASURY, $this->org);

        $this->assertNotContains(
            'Advanced Accounting',
            $this->financeLabels(),
            'The sidebar still offers advanced accounting after the farm switched every '
            . 'advanced feature off. Clicking it answers 404 — a dead end made by the menu.'
        );
    }

    /**
     * The everyday entries survive, which is the point of hiding the ledger
     * rather than the business.
     */
    public function test_the_everyday_entries_survive_switching_the_advanced_ones_off(): void
    {
        $this->app->make(OrganizationFeatureService::class)
            ->disable(FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING, $this->org);

        $labels = $this->financeLabels();

        $this->assertContains('Overview', $labels);
        $this->assertContains('Expenses', $labels);
    }

    /**
     * The rendered page, not just the array — the wiring is the whole point of
     * this file, and an adapter that returns a shape the partial cannot render
     * would pass every assertion above.
     */
    public function test_the_finance_entries_render_on_a_real_page(): void
    {
        $this->actingAs($this->user)
            ->get(route('finance.overview'))
            ->assertOk()
            ->assertSee('Cash, Bank &amp; MoMo', false);
    }

    /**
     * A guest has no menu and must not blow up building one.
     */
    public function test_a_missing_user_yields_no_sections(): void
    {
        $this->assertSame([], NavigationHelper::sidebarSections(null));
    }
}
