<?php

namespace Tests\Feature\Storefront;

use App\Models\Finance\TaxProfile;
use App\Models\Organization;
use App\Models\Storefront\StorefrontDeliveryZone;
use App\Models\Storefront\StorefrontSetting;
use App\Services\Organizations\OrganizationFeatureService;
use App\Storefront\Support\StorefrontSettings;
use App\Support\Features\FeatureCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Phase 8 — the storefront settings screen (blueprint §34).
 *
 * The tests are written as the ways this goes wrong rather than as "can a form
 * be saved": another farm's tax profile accepted, another farm's delivery zone
 * edited, a settings row shared between tenants, a Paystack secret rendered,
 * and an unticked checkbox silently re-ticking itself.
 */
class StorefrontSettingsScreenTest extends StorefrontTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedStorefrontPermissions();
    }

    /** @return array<string, mixed> */
    protected function validPayload(array $overrides = []): array
    {
        return array_merge([
            'public_shop_open'      => '1',
            'store_name'            => 'Chipper Farms Shop',
            'support_email'         => 'shop@chipper.test',
            'support_phone'         => '0300000000',
            'default_currency'      => 'GHS',
            'pickup_address'        => 'Farm gate, Nsawam road',
            'hero_headline'         => 'Fresh from the farm',
            'hero_subheadline'      => 'Pigs, piglets and feed',
            'tax_display_mode'      => StorefrontSetting::TAX_DISPLAY_EXCLUSIVE,
            'checkout_hold_minutes' => 30,
            'features'              => [
                FeatureCatalog::STOREFRONT_LIVE_PIGS,
                FeatureCatalog::STOREFRONT_COD,
            ],
        ], $overrides);
    }

    /* ---------------------------------------------------------------- */
    /* Authorization                                                    */
    /* ---------------------------------------------------------------- */

    public function test_screen_requires_the_settings_permission(): void
    {
        $this->actingAs($this->makeUser('salesperson'))
            ->get(route('storefront.admin.settings.index'))
            ->assertForbidden();
    }

    public function test_save_requires_the_settings_permission(): void
    {
        $this->actingAs($this->makeUser('salesperson'))
            ->put(route('storefront.admin.settings.update'), $this->validPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('storefront_settings', 0);
    }

    public function test_admin_can_open_the_screen(): void
    {
        $this->actingAs($this->makeUser('admin'))
            ->get(route('storefront.admin.settings.index'))
            ->assertOk()
            ->assertSee('Shop settings');
    }

    /**
     * A farm whose Online Store is switched off has no shop back office —
     * only the superuser can switch it back on — but keeps its orders, which
     * may still need fulfilling or refunding.
     */
    public function test_the_back_office_closes_when_the_storefront_module_is_disabled(): void
    {
        $this->seedStorefrontOrderPermissions();
        $this->seedStorefrontQuotePermissions();
        $this->disableStorefrontFeature(FeatureCatalog::STOREFRONT_ENABLED);
        $admin = $this->makeUser('admin');

        foreach (['storefront.admin.settings.index', 'storefront.admin.dashboard', 'storefront.admin.listings.index',
            'storefront.admin.push.create', 'storefront.admin.quotes.index', 'storefront.admin.placements.index',
            'storefront.admin.reports.sales'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertNotFound();
        }
        $this->actingAs($admin)->get(route('storefront.admin.orders.index'))->assertOk();

        $storefront = collect(\App\Helpers\NavigationHelper::sidebarSections($admin))->firstWhere('title', 'Storefront');
        $this->assertSame(['Orders'], array_column($storefront['items'] ?? [], 'label'));
    }

    /* ---------------------------------------------------------------- */
    /* Saving values                                                    */
    /* ---------------------------------------------------------------- */

    public function test_admin_can_save_store_details(): void
    {
        $this->actingAs($this->makeUser('admin'))
            ->put(route('storefront.admin.settings.update'), $this->validPayload())
            ->assertRedirect(route('storefront.admin.settings.index'));

        $this->assertDatabaseHas('storefront_settings', [
            'organization_id' => $this->org->id,
            'store_name'      => 'Chipper Farms Shop',
            'support_email'   => 'shop@chipper.test',
        ]);

        $this->assertSame('Chipper Farms Shop', StorefrontSettings::storeName($this->org->id));
    }

    /**
     * The defect this whole table exists to prevent (IMP-001): one farm's
     * settings must not be another farm's settings.
     */
    public function test_settings_do_not_leak_across_organisations(): void
    {
        $other = Organization::create(['slug' => 'other-farm', 'name' => 'Other Farm']);

        StorefrontSetting::create([
            'organization_id' => $other->id,
            'store_name'      => 'Other Farm Shop',
        ]);

        $this->actingAs($this->makeUser('admin'))
            ->put(route('storefront.admin.settings.update'), $this->validPayload())
            ->assertRedirect();

        $this->assertSame('Chipper Farms Shop', StorefrontSettings::storeName($this->org->id));
        $this->assertSame('Other Farm Shop', StorefrontSettings::storeName($other->id));
    }

    public function test_a_hold_shorter_than_the_minimum_is_rejected(): void
    {
        $this->actingAs($this->makeUser('admin'))
            ->put(route('storefront.admin.settings.update'), $this->validPayload([
                'checkout_hold_minutes' => 1,
            ]))
            ->assertSessionHasErrors('checkout_hold_minutes');

        $this->assertDatabaseCount('storefront_settings', 0);
    }

    public function test_a_hold_longer_than_a_day_is_rejected(): void
    {
        $this->actingAs($this->makeUser('admin'))
            ->put(route('storefront.admin.settings.update'), $this->validPayload([
                'checkout_hold_minutes' => 4321,
            ]))
            ->assertSessionHasErrors('checkout_hold_minutes');
    }

    /**
     * A posted id from another farm's tax profile list would point this shop at
     * another tenant's rates — a cross-tenant defect wearing a settings form.
     */
    public function test_a_tax_profile_from_another_organisation_is_rejected(): void
    {
        $other = Organization::create(['slug' => 'other-farm', 'name' => 'Other Farm']);

        $foreign = TaxProfile::create([
            'organization_id' => $other->id,
            'code'            => 'OTHER-VAT',
            'name'            => 'Other farm VAT',
            'applies_to'      => 'sales',
            'treatment'       => TaxProfile::TREATMENT_STANDARD,
            'effective_from'  => now()->subYear()->toDateString(),
            'is_active'       => true,
        ]);

        $this->actingAs($this->makeUser('admin'))
            ->put(route('storefront.admin.settings.update'), $this->validPayload([
                'default_tax_profile_id' => $foreign->id,
            ]))
            ->assertSessionHasErrors('default_tax_profile_id');

        $this->assertDatabaseCount('storefront_settings', 0);
    }

    /* ---------------------------------------------------------------- */
    /* Toggles write feature flags, not a second copy                   */
    /* ---------------------------------------------------------------- */

    public function test_toggles_write_feature_flags(): void
    {
        $features = $this->app->make(OrganizationFeatureService::class);

        // Feed Sales defaults on; the payload below omits it.
        $this->assertTrue($features->enabled(FeatureCatalog::STOREFRONT_FEED_SALES, $this->org->id));

        $this->actingAs($this->makeUser('admin'))
            ->put(route('storefront.admin.settings.update'), $this->validPayload([
                'features' => [
                    FeatureCatalog::STOREFRONT_LIVE_PIGS,
                    FeatureCatalog::STOREFRONT_MANURE_SALES,
                    FeatureCatalog::STOREFRONT_COD,
                ],
            ]))
            ->assertRedirect();

        $features->forget();

        $this->assertTrue($features->enabled(FeatureCatalog::STOREFRONT_MANURE_SALES, $this->org->id));
        $this->assertFalse($features->enabled(FeatureCatalog::STOREFRONT_FEED_SALES, $this->org->id));

        // One switch per behaviour: the flag row is the only record of it. If a
        // duplicate ever appears in the settings table, the two can disagree
        // and an administrator gets a toggle that does nothing.
        foreach (['cod_enabled', 'paystack_enabled', 'delivery_enabled', 'manure_sales_enabled'] as $duplicate) {
            $this->assertFalse(
                \Illuminate\Support\Facades\Schema::hasColumn('storefront_settings', $duplicate),
                "storefront_settings.{$duplicate} duplicates a feature flag.",
            );
        }
    }

    /**
     * The open/closed sign must not be the `storefront.enabled` flag: every
     * other storefront flag depends on that one, so switching it off here would
     * cascade and destroy the farm's payment and category choices for the sake
     * of a seasonal pause.
     */
    public function test_closing_the_shop_does_not_disturb_the_other_switches(): void
    {
        $features = $this->app->make(OrganizationFeatureService::class);

        $this->actingAs($this->makeUser('admin'))
            ->put(route('storefront.admin.settings.update'), $this->validPayload([
                'public_shop_open' => null,
                'features'         => [
                    FeatureCatalog::STOREFRONT_LIVE_PIGS,
                    FeatureCatalog::STOREFRONT_COD,
                    FeatureCatalog::STOREFRONT_PAYSTACK,
                ],
            ]))
            ->assertRedirect();

        $features->forget();

        $this->assertFalse(StorefrontSettings::enabled($this->org->id), 'The shop should be closed.');
        $this->assertTrue($features->enabled(FeatureCatalog::STOREFRONT_ENABLED, $this->org->id));
        $this->assertTrue($features->enabled(FeatureCatalog::STOREFRONT_COD, $this->org->id));
        $this->assertTrue($features->enabled(FeatureCatalog::STOREFRONT_PAYSTACK, $this->org->id));
    }

    /* ---------------------------------------------------------------- */
    /* Delivery zones                                                   */
    /* ---------------------------------------------------------------- */

    public function test_admin_can_add_a_delivery_area(): void
    {
        $this->actingAs($this->makeUser('admin'))
            ->post(route('storefront.admin.settings.zones.store'), [
                'name' => 'Kumasi metro',
                'fee'  => '85.5',
            ])
            ->assertRedirect(route('storefront.admin.settings.index'));

        $zone = StorefrontDeliveryZone::firstWhere('name', 'Kumasi metro');

        $this->assertNotNull($zone);
        $this->assertSame($this->org->id, $zone->organization_id);
        // Stored to two places, as a decimal string — never a float.
        $this->assertSame('85.50', $zone->feeString());
    }

    public function test_duplicate_area_names_are_rejected_per_organisation(): void
    {
        StorefrontDeliveryZone::create([
            'organization_id' => $this->org->id,
            'name' => 'Accra', 'fee' => '50.00',
        ]);

        $this->actingAs($this->makeUser('admin'))
            ->post(route('storefront.admin.settings.zones.store'), ['name' => 'Accra', 'fee' => '60.00'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, StorefrontDeliveryZone::where('name', 'Accra')->count());
    }

    /**
     * Route-model binding resolves by id alone. The organisation check is the
     * only thing between one farm and another farm's delivery pricing.
     */
    public function test_another_organisations_delivery_area_cannot_be_edited(): void
    {
        $other = Organization::create(['slug' => 'other-farm', 'name' => 'Other Farm']);

        $foreign = StorefrontDeliveryZone::create([
            'organization_id' => $other->id,
            'name' => 'Their zone', 'fee' => '10.00',
        ]);

        $this->actingAs($this->makeUser('admin'))
            ->put(route('storefront.admin.settings.zones.update', $foreign), [
                'name' => 'Hijacked', 'fee' => '0.00',
            ])
            ->assertNotFound();

        $this->assertSame('Their zone', $foreign->fresh()->name);
        $this->assertSame('10.00', $foreign->fresh()->feeString());
    }

    public function test_another_organisations_delivery_area_cannot_be_deleted(): void
    {
        $other = Organization::create(['slug' => 'other-farm', 'name' => 'Other Farm']);

        $foreign = StorefrontDeliveryZone::create([
            'organization_id' => $other->id,
            'name' => 'Their zone', 'fee' => '10.00',
        ]);

        $this->actingAs($this->makeUser('admin'))
            ->delete(route('storefront.admin.settings.zones.destroy', $foreign))
            ->assertNotFound();

        $this->assertNotNull($foreign->fresh());
    }

    /* ---------------------------------------------------------------- */
    /* Paystack health                                                  */
    /* ---------------------------------------------------------------- */

    /**
     * Blueprint §34: "config health display only, never expose secret". A
     * partially-masked key is still part of a live secret in a browser, a proxy
     * log and a support screenshot.
     */
    public function test_the_screen_never_renders_the_paystack_secret(): void
    {
        config([
            'services.paystack.secret_key' => 'sk_test_abcdef0123456789',
            'services.paystack.public_key' => 'pk_test_abcdef0123456789',
        ]);

        $response = $this->actingAs($this->makeUser('admin'))
            ->get(route('storefront.admin.settings.index'))
            ->assertOk();

        $response->assertDontSee('sk_test_abcdef0123456789');
        $response->assertDontSee('0123456789');
        $response->assertSee('TEST');
    }

    public function test_paystack_health_reports_an_unconfigured_gateway(): void
    {
        config(['services.paystack.secret_key' => null, 'services.paystack.public_key' => null]);

        $health = StorefrontSettings::paystackHealth();

        $this->assertFalse($health['configured']);
        $this->assertNull($health['mode']);
    }
}
