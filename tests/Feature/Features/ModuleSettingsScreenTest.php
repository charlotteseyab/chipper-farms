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
 * The screen is the only way an administrator changes module configuration, so
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

    protected function user(bool $canManage): User
    {
        $role = Role::create(['name' => 'r_' . uniqid()]);

        if ($canManage) {
            $role->permissions()->attach(
                Permission::where('name', 'manage_feature_flags')->value('id')
            );
        }

        return User::forceCreate([
            'name' => 'U', 'email' => 'u_' . uniqid() . '@example.com',
            'password' => bcrypt('x'), 'role' => $role->name, 'role_id' => $role->id,
            'organization_id' => $this->org->id, 'active_status' => true,
        ]);
    }

    /* ------------------------------------------------------------------ */

    public function test_screen_renders_for_an_authorised_administrator(): void
    {
        $this->actingAs($this->user(true))
            ->get(route('settings.modules'))
            ->assertOk()
            ->assertSee('Modules')
            ->assertSee('Record Expense');
    }

    public function test_screen_is_refused_without_the_permission(): void
    {
        $this->actingAs($this->user(false))
            ->get(route('settings.modules'))
            ->assertForbidden();
    }

    public function test_toggling_a_feature_persists(): void
    {
        $admin = $this->user(true);

        $this->actingAs($admin)
            ->put(route('settings.modules.update'), [
                'feature_key' => FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING,
                'enabled'     => 1,
            ])
            ->assertRedirect(route('settings.modules'));

        $this->service()->forget();
        $this->assertTrue(
            $this->service()->enabled(FeatureCatalog::FINANCE_ADVANCED_ACCOUNTING)
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

    public function test_an_unknown_feature_key_is_rejected_by_validation(): void
    {
        $this->actingAs($this->user(true))
            ->put(route('settings.modules.update'), [
                'feature_key' => 'finance.not_real',
                'enabled'     => 1,
            ])
            ->assertSessionHasErrors('feature_key');
    }

    public function test_applying_a_preset_from_the_screen_works(): void
    {
        $this->actingAs($this->user(true))
            ->post(route('settings.modules.preset'), ['preset' => ModulePreset::ESSENTIALS])
            ->assertRedirect(route('settings.modules'));

        $this->service()->forget();
        $this->assertTrue($this->service()->enabled(FeatureCatalog::FINANCE_QUICK_EXPENSE));
    }

    public function test_an_unknown_preset_is_rejected_by_validation(): void
    {
        $this->actingAs($this->user(true))
            ->post(route('settings.modules.preset'), ['preset' => 'nope'])
            ->assertSessionHasErrors('preset');
    }

    /**
     * The lockout guard. The settings screen must not be gated by a feature
     * flag, or switching the wrong thing off would remove the only route back.
     */
    public function test_the_screen_stays_reachable_after_an_exclusive_reset(): void
    {
        $admin = $this->user(true);

        $this->actingAs($admin)
            ->post(route('settings.modules.preset'), [
                'preset'    => ModulePreset::ESSENTIALS,
                'exclusive' => 1,
            ])
            ->assertRedirect();

        $this->service()->forget();

        $this->actingAs($admin)
            ->get(route('settings.modules'))
            ->assertOk();
    }

    public function test_the_screen_explains_what_a_disable_will_cascade_to(): void
    {
        $this->service()->enable(FeatureCatalog::FINANCE_WHT);

        $this->actingAs($this->user(true))
            ->get(route('settings.modules'))
            ->assertOk()
            ->assertSee('will also switch off', false);
    }
}
