<?php

namespace Tests\Feature\Features;

use App\Models\Organization;
use App\Models\OrganizationFeatureFlag;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Organizations\OrganizationFeatureService;
use App\Support\CurrentOrganization;
use App\Support\Features\FeatureCatalog;
use App\Support\Features\ModulePreset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Settings → Modules & Features screen — Phase 1 (prompt §15).
 *
 * The screen is the only way a superuser changes module configuration, so
 * two things must hold: it is reachable only by someone entitled to reshape the
 * product for everyone else, and it cannot be used to lock that person out.
 */
class ModuleSettingsScreenTest extends TestCase
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

        OrganizationFeatureFlag::query()->delete();

        Permission::firstOrCreate(
            ['name' => 'manage_feature_flags'],
            ['description' => 'Manage modules', 'module' => 'settings'],
        );
    }

    protected function service(): OrganizationFeatureService
    {
        return app(OrganizationFeatureService::class);
    }

    protected function user(bool $canManage, string $roleName = 'staff'): User
    {
        $role = Role::create(['name' => $roleName . '_' . uniqid()]);

        if ($canManage) {
            $role->permissions()->attach(
                Permission::where('name', 'manage_feature_flags')->value('id')
            );
        }

        return User::forceCreate([
            'name' => 'U', 'email' => 'u_' . uniqid() . '@example.com',
            'password' => bcrypt('x'), 'role' => $roleName, 'role_id' => $role->id,
            'organization_id' => $this->org->id, 'active_status' => true,
        ]);
    }

    protected function superuser(): User
    {
        $role = Role::firstOrCreate(['name' => 'superuser']);

        return User::forceCreate([
            'name' => 'Superuser', 'email' => 'super_' . uniqid() . '@example.com',
            'password' => bcrypt('x'), 'role' => 'superuser', 'role_id' => $role->id,
            'organization_id' => null, 'active_status' => true,
        ]);
    }

    /* ------------------------------------------------------------------ */

    public function test_screen_renders_for_a_superuser(): void
    {
        $this->actingAs($this->superuser())
            ->get(route('settings.modules', ['farm' => $this->org->id]))
            ->assertOk()
            ->assertSee('Modules')
            ->assertSee('Record Expense');
    }

    public function test_screen_is_refused_for_admin_even_with_the_permission(): void
    {
        $this->actingAs($this->user(true, 'admin'))
            ->get(route('settings.modules'))
            ->assertForbidden();
    }

    public function test_screen_is_refused_without_the_permission(): void
    {
        $this->actingAs($this->user(false))
            ->get(route('settings.modules'))
            ->assertForbidden();
    }

    public function test_toggling_a_feature_persists(): void
    {
        $admin = $this->superuser();

        $this->actingAs($admin)
            ->put(route('settings.modules.update'), [
                'farm'        => (string) $this->org->id,
                'feature_key' => FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING,
                'enabled'     => 1,
            ])
            ->assertRedirect(route('settings.modules', ['farm' => $this->org->id]));

        $this->service()->forget();
        $this->assertTrue(
            $this->service()->enabled(FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING, $this->org)
        );
    }

    public function test_toggling_is_refused_without_the_permission(): void
    {
        $this->actingAs($this->user(false))
            ->put(route('settings.modules.update'), [
                'feature_key' => FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING,
                'enabled'     => 1,
            ])
            ->assertForbidden();

        $this->service()->forget();
        $this->assertFalse(
            $this->service()->enabled(FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING)
        );
    }

    public function test_toggling_is_refused_for_admin_even_with_the_permission(): void
    {
        $this->actingAs($this->user(true, 'admin'))
            ->put(route('settings.modules.update'), [
                'feature_key' => FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING,
                'enabled'     => 1,
            ])
            ->assertForbidden();

        $this->service()->forget();
        $this->assertFalse(
            $this->service()->enabled(FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING)
        );
    }

    public function test_an_unknown_feature_key_is_rejected_by_validation(): void
    {
        $this->actingAs($this->superuser())
            ->put(route('settings.modules.update'), [
                'feature_key' => 'finance.not_real',
                'enabled'     => 1,
            ])
            ->assertSessionHasErrors('feature_key');
    }

    public function test_applying_a_preset_from_the_screen_works(): void
    {
        $this->actingAs($this->superuser())
            ->post(route('settings.modules.preset'), ['farm' => (string) $this->org->id, 'preset' => ModulePreset::ESSENTIALS])
            ->assertRedirect(route('settings.modules', ['farm' => $this->org->id]));

        $this->service()->forget();
        $this->assertTrue($this->service()->enabled(FeatureCatalog::FINANCE_QUICK_EXPENSE, $this->org));
    }

    public function test_applying_a_preset_is_refused_for_admin_even_with_the_permission(): void
    {
        $this->actingAs($this->user(true, 'admin'))
            ->post(route('settings.modules.preset'), ['preset' => ModulePreset::ESSENTIALS])
            ->assertForbidden();
    }

    public function test_an_unknown_preset_is_rejected_by_validation(): void
    {
        $this->actingAs($this->superuser())
            ->post(route('settings.modules.preset'), ['preset' => 'nope'])
            ->assertSessionHasErrors('preset');
    }

    /**
     * The lockout guard. The settings screen must not be gated by a feature
     * flag, or switching the wrong thing off would remove the only route back.
     */
    public function test_the_screen_stays_reachable_after_an_exclusive_reset(): void
    {
        $admin = $this->superuser();

        $this->actingAs($admin)
            ->post(route('settings.modules.preset'), [
                'farm'   => (string) $this->org->id,
                'preset' => ModulePreset::ESSENTIALS,
            ])
            ->assertRedirect();

        $this->service()->forget();

        $this->actingAs($admin)
            ->get(route('settings.modules', ['farm' => $this->org->id]))
            ->assertOk();
    }

    public function test_the_screen_explains_what_a_disable_will_cascade_to(): void
    {
        $this->service()->enable(FeatureCatalog::FINANCE_WHT, $this->org);

        $this->actingAs($this->superuser())
            ->get(route('settings.modules', ['farm' => $this->org->id]))
            ->assertOk()
            ->assertSee('will also switch off', false);
    }

    /* ------------------------------------------------------------------ */
    /* Which farm                                                         */
    /* ------------------------------------------------------------------ */

    public function test_a_switch_changes_only_the_chosen_farm(): void
    {
        $other = Organization::create(['slug' => 'other-' . uniqid(), 'name' => 'Other Farm', 'is_active' => true]);

        $this->actingAs($this->superuser())->put(route('settings.modules.update'), [
            'farm' => (string) $this->org->id, 'feature_key' => FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING, 'enabled' => 1,
        ]);

        $this->service()->forget();
        $this->assertTrue($this->service()->enabled(FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING, $this->org));
        $this->assertFalse($this->service()->enabled(FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING, $other),
            'Switching an add-on on for one farm gave it to another.');
    }

    public function test_every_farm_changes_only_when_all_farms_is_chosen(): void
    {
        $other = Organization::create(['slug' => 'other-' . uniqid(), 'name' => 'Other Farm', 'is_active' => true]);

        $this->actingAs($this->superuser())->put(route('settings.modules.update'), [
            'farm' => 'all', 'feature_key' => FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING, 'enabled' => 1,
        ]);

        $this->service()->forget();
        $this->assertTrue($this->service()->enabled(FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING, $other));
    }

    public function test_a_change_without_a_farm_is_refused(): void
    {
        $superuser = $this->superuser();
        $this->actingAs($superuser)
            ->put(route('settings.modules.update'), ['feature_key' => FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING, 'enabled' => 1])
            ->assertSessionHasErrors('farm');

        $this->actingAs($superuser)
            ->put(route('settings.modules.update'), ['farm' => '999999', 'feature_key' => FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING, 'enabled' => 1])
            ->assertSessionHasErrors('farm');
    }

    public function test_the_screen_shows_the_chosen_farms_real_state(): void
    {
        $this->service()->enable(FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING, $this->org);

        // Advanced Accounting is off by default; on this farm it is on, so
        // its button offers to turn it off.
        $this->actingAs($this->superuser())
            ->get(route('settings.modules', ['farm' => $this->org->id]))
            ->assertOk()
            ->assertSee('Chipper Farms')
            ->assertSeeInOrder(['Advanced Accounting', 'Turn off'], false);
    }

    public function test_nothing_can_be_switched_until_a_farm_is_chosen(): void
    {
        $this->actingAs($this->superuser())
            ->get(route('settings.modules'))
            ->assertOk()
            ->assertSee('Choose a farm to see and change what it uses.')
            ->assertDontSee('Turn on');
    }

    public function test_a_smaller_setup_downgrades_the_farm_but_leaves_the_shop(): void
    {
        $this->service()->applyPreset(ModulePreset::FULL_ACCOUNTING, $this->org);
        $this->service()->enable(FeatureCatalog::STOREFRONT_MANURE_SALES, $this->org);

        $this->actingAs($this->superuser())->post(route('settings.modules.preset'), [
            'farm' => (string) $this->org->id, 'preset' => ModulePreset::ESSENTIALS,
        ]);

        $this->service()->forget();
        $this->assertFalse($this->service()->enabled(FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING, $this->org),
            'Choosing Essentials left a paid add-on on.');
        $this->assertTrue($this->service()->enabled(FeatureCatalog::FINANCE_QUICK_EXPENSE, $this->org));
        $this->assertTrue($this->service()->enabled(FeatureCatalog::STOREFRONT_MANURE_SALES, $this->org),
            'A finance setup must not switch off shop features.');
    }
}
