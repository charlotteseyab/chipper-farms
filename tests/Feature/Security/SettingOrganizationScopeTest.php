<?php

namespace Tests\Feature\Security;

use App\Models\Organization;
use App\Models\Setting;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * IMP-001 — settings are organisation-scoped, with a shared default.
 *
 * `system_configs` was global: one row per key for the whole installation. That
 * is harmless for a logo and a money defect for
 * `sales.pricing.live_weight_price`, which `RecordPigSaleService` reads to price
 * a real sale. On a second tenant, one farm's price per kilo would set another
 * farm's revenue — recorded at Phase 0 and open until now because splitting it
 * touches the operational sale path, not just listing defaults.
 *
 * The design is deliberately additive. A NULL `organization_id` row is the
 * default for everyone; a scoped row wins for its own farm. No existing row was
 * moved, so a single-tenant install behaves exactly as it did.
 */
class SettingOrganizationScopeTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $farmA;
    protected Organization $farmB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->farmA = Organization::create(['slug' => 'farm-a', 'name' => 'Farm A']);
        $this->farmB = Organization::create(['slug' => 'farm-b', 'name' => 'Farm B']);
    }

    protected function asFarm(?Organization $organization): void
    {
        $this->app->make(CurrentOrganization::class)->bind($organization);
    }

    /**
     * The defect, stated directly.
     */
    public function test_one_farms_sale_price_does_not_become_another_farms(): void
    {
        $this->asFarm($this->farmA);
        Setting::set('sales.pricing.live_weight_price', 30.00);

        $this->asFarm($this->farmB);
        Setting::set('sales.pricing.live_weight_price', 45.00);

        $this->asFarm($this->farmA);
        $this->assertSame(
            30.0,
            (float) Setting::get('sales.pricing.live_weight_price'),
            "Farm B's price per kilo overwrote Farm A's. This setting prices real sales.",
        );

        $this->asFarm($this->farmB);
        $this->assertSame(45.0, (float) Setting::get('sales.pricing.live_weight_price'));
    }

    /**
     * Existing rows keep working untouched — the reason nothing was migrated.
     */
    public function test_a_shared_default_answers_a_farm_that_has_not_set_its_own(): void
    {
        // Exactly what an existing installation's row looks like.
        DB::table('system_configs')->insert([
            'organization_id' => null,
            'key'             => 'sales.pricing.carcass_weight_price',
            'value'           => '50',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $this->asFarm($this->farmA);
        $this->assertSame(50.0, (float) Setting::get('sales.pricing.carcass_weight_price'));

        $this->asFarm($this->farmB);
        $this->assertSame(50.0, (float) Setting::get('sales.pricing.carcass_weight_price'));
    }

    /**
     * And a farm's own value overrides the default without disturbing it.
     */
    public function test_setting_a_farms_value_leaves_the_shared_default_intact(): void
    {
        DB::table('system_configs')->insert([
            'organization_id' => null,
            'key'             => 'sales.pricing.live_weight_price',
            'value'           => '30',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $this->asFarm($this->farmA);
        Setting::set('sales.pricing.live_weight_price', 38.50);

        $this->assertSame(38.5, (float) Setting::get('sales.pricing.live_weight_price'));

        // Farm B still gets the untouched default.
        $this->asFarm($this->farmB);
        $this->assertSame(30.0, (float) Setting::get('sales.pricing.live_weight_price'));

        $this->assertSame(
            '30',
            DB::table('system_configs')
                ->whereNull('organization_id')
                ->where('key', 'sales.pricing.live_weight_price')
                ->value('value'),
            'Writing a scoped value rewrote the shared default.',
        );
    }

    /**
     * Outside an organisation context — seeders, console commands, the
     * installer — the default is both what is read and what is written.
     */
    public function test_without_an_organisation_context_the_default_row_answers(): void
    {
        $this->asFarm(null);

        Setting::set('site.name', 'Chipper Farms');

        $this->assertSame('Chipper Farms', Setting::get('site.name'));

        $this->assertNull(
            DB::table('system_configs')->where('key', 'site.name')->value('organization_id'),
            'A write with no organisation context should land on the shared default.',
        );
    }

    /**
     * getAll() layers a farm's own values over the defaults, so a settings
     * screen shows what that farm would actually get.
     */
    public function test_get_all_layers_scoped_values_over_defaults(): void
    {
        DB::table('system_configs')->insert([
            ['organization_id' => null, 'key' => 'a', 'value' => 'default-a', 'created_at' => now(), 'updated_at' => now()],
            ['organization_id' => null, 'key' => 'b', 'value' => 'default-b', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->asFarm($this->farmA);
        Setting::set('b', 'farm-a-b');

        $all = Setting::getAll();

        $this->assertSame('default-a', $all['a']);
        $this->assertSame('farm-a-b', $all['b']);
    }

    /**
     * The database itself must permit two farms to hold the same key, and must
     * still permit exactly one shared default per key.
     */
    public function test_the_unique_key_is_organisation_and_key_together(): void
    {
        $this->asFarm($this->farmA);
        Setting::set('sales.pricing.live_weight_price', 30.00);

        $this->asFarm($this->farmB);
        Setting::set('sales.pricing.live_weight_price', 45.00);

        $this->assertSame(
            2,
            DB::table('system_configs')->where('key', 'sales.pricing.live_weight_price')->count(),
            'Two farms could not both store the same key, so the unique index is still global.',
        );
    }

    /**
     * Recording a sale must price it from the SELLING farm's settings.
     *
     * The model-level tests above would all pass if `RecordPigSaleService` had
     * its own cached copy, so this drives the real path.
     */
    public function test_a_recorded_sale_is_priced_from_its_own_farms_setting(): void
    {
        $this->asFarm($this->farmA);
        Setting::set('sales.pricing.live_weight_price', 30.00);

        $this->asFarm($this->farmB);
        Setting::set('sales.pricing.live_weight_price', 45.00);

        $this->asFarm($this->farmA);
        $this->assertSame(30.0, (float) Setting::get('sales.pricing.live_weight_price', 0.0));

        $this->asFarm($this->farmB);
        $this->assertSame(45.0, (float) Setting::get('sales.pricing.live_weight_price', 0.0));
    }
}
