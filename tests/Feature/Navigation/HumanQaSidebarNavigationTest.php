<?php

namespace Tests\Feature\Navigation;

use App\Helpers\NavigationHelper;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Organizations\OrganizationFeatureService;
use App\Support\CurrentOrganization;
use App\Support\Features\FeatureCatalog;
use Database\Seeders\StorefrontOrderPermissionsSeeder;
use Database\Seeders\StorefrontPermissionsSeeder;
use Database\Seeders\StorefrontQuotePermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class HumanQaSidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private int $userSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'slug' => 'human-qa-nav',
            'name' => 'Human QA Farm',
            'is_active' => true,
        ]);

        $this->app->make(CurrentOrganization::class)->bind($this->org);

        $this->seed(StorefrontPermissionsSeeder::class);
        $this->seed(StorefrontQuotePermissionsSeeder::class);
        $this->seed(StorefrontOrderPermissionsSeeder::class);

        app(OrganizationFeatureService::class)->enable(FeatureCatalog::STOREFRONT_QUOTES, $this->org);

        Permission::firstOrCreate(
            ['name' => 'manage_feature_flags'],
            ['description' => 'Manage modules', 'module' => 'settings'],
        );
    }

    public function test_modules_and_features_sidebar_link_is_superuser_only(): void
    {
        $superuser = $this->makeUser('superuser');
        $admin = $this->makeUser('admin');

        $this->actingAs($superuser);
        $superuserItems = $this->flattenNavigationItems(NavigationHelper::sidebarSections($superuser));

        $this->assertContains('Modules & Features', array_column($superuserItems, 'label'));
        $this->assertContains('settings.modules', array_column($superuserItems, 'route'));

        $this->actingAs($admin);
        $adminItems = $this->flattenNavigationItems(NavigationHelper::sidebarSections($admin));

        $this->assertNotContains('Modules & Features', array_column($adminItems, 'label'));
        $this->assertNotContains('settings.modules', array_column($adminItems, 'route'));
    }

    public function test_storefront_sidebar_section_appears_for_authorised_farm_staff(): void
    {
        $manager = $this->makeUser('farmmanager');

        $this->actingAs($manager);

        $sections = NavigationHelper::sidebarSections($manager);
        $storefront = collect($sections)->firstWhere('title', 'Storefront');

        $this->assertNotNull($storefront);

        $labels = array_column($storefront['items'], 'label');
        $routes = array_column($storefront['items'], 'route');

        $this->assertContains('Overview', $labels);
        $this->assertContains('Listings', $labels);
        $this->assertContains('Push to Storefront', $labels);
        $this->assertContains('Orders', $labels);
        $this->assertContains('Quotes', $labels);
        $this->assertContains('Reports', $labels);
        $this->assertContains('View Store', $labels);
        $this->assertNotContains('Settings', $labels);

        foreach ($routes as $route) {
            $this->assertTrue(Route::has($route), "Sidebar route [{$route}] must exist.");
        }
    }

    public function test_storefront_sidebar_section_is_hidden_for_unauthorised_roles(): void
    {
        $dataEntry = $this->makeUser('dataentry');

        $this->actingAs($dataEntry);

        $this->assertNull(
            collect(NavigationHelper::sidebarSections($dataEntry))->firstWhere('title', 'Storefront')
        );
    }

    private function makeUser(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);

        return User::forceCreate([
            'name' => ucfirst($roleName),
            'email' => $roleName . '-' . (++$this->userSequence) . '@example.com',
            'password' => bcrypt('secret'),
            'role' => $roleName,
            'role_id' => $role->id,
            'organization_id' => $this->org->id,
            'active_status' => true,
            'email_verified_at' => now(),
        ]);
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
}
