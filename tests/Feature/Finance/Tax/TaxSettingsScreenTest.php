<?php

namespace Tests\Feature\Finance\Tax;

use App\Models\Finance\TaxProfile;
use App\Models\Finance\TaxProfileComponent;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\Tax\TaxCalculator;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tax settings screen — completes Phase 2.
 *
 * Phase 2 built the engine and deferred the screen, which meant a farm could
 * not enter the taxes its business charges. This is what stands between the tax
 * work and client sign-off.
 *
 * The tests that matter are the ones proving a client can express THEIR taxes,
 * not Ghana's: a percentage, a flat amount, and a levy that compounds. The
 * seeded Ghana profiles are a starting point, not the model.
 */
class TaxSettingsScreenTest extends TestCase
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

        Permission::firstOrCreate(['name' => 'manage_tax_settings'],
            ['description' => 'T', 'module' => 'finance']);
    }

    protected function user(bool $canManage = true): User
    {
        $role = Role::create(['name' => 'r_' . uniqid()]);

        if ($canManage) {
            $role->permissions()->attach(Permission::where('name', 'manage_tax_settings')->value('id'));
        }

        return User::forceCreate([
            'name' => 'U', 'email' => 'u_' . uniqid() . '@example.com',
            'password' => bcrypt('x'), 'role' => $role->name, 'role_id' => $role->id,
            'organization_id' => $this->org->id, 'active_status' => true,
        ]);
    }

    protected function profile(array $overrides = []): TaxProfile
    {
        return TaxProfile::create(array_merge([
            'organization_id' => $this->org->id,
            'code'            => 'T' . strtoupper(substr(uniqid(), -6)),
            'name'            => 'Test Tax',
            'treatment'       => TaxProfile::TREATMENT_STANDARD,
            'applies_to'      => TaxProfile::APPLIES_BOTH,
            'price_mode'      => TaxProfile::PRICE_EXCLUSIVE,
            'effective_from'  => '2026-01-01',
            'is_active'       => true,
        ], $overrides));
    }

    /* ------------------------------------------------------------------ */
    /* Access                                                             */
    /* ------------------------------------------------------------------ */

    public function test_the_screen_renders_for_an_authorised_user(): void
    {
        $this->actingAs($this->user())
            ->get(route('finance.tax.index'))
            ->assertOk()
            ->assertSee('Tax');
    }

    public function test_the_screen_is_refused_without_the_permission(): void
    {
        $this->actingAs($this->user(false))
            ->get(route('finance.tax.index'))
            ->assertForbidden();
    }

    public function test_a_farm_with_no_tax_is_warned_that_untaxed_is_not_exempt(): void
    {
        $this->actingAs($this->user())
            ->get(route('finance.tax.index'))
            ->assertOk()
            ->assertSee('not the same as being exempt');
    }

    /* ------------------------------------------------------------------ */
    /* A client entering their own taxes                                  */
    /* ------------------------------------------------------------------ */

    public function test_a_client_can_create_a_tax_and_give_it_a_percentage_charge(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post(route('finance.tax.profiles.store'), [
            'code' => 'FARM_LEVY', 'name' => 'Farm Levy',
            'treatment' => TaxProfile::TREATMENT_STANDARD,
            'applies_to' => TaxProfile::APPLIES_SALE,
            'price_mode' => TaxProfile::PRICE_EXCLUSIVE,
            'effective_from' => '2026-01-01',
        ])->assertRedirect();

        $profile = TaxProfile::where('code', 'FARM_LEVY')->firstOrFail();

        $this->actingAs($user)->post(route('finance.tax.components.store', $profile), [
            'code' => 'LEVY', 'label' => 'Levy', 'rate_type' => 'percent',
            'rate' => '7.5', 'base_mode' => 'net',
        ])->assertRedirect();

        $calc = app(TaxCalculator::class)->apply($profile->fresh()->load('components'), '200.00');

        $this->assertSame('15.00', $calc->totalTax, '7.5% of 200.00.');
    }

    /**
     * The case the client raised directly: a flat amount per transaction rather
     * than a rate.
     */
    public function test_a_client_can_charge_a_fixed_amount_rather_than_a_percentage(): void
    {
        $profile = $this->profile();

        $this->actingAs($this->user())->post(route('finance.tax.components.store', $profile), [
            'code' => 'PERPIG', 'label' => 'Levy per animal', 'rate_type' => 'fixed',
            'rate' => '12.50', 'base_mode' => 'net',
        ])->assertRedirect();

        $calc = app(TaxCalculator::class)->apply($profile->fresh()->load('components'), '500.00');

        $this->assertSame(
            '12.50',
            $calc->totalTax,
            'A fixed charge must not scale with the amount — that is what makes it fixed.',
        );
    }

    public function test_a_fixed_charge_stays_fixed_regardless_of_the_sale_size(): void
    {
        $profile = $this->profile();

        $this->actingAs($this->user())->post(route('finance.tax.components.store', $profile), [
            'code' => 'FLAT', 'label' => 'Flat fee', 'rate_type' => 'fixed',
            'rate' => '20.00', 'base_mode' => 'net',
        ]);

        $calculator = app(TaxCalculator::class);
        $profile    = $profile->fresh()->load('components');

        $this->assertSame('20.00', $calculator->apply($profile, '100.00')->totalTax);
        $this->assertSame('20.00', $calculator->apply($profile, '9000.00')->totalTax);
    }

    public function test_a_client_can_mix_a_percentage_and_a_fixed_charge(): void
    {
        $profile = $this->profile();
        $user    = $this->user();

        $this->actingAs($user)->post(route('finance.tax.components.store', $profile), [
            'code' => 'VAT', 'label' => 'VAT', 'rate_type' => 'percent',
            'rate' => '10', 'base_mode' => 'net',
        ]);
        $this->actingAs($user)->post(route('finance.tax.components.store', $profile), [
            'code' => 'FLAT', 'label' => 'Handling', 'rate_type' => 'fixed',
            'rate' => '5.00', 'base_mode' => 'net',
        ]);

        $calc = app(TaxCalculator::class)->apply($profile->fresh()->load('components'), '100.00');

        $this->assertSame('15.00', $calc->totalTax, '10% of 100.00 plus a flat 5.00.');
    }

    public function test_a_charge_can_be_told_to_compound_on_the_others(): void
    {
        $profile = $this->profile();
        $user    = $this->user();

        $this->actingAs($user)->post(route('finance.tax.components.store', $profile), [
            'code' => 'LEVY', 'label' => 'Levy', 'rate_type' => 'percent',
            'rate' => '10', 'base_mode' => 'net',
        ]);
        $this->actingAs($user)->post(route('finance.tax.components.store', $profile), [
            'code' => 'VAT', 'label' => 'VAT', 'rate_type' => 'percent',
            'rate' => '10', 'base_mode' => 'compound_on_previous',
        ]);

        $calc = app(TaxCalculator::class)->apply($profile->fresh()->load('components'), '100.00');

        $this->assertSame(
            '21.00',
            $calc->totalTax,
            'Levy 10.00 on 100.00, then VAT 10% on 110.00 = 11.00. If this reads 20.00 the '
            . 'compounding checkbox did nothing.',
        );
    }

    /* ------------------------------------------------------------------ */
    /* Guard rails                                                        */
    /* ------------------------------------------------------------------ */

    public function test_the_preview_uses_the_real_calculator(): void
    {
        $profile = $this->profile(['name' => 'Preview Tax']);
        $profile->components()->create([
            'code' => 'VAT', 'label' => 'VAT', 'rate_type' => 'percent',
            'rate' => '15', 'base_mode' => 'net', 'calculation_order' => 10,
        ]);

        $this->actingAs($this->user())
            ->get(route('finance.tax.index'))
            ->assertOk()
            ->assertSee('+15.00');
    }

    public function test_setting_a_sales_default_clears_the_previous_one(): void
    {
        $first  = $this->profile(['is_default_sales' => true]);
        $second = $this->profile();

        $this->actingAs($this->user())
            ->post(route('finance.tax.profiles.default', $second), ['side' => 'sales'])
            ->assertRedirect();

        $this->assertFalse((bool) $first->fresh()->is_default_sales);
        $this->assertTrue((bool) $second->fresh()->is_default_sales);
    }

    public function test_a_duplicate_code_on_the_same_start_date_is_refused(): void
    {
        $this->profile(['code' => 'DUPE', 'effective_from' => '2026-01-01']);

        $this->actingAs($this->user())->post(route('finance.tax.profiles.store'), [
            'code' => 'DUPE', 'name' => 'Another',
            'treatment' => TaxProfile::TREATMENT_STANDARD,
            'applies_to' => TaxProfile::APPLIES_BOTH,
            'price_mode' => TaxProfile::PRICE_EXCLUSIVE,
            'effective_from' => '2026-01-01',
        ])->assertSessionHasErrors('code');
    }

    public function test_a_negative_rate_is_refused(): void
    {
        $profile = $this->profile();

        $this->actingAs($this->user())->post(route('finance.tax.components.store', $profile), [
            'code' => 'BAD', 'label' => 'Bad', 'rate_type' => 'percent',
            'rate' => '-5', 'base_mode' => 'net',
        ])->assertSessionHasErrors('rate');
    }

    public function test_another_organisations_profile_cannot_be_edited(): void
    {
        $other = Organization::create([
            'slug' => 'other-' . uniqid(), 'name' => 'Other', 'is_active' => true,
        ]);

        $theirs = TaxProfile::create([
            'organization_id' => $other->id, 'code' => 'THEIRS', 'name' => 'Theirs',
            'treatment' => TaxProfile::TREATMENT_STANDARD, 'applies_to' => TaxProfile::APPLIES_BOTH,
            'price_mode' => TaxProfile::PRICE_EXCLUSIVE, 'effective_from' => '2026-01-01',
            'is_active' => true,
        ]);

        $this->actingAs($this->user())->post(route('finance.tax.components.store', $theirs), [
            'code' => 'X', 'label' => 'X', 'rate_type' => 'percent',
            'rate' => '5', 'base_mode' => 'net',
        ])->assertNotFound();

        $this->assertSame(0, TaxProfileComponent::where('tax_profile_id', $theirs->id)->count());
    }

    public function test_a_component_can_be_removed(): void
    {
        $profile   = $this->profile();
        $component = $profile->components()->create([
            'code' => 'VAT', 'label' => 'VAT', 'rate_type' => 'percent',
            'rate' => '15', 'base_mode' => 'net', 'calculation_order' => 10,
        ]);

        $this->actingAs($this->user())
            ->delete(route('finance.tax.components.destroy', [$profile, $component]))
            ->assertRedirect();

        $this->assertSame(0, $profile->components()->count());
    }
}
