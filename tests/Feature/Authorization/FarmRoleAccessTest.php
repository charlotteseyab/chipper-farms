<?php

namespace Tests\Feature\Authorization;

use App\Helpers\NavigationHelper;
use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\PigManagement;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\FarmOperationsPermissionsSeeder;
use Database\Seeders\LivestockAccessPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Settings → Roles & access: a farm admin chooses what each other role may do
 * on that farm, and only that farm.
 */
class FarmRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private Organization $farm;
    private Organization $otherFarm;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['superuser', 'admin', 'farmmanager', 'dataentry', 'accountant', 'customer'] as $name) {
            Role::firstOrCreate(['name' => $name]);
        }
        $this->seed(FarmOperationsPermissionsSeeder::class);
        $this->seed(LivestockAccessPermissionsSeeder::class);

        $this->farm = Organization::create(['slug' => 'farm-a', 'name' => 'Farm A', 'is_active' => true]);
        $this->otherFarm = Organization::create(['slug' => 'farm-b', 'name' => 'Farm B', 'is_active' => true]);
    }

    public function test_roles_keep_their_usual_access_until_a_farm_changes_them(): void
    {
        $this->assertTrue($this->user('dataentry')->can('create', PigManagement::class));
        $this->assertFalse($this->user('dataentry')->can('restore', new PigManagement(['organization_id' => $this->farm->id])));
        $this->assertTrue($this->user('accountant')->can('viewAny', PigManagement::class));
        $this->assertFalse($this->user('accountant')->can('create', PigManagement::class));
        $this->assertTrue($this->user('farmmanager')->hasPermission('manage_facilities'));
        $this->assertFalse($this->user('dataentry')->hasPermission('manage_facilities'));
    }

    public function test_an_admin_changes_a_role_for_their_farm_only(): void
    {
        $admin = $this->user('admin');
        $dataentry = Role::where('name', 'dataentry')->first();

        // Data entry keeps viewing pigs, loses recording them.
        $this->actingAs($admin)
            ->put(route('settings.roles-access.update', $dataentry), ['permissions' => ['view_pigs', 'view_weights', 'record_weights']])
            ->assertRedirect();

        $here = $this->user('dataentry');
        $this->assertTrue($here->can('viewAny', PigManagement::class));
        $this->assertFalse($here->can('create', PigManagement::class));
        $this->assertTrue($here->hasPermission('record_weights'));
        $this->assertFalse($here->hasPermission('view_breeding'));

        $elsewhere = $this->user('dataentry', $this->otherFarm);
        $this->assertTrue($elsewhere->can('create', PigManagement::class));
        $this->assertTrue($elsewhere->hasPermission('view_breeding'));

        $log = ActivityLog::where('action', 'role_access_updated')->firstOrFail();
        $this->assertSame($this->farm->id, $log->organization_id);
        $this->assertContains('manage_pigs', $log->metadata['removed']);
    }

    public function test_view_only_access_hides_the_record_links_but_keeps_the_list(): void
    {
        $dataentry = Role::where('name', 'dataentry')->first();
        $this->actingAs($this->user('admin'))
            ->put(route('settings.roles-access.update', $dataentry), ['permissions' => ['view_weights', 'view_pigs']]);

        $user = $this->user('dataentry');
        $this->actingAs($user);
        $labels = $this->sidebarLabels($user);

        $this->assertContains('All Weights', $labels);
        $this->assertNotContains('Record Weight', $labels);
        $this->assertContains('All Stock', $labels);
        $this->assertNotContains('Mortality', $labels);
    }

    public function test_reset_puts_the_role_back_to_default(): void
    {
        $admin = $this->user('admin');
        $dataentry = Role::where('name', 'dataentry')->first();

        $this->actingAs($admin)->put(route('settings.roles-access.update', $dataentry), ['permissions' => []]);
        $this->assertFalse($this->user('dataentry')->hasPermission('view_pigs'));

        $this->actingAs($admin)->delete(route('settings.roles-access.reset', $dataentry))->assertRedirect();
        $this->assertTrue($this->user('dataentry')->hasPermission('view_pigs'));
        $this->assertTrue($this->user('dataentry')->can('create', PigManagement::class));
    }

    public function test_admin_superuser_and_customer_roles_cannot_be_changed(): void
    {
        $admin = $this->user('admin');

        foreach (['admin', 'superuser', 'customer'] as $name) {
            $this->actingAs($admin)
                ->put(route('settings.roles-access.update', Role::where('name', $name)->first()), ['permissions' => []])
                ->assertForbidden();
        }

        $this->assertTrue($this->user('admin')->hasPermission('view_pigs'));
    }

    public function test_platform_powers_cannot_be_given_by_a_farm(): void
    {
        $dataentry = Role::where('name', 'dataentry')->first();

        $this->actingAs($this->user('admin'))
            ->put(route('settings.roles-access.update', $dataentry), ['permissions' => ['view_pigs', 'manage_feature_flags', 'process_outbox', 'made_up']]);

        $user = $this->user('dataentry');
        $this->assertTrue($user->hasPermission('view_pigs'));
        $this->assertFalse($user->hasPermission('manage_feature_flags'));
        $this->assertFalse($user->hasPermission('process_outbox'));
    }

    public function test_only_admins_open_the_page(): void
    {
        $this->actingAs($this->user('admin'))->get(route('settings.roles-access'))
            ->assertOk()->assertSee('Roles &amp; access', false)->assertSee('Data entry')->assertDontSee('Customer');

        $this->flushSession();
        $this->actingAs($this->user('farmmanager'))->get(route('settings.roles-access'))->assertForbidden();
        $this->flushSession();
        $this->actingAs($this->user('dataentry'))
            ->put(route('settings.roles-access.update', Role::where('name', 'dataentry')->first()), ['permissions' => ['manage_pigs']])
            ->assertForbidden();
    }

    public function test_an_accountant_given_livestock_access_sees_it_in_the_menu(): void
    {
        $accountant = $this->user('accountant');
        $this->actingAs($accountant);
        $this->assertNotContains('All Stock', $this->sidebarLabels($accountant));

        $this->actingAs($this->user('admin'))
            ->put(route('settings.roles-access.update', Role::where('name', 'accountant')->first()), ['permissions' => ['view_pigs']]);

        $accountant = $this->user('accountant');
        $this->actingAs($accountant);
        $this->assertContains('All Stock', $this->sidebarLabels($accountant));
    }

    private function user(string $role, ?Organization $farm = null): User
    {
        $farm ??= $this->farm;

        return User::forceCreate([
            'name' => ucfirst($role),
            'email' => $role.'_'.uniqid().'@example.com',
            'password' => bcrypt('x'),
            'role' => $role,
            'role_id' => Role::where('name', $role)->value('id'),
            'organization_id' => $farm->id,
        ]);
    }

    /** @return list<string> */
    private function sidebarLabels(User $user): array
    {
        $labels = [];
        $walk = function (array $items) use (&$walk, &$labels) {
            foreach ($items as $item) {
                $labels[] = $item['label'];
                $walk($item['children'] ?? []);
            }
        };
        foreach (NavigationHelper::sidebarSections($user) as $section) {
            $walk($section['items'] ?? []);
        }

        return $labels;
    }
}
