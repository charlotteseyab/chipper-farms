<?php

namespace Tests\Feature\Storefront;

use App\Models\Organization;
use App\Models\Setting;
use App\Models\Storefront\StorefrontDeliveryZone;
use App\Models\Storefront\StorefrontSetting;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8 schema and backfill.
 *
 * The backfill is the interesting half: it carries the old global
 * `storefront.*` values onto every organisation, and it must be safe to run
 * twice — a partial migration that resumes must not overwrite an
 * administrator's later choices.
 */
class StorefrontSettingsMigrationTest extends StorefrontTestCase
{
    use RefreshDatabase;

    public function test_the_new_tables_exist_with_their_columns(): void
    {
        $this->assertTrue(Schema::hasTable('storefront_settings'));
        $this->assertTrue(Schema::hasTable('storefront_delivery_zones'));

        foreach ([
            'organization_id', 'public_shop_open', 'store_name', 'support_email',
            'support_phone', 'default_currency', 'pickup_address', 'hero_headline',
            'hero_subheadline', 'tax_display_mode', 'default_tax_profile_id',
            'checkout_hold_minutes', 'refund_policy', 'updated_by',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('storefront_settings', $column),
                "storefront_settings missing column: {$column}",
            );
        }

        foreach (['organization_id', 'name', 'description', 'fee', 'is_active', 'display_order'] as $column) {
            $this->assertTrue(
                Schema::hasColumn('storefront_delivery_zones', $column),
                "storefront_delivery_zones missing column: {$column}",
            );
        }
    }

    public function test_orders_record_which_zone_priced_them(): void
    {
        $this->assertTrue(Schema::hasColumn('storefront_orders', 'delivery_zone_id'));
        $this->assertTrue(Schema::hasColumn('storefront_orders', 'delivery_zone_name'));
    }

    /**
     * The unique organisation index is the correctness anchor: without it two
     * concurrent saves leave duplicate rows and which one wins depends on row
     * order — the shape of IMP-025.
     */
    public function test_an_organisation_cannot_have_two_settings_rows(): void
    {
        StorefrontSetting::create(['organization_id' => $this->org->id, 'store_name' => 'First']);

        $this->expectException(QueryException::class);

        DB::table('storefront_settings')->insert([
            'organization_id' => $this->org->id,
            'store_name'      => 'Second',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    public function test_two_organisations_can_use_the_same_zone_name(): void
    {
        $other = Organization::create(['slug' => 'other-farm', 'name' => 'Other Farm']);

        StorefrontDeliveryZone::create(['organization_id' => $this->org->id, 'name' => 'Accra', 'fee' => '40.00']);
        StorefrontDeliveryZone::create(['organization_id' => $other->id, 'name' => 'Accra', 'fee' => '55.00']);

        $this->assertSame(2, StorefrontDeliveryZone::where('name', 'Accra')->count());
    }

    /**
     * Re-running the migration must not overwrite settings an administrator has
     * since edited. The guard is "skip organisations that already have a row";
     * this exercises the same code path the migration uses.
     */
    public function test_the_backfill_skips_organisations_that_already_have_a_row(): void
    {
        Setting::set('storefront.store_name', 'Legacy global name');

        StorefrontSetting::create([
            'organization_id' => $this->org->id,
            'store_name'      => 'Administrator chose this',
        ]);

        $this->runBackfill();

        $this->assertSame(
            'Administrator chose this',
            StorefrontSetting::where('organization_id', $this->org->id)->value('store_name'),
        );
        $this->assertSame(1, StorefrontSetting::where('organization_id', $this->org->id)->count());
    }

    public function test_the_backfill_carries_the_old_global_values_onto_an_unconfigured_organisation(): void
    {
        Setting::set('storefront.store_name', 'Legacy global name');
        Setting::set('storefront.support_phone', '0244000000');

        $this->assertSame(0, StorefrontSetting::where('organization_id', $this->org->id)->count());

        $this->runBackfill();

        $row = StorefrontSetting::where('organization_id', $this->org->id)->first();

        $this->assertNotNull($row);
        $this->assertSame('Legacy global name', $row->store_name);
        $this->assertSame('0244000000', $row->support_phone);
    }

    /**
     * Run the migration's backfill against the current database.
     *
     * The migration object is instantiated directly rather than re-running
     * `artisan migrate`, so the test exercises the real code instead of a copy
     * of it — a backfill re-implemented in a test proves nothing about the one
     * that will run on the customer's server.
     */
    protected function runBackfill(): void
    {
        $migration = require database_path(
            'migrations/2028_09_08_100000_create_storefront_settings_tables.php'
        );

        $method = new \ReflectionMethod($migration, 'backfillFromGlobalSettings');
        $method->setAccessible(true);
        $method->invoke($migration);
    }
}
