<?php

namespace Tests\Feature\Finance\Overview;

use App\Models\DomainEvent;
use App\Models\Organization;
use App\Models\OrganizationFeatureFlag;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Organizations\OrganizationFeatureService;
use App\Support\CurrentOrganization;
use App\Support\Features\FeatureCatalog;
use App\Support\Features\FinanceNavigation;
use Database\Seeders\GhanaTaxProfileSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Finance Overview screen and feature-aware navigation — Phase 3.
 *
 * The navigation tests are the important ones. A menu built from features and
 * permissions can fail in two opposite directions: it can hide something a user
 * is entitled to, which is merely annoying, or it can show something they are
 * not, which is a security smell even though the route still refuses them.
 * Both are covered.
 */
class FinanceOverviewScreenTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'slug' => 'cf-' . uniqid(), 'name' => 'Chipper Farms', 'is_active' => true,
        ]);
        $this->app->make(CurrentOrganization::class)->bind($this->org);

        $this->seed(GhanaTaxProfileSeeder::class);
        OrganizationFeatureFlag::query()->delete();
    }

    protected function features(): OrganizationFeatureService
    {
        return app(OrganizationFeatureService::class);
    }

    protected function user(array $permissionNames): User
    {
        $role = Role::create(['name' => 'r_' . uniqid()]);

        foreach ($permissionNames as $name) {
            $permission = Permission::firstOrCreate(
                ['name' => $name],
                ['description' => 'Test', 'module' => 'finance'],
            );
            $role->permissions()->attach($permission->id);
        }

        return User::forceCreate([
            'name' => 'U', 'email' => 'u_' . uniqid() . '@example.com',
            'password' => bcrypt('x'), 'role' => $role->name, 'role_id' => $role->id,
            'organization_id' => $this->org->id, 'active_status' => true,
        ]);
    }

    protected function labels(User $user): array
    {
        return array_column(FinanceNavigation::build($user), 'label');
    }

    /* ------------------------------------------------------------------ */
    /* The screen                                                         */
    /* ------------------------------------------------------------------ */

    public function test_overview_renders_for_an_authorised_user(): void
    {
        $this->actingAs($this->user(['view_finance', 'view_finance_dashboard']))
            ->get(route('finance.overview'))
            ->assertOk()
            ->assertSee('Money');
    }

    public function test_overview_shows_attention_items_in_plain_language(): void
    {
        DomainEvent::forceCreate([
            'organization_id' => $this->org->id,
            'event_type' => 'test.event', 'idempotency_key' => 'i-' . uniqid(),
            'source_module' => 'test', 'source_type' => 'T', 'source_id' => 1,
            'payload' => [], 'status' => DomainEvent::STATUS_FAILED, 'occurred_at' => now(),
        ]);

        $response = $this->actingAs($this->user(['view_finance', 'view_finance_dashboard']))
            ->get(route('finance.overview'))
            ->assertOk();

        $response->assertSee('Needs attention');
        $response->assertSee('bookkeeping', false);
    }

    public function test_a_clean_farm_is_told_so(): void
    {
        $this->actingAs($this->user(['view_finance', 'view_finance_dashboard']))
            ->get(route('finance.overview'))
            ->assertOk()
            ->assertSee('Nothing needs your attention');
    }

    /**
     * An item a user cannot act on is not shown to them. Otherwise the owner's
     * screen fills with problems only an accountant can resolve.
     */
    public function test_attention_items_are_filtered_by_what_the_viewer_can_act_on(): void
    {
        DomainEvent::forceCreate([
            'organization_id' => $this->org->id,
            'event_type' => 'test.event', 'idempotency_key' => 'i-' . uniqid(),
            'source_module' => 'test', 'source_type' => 'T', 'source_id' => 2,
            'payload' => [], 'status' => DomainEvent::STATUS_FAILED, 'occurred_at' => now(),
        ]);

        // Has finance access but not the dashboard permission the item requires.
        $this->actingAs($this->user(['view_finance']))
            ->get(route('finance.overview'))
            ->assertOk()
            ->assertDontSee('bookkeeping', false);
    }

    public function test_overview_is_refused_without_finance_access(): void
    {
        $this->actingAs($this->user([]))
            ->get(route('finance.overview'))
            ->assertForbidden();
    }

    public function test_overview_disappears_when_the_feature_is_switched_off(): void
    {
        $this->features()->disable(FeatureCatalog::FINANCE_OVERVIEW);

        $this->actingAs($this->user(['view_finance', 'view_finance_dashboard']))
            ->get(route('finance.overview'))
            ->assertNotFound();
    }

    /* ------------------------------------------------------------------ */
    /* Navigation                                                         */
    /* ------------------------------------------------------------------ */

    public function test_navigation_hides_entries_whose_feature_is_off(): void
    {
        $svc = $this->features();
        $svc->enable(FeatureCatalog::FINANCE_LOANS);
        $svc->disable(FeatureCatalog::FINANCE_REPORTS);

        $labels = $this->labels($this->user([
            'manage_loans', 'view_financial_reports',
        ]));

        $this->assertContains('Loans', $labels);
        $this->assertNotContains('Reports', $labels);
    }

    public function test_navigation_hides_entries_the_user_lacks_permission_for(): void
    {
        $svc = $this->features();
        $svc->enable(FeatureCatalog::FINANCE_LOANS);
        $svc->enable(FeatureCatalog::FINANCE_REPORTS);

        // Feature is on for both, but only one permission is held.
        $labels = $this->labels($this->user(['manage_loans']));

        $this->assertContains('Loans', $labels);
        $this->assertNotContains(
            'Reports',
            $labels,
            'A feature being enabled must never put an entry in front of a user who cannot use it.',
        );
    }

    public function test_advanced_accounting_is_grouped_rather_than_listed_flat(): void
    {
        $svc = $this->features();
        $svc->enable(FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING);

        $nav = FinanceNavigation::build($this->user([
            'view_chart_of_accounts', 'view_journals', 'view_financial_reports',
        ]));

        $advanced = collect($nav)->firstWhere('label', 'Advanced Accounting');

        $this->assertNotNull($advanced, 'Ledger screens must be gathered under Advanced Accounting.');
        $this->assertNotEmpty($advanced['children']);

        $childLabels = array_column($advanced['children'], 'label');
        $this->assertContains('Chart of Accounts', $childLabels);

        // And not duplicated at the top level.
        $this->assertNotContains('Chart of Accounts', array_column($nav, 'label'));
    }

    public function test_advanced_accounting_vanishes_entirely_when_nothing_in_it_is_available(): void
    {
        $svc = $this->features();
        foreach ([
            FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING,
            FeatureCatalog::FINANCE_BANK_RECONCILIATION,
            FeatureCatalog::FINANCE_ADVANCED_AP,
            FeatureCatalog::FINANCE_WHT,
            FeatureCatalog::FINANCE_PERIOD_CLOSE,
            FeatureCatalog::FINANCE_INVESTOR_MANAGEMENT,
            FeatureCatalog::FINANCE_ADVANCED_BIOLOGICAL,
            FeatureCatalog::FINANCE_ADVANCED_TREASURY,
        ] as $key) {
            $svc->disable($key);
        }

        $labels = $this->labels($this->user(['view_chart_of_accounts', 'view_financial_reports']));

        $this->assertNotContains(
            'Advanced Accounting',
            $labels,
            'An empty group is a dead end — it must not render at all.',
        );
    }

    public function test_navigation_never_references_a_route_that_does_not_exist(): void
    {
        $svc = $this->features();
        foreach (FeatureCatalog::keys() as $key) {
            $svc->enable($key);
        }

        $user = $this->user(array_map(
            fn ($p) => $p,
            ['view_finance', 'view_finance_dashboard', 'record_expenses', 'record_purchases',
             'manage_invoices', 'manage_bank_accounts', 'manage_loans', 'manage_customers',
             'view_financial_reports', 'view_chart_of_accounts', 'view_journals',
             'manage_vendor_invoices', 'manage_wht_certificates', 'manage_investors'],
        ));

        foreach (FinanceNavigation::build($user) as $entry) {
            $this->assertTrue(
                \Illuminate\Support\Facades\Route::has($entry['route']),
                "Navigation points at unregistered route [{$entry['route']}]. A menu that references "
                . 'a missing route throws on every page render for every user.',
            );

            foreach ($entry['children'] ?? [] as $child) {
                $this->assertTrue(
                    \Illuminate\Support\Facades\Route::has($child['route']),
                    "Navigation points at unregistered route [{$child['route']}].",
                );
            }
        }
    }

    public function test_navigation_is_empty_for_a_guest(): void
    {
        $this->assertSame([], FinanceNavigation::build(null));
    }
}
