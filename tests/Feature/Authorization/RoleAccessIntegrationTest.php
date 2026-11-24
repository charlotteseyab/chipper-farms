<?php

namespace Tests\Feature\Authorization;

use App\Helpers\NavigationHelper;
use App\Models\Organization;
use App\Models\PigManagement;
use App\Models\PigWeight;
use App\Models\Role;
use App\Models\Structure;
use App\Models\User;
use Database\Seeders\FarmOperationsPermissionsSeeder;
use Database\Seeders\FinancePermissionsSeeder;
use Database\Seeders\LivestockAccessPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The four defects from the roles and permissions review of 28 October 2026
 * (docs/operations/ROLES_PERMISSIONS_REVIEW_2026-10-28.md). Each test changes
 * a role through Settings → Roles & access, as a farm admin would, and checks
 * what the affected user can then reach.
 */
class RoleAccessIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $farm;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['superuser', 'admin', 'farmmanager', 'dataentry', 'accountant', 'treasurer', 'customer'] as $name) {
            Role::firstOrCreate(['name' => $name]);
        }
        $this->seed(FarmOperationsPermissionsSeeder::class);
        $this->seed(LivestockAccessPermissionsSeeder::class);
        $this->seed(FinancePermissionsSeeder::class);

        $this->farm = Organization::create(['slug' => 'farm-a', 'name' => 'Farm A', 'is_active' => true]);
        app(\App\Support\CurrentOrganization::class)->bind($this->farm);
    }

    // 1 ---------------------------------------------------------------------

    public function test_revoked_data_entry_dashboard_shows_no_farm_records(): void
    {
        $pig = PigManagement::create([
            'organization_id' => $this->farm->id, 'ear_tag_number' => 'SECRET-77', 'code' => 'SECRET-77',
            'breed' => 'LW', 'gender' => 'F', 'date_of_birth' => now()->subMonths(3)->toDateString(), 'stock_type' => 'standard',
        ]);
        PigWeight::create(['pig_id' => $pig->id, 'weight' => 41.5, 'weigh_date' => now()->toDateString()]);

        $this->setRole('dataentry', []);
        $user = $this->user('dataentry');

        $this->actingAs($user)->get(route('all-stocks.index'))->assertForbidden();
        $this->actingAs($user)->get(route('dataentry.dashboard'))
            ->assertOk()
            ->assertDontSee('SECRET-77')
            ->assertDontSee('41.5 kg')
            ->assertDontSee('Fix missing details')
            ->assertSee('Nothing is assigned to your role yet');
    }

    public function test_data_entry_dashboard_shows_only_the_modules_still_granted(): void
    {
        $pig = PigManagement::create([
            'organization_id' => $this->farm->id, 'ear_tag_number' => 'W-12', 'code' => 'W-12',
            'breed' => 'LW', 'gender' => 'F', 'date_of_birth' => now()->subMonths(3)->toDateString(), 'stock_type' => 'standard',
        ]);
        PigWeight::create(['pig_id' => $pig->id, 'weight' => 41.5, 'weigh_date' => now()->toDateString()]);

        $this->setRole('dataentry', ['view_weights']);

        $this->actingAs($this->user('dataentry'))->get(route('dataentry.dashboard'))
            ->assertOk()
            ->assertSee('41.5 kg')            // weights: granted
            ->assertDontSee('New pig')         // pig records: revoked
            ->assertDontSee('Fix missing details');
    }

    public function test_generic_and_manager_dashboards_hide_herd_figures_without_pig_access(): void
    {
        $this->setRole('farmmanager', []);
        $manager = $this->user('farmmanager');

        $this->actingAs($manager)->get(route('manager.dashboard'))
            ->assertOk()
            ->assertDontSee('Herd position')
            ->assertDontSee('Stock Composition');

        $this->actingAs($manager)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Nothing is assigned to your role yet');
    }

    // 2 ---------------------------------------------------------------------

    public function test_daily_weights_api_needs_the_weights_permission(): void
    {
        $this->setRole('dataentry', []);
        $user = $this->user('dataentry');

        $this->actingAs($user)->get(route('weights.index'))->assertForbidden();
        $this->actingAs($user)->getJson(route('api.daily-weights-data'))->assertForbidden();
    }

    public function test_daily_weights_api_counts_by_day_within_a_bounded_range(): void
    {
        $this->setRole('dataentry', ['view_weights']);
        $user = $this->user('dataentry');
        $pig = PigManagement::create([
            'organization_id' => $this->farm->id, 'ear_tag_number' => 'D-1', 'code' => 'D-1',
            'breed' => 'LW', 'gender' => 'F', 'date_of_birth' => now()->subMonths(3)->toDateString(), 'stock_type' => 'standard',
        ]);
        PigWeight::create(['pig_id' => $pig->id, 'weight' => 30, 'weigh_date' => now()->toDateString()]);
        PigWeight::create(['pig_id' => $pig->id, 'weight' => 31, 'weigh_date' => now()->toDateString()]);

        $this->actingAs($user)->getJson(route('api.daily-weights-data'))
            ->assertOk()
            ->assertJsonCount(7, 'labels')
            ->assertJsonPath('data.6', 2);

        $this->actingAs($user)->getJson(route('api.daily-weights-data', [
            'start_date' => now()->subYears(5)->toDateString(), 'end_date' => now()->toDateString(),
        ]))->assertStatus(422);
    }

    // 3 ---------------------------------------------------------------------

    public function test_view_only_facilities_access_opens_the_list_without_edit_controls(): void
    {
        $structure = Structure::create(['organization_id' => $this->farm->id, 'name' => 'Barn One', 'code' => 'B1']);
        $this->setRole('dataentry', ['view_facilities']);
        $user = $this->user('dataentry');

        $this->assertFalse($user->hasPermission('manage_facilities'));
        $this->actingAs($user)->get(route('structures.index'))
            ->assertOk()
            ->assertSee('Barn One')
            ->assertDontSee('New Structure');
        $this->actingAs($user)->get(route('structures.show', $structure))
            ->assertOk()
            ->assertDontSee('Edit Structure')
            ->assertDontSee('Add Room');
        $this->actingAs($user)->get(route('structures.create'))->assertForbidden();
        $this->actingAs($user)->delete(route('structures.destroy', $structure))->assertForbidden();

        $this->assertContains('Structures & Rooms', $this->sidebarLabels($user));
    }

    // Follow-up: action links follow the action's own permission -----------

    public function test_view_only_pig_access_offers_no_add_or_import_links(): void
    {
        $this->setRole('dataentry', ['view_pigs']);
        $user = $this->user('dataentry');

        $this->actingAs($user)->get(route('all-stocks.create'))->assertForbidden();
        $this->actingAs($user)->get(route('dataentry.dashboard'))
            ->assertOk()
            ->assertDontSee('Add a pig')
            ->assertDontSee('Import stock');
    }

    public function test_record_only_pig_access_offers_the_add_link_it_can_open(): void
    {
        $this->setRole('dataentry', ['manage_pigs']);
        $user = $this->user('dataentry');

        $this->actingAs($user)->get(route('all-stocks.create'))->assertOk();
        $this->actingAs($user)->get(route('dataentry.dashboard'))
            ->assertOk()
            ->assertSee('Add a pig')
            ->assertSee('Import stock');
    }

    /**
     * canOpen() must agree with the page itself, for every controller-gated
     * page a dashboard links to, under view-only, record-only and empty grants.
     */
    public function test_can_open_matches_what_each_controller_gated_page_answers(): void
    {
        $routes = ['all-stocks.index', 'all-stocks.create', 'all-stocks.import.create', 'mating.index', 'mating.manage',
            'deaths.index', 'weaning.dashboard', 'purchase-requests.index', 'purchase-requests.create',
            'purchase-stock-intakes.index', 'purchase-stock-intakes.create'];
        $grants = [[], ['view_pigs'], ['manage_pigs'], ['view_breeding'], ['view_pig_deaths'],
            ['view_purchase_requests'], ['create_purchase_requests'], ['operate_purchase_intakes']];

        foreach ($grants as $grant) {
            $this->setRole('dataentry', $grant);
            $user = $this->user('dataentry');
            $access = \App\Services\Access\DashboardAccess::for($user);

            foreach ($routes as $route) {
                $status = $this->actingAs($user)->get(route($route))->getStatusCode();
                $this->assertSame(
                    $status !== 403,
                    $access->canOpen($route),
                    "{$route} answered {$status} with [".implode(', ', $grant).'] but canOpen() disagrees.'
                );
            }
        }
    }

    // 4 ---------------------------------------------------------------------

    public function test_an_accountant_with_livestock_access_only_lands_on_a_page_they_can_open(): void
    {
        $this->setRole('accountant', ['view_pigs']);
        $user = $this->user('accountant', ['onboarding_required' => false]);
        $this->assertFalse($user->hasPermission('view_finance'));

        $landing = $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect()
            ->headers->get('Location');

        $this->assertStringNotContainsString('/finance', $landing);
        $this->get($landing)->assertOk();
        $this->assertSame('dashboard', \App\Services\Access\LandingPage::routeName($user->fresh()));
    }

    public function test_the_sidebar_dashboard_link_matches_the_landing_page(): void
    {
        $this->setRole('accountant', ['view_pigs']);
        $user = $this->user('accountant');

        $dashboard = collect(NavigationHelper::sidebarSections($user))->flatMap(fn ($section) => $section['items'] ?? [])
            ->firstWhere('label', 'Dashboard');
        $this->assertSame('dashboard', $dashboard['route']);
        $this->assertSame(route('dashboard'), NavigationHelper::dashboardRoute($user));
    }

    // ---------------------------------------------------------------------

    /** @param list<string> $permissions */
    private function setRole(string $role, array $permissions): void
    {
        // Each switch of user starts a fresh session: session authentication
        // logs out a request whose session holds another user's password hash.
        $this->flushSession();

        $response = $this->actingAs($this->user('admin'))
            ->put(route('settings.roles-access.update', Role::where('name', $role)->first()), ['permissions' => $permissions])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        // A logged-out save also redirects, so check it was not sent to login.
        $this->assertNotSame(route('login'), $response->headers->get('Location'), 'The role was not saved.');

        $this->flushSession();
    }

    private function user(string $role, array $attributes = []): User
    {
        return User::forceCreate(array_merge([
            'name' => ucfirst($role),
            'email' => $role.'_'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'role' => $role,
            'role_id' => Role::where('name', $role)->value('id'),
            'organization_id' => $this->farm->id,
            'active_status' => true,
        ], $attributes));
    }

    /** @return list<string> */
    private function sidebarLabels(User $user): array
    {
        $labels = [];
        $walk = function (array $items) use (&$walk, &$labels) {
            foreach ($items as $item) {
                $labels[] = $item['label'] ?? null;
                $walk($item['children'] ?? []);
            }
        };
        foreach (NavigationHelper::sidebarSections($user) as $section) {
            $walk($section['items'] ?? []);
        }

        return array_values(array_filter($labels));
    }
}
