<?php

namespace Tests\Feature;

use App\Console\Commands\BackfillPigSaleSoldBy;
use App\Models\Organization;
use App\Models\PigManagement;
use App\Models\PigSale;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\Sales\RecordPigSaleService;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 0.2 coverage:
 *   - RecordPigSaleService writes sold_by_user_id and sold_by_name correctly.
 *   - PigSale::soldBy() relation now resolves to a real user.
 *   - PigSale::display_sold_by accessor falls back gracefully.
 *   - Backfill command populates both new columns and handles ambiguity.
 */
class PhaseTwoPigSaleSoldByTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected int $userSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'slug' => 'chipper-farms',
            'name' => 'Chipper Farms',
        ]);

        $this->app->make(CurrentOrganization::class)->bind($this->org);
    }

    protected function makeUser(string $name, string $roleName = 'admin'): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);

        return User::forceCreate([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)) . '-' . (++$this->userSequence) . '@example.com',
            'password' => bcrypt('x'),
            'role' => $roleName,
            'role_id' => $role->id,
            'organization_id' => $this->org->id,
        ]);
    }

    protected function makePig(): PigManagement
    {
        return PigManagement::create([
            'ear_tag_number' => 'TEST-' . uniqid(),
            'breed' => 'Large White',
            'gender' => 'male',
            'stock_type' => 'standard',
            'date_of_birth' => now()->subMonths(6)->toDateString(),
            'code' => 'PIG-' . uniqid(),
        ]);
    }

    protected function service(): RecordPigSaleService
    {
        return $this->app->make(RecordPigSaleService::class);
    }

    public function test_service_writes_both_new_columns_for_attribution(): void
    {
        $user = $this->makeUser('Jane Doe');
        $pig = $this->makePig();

        Setting::set('sales.pricing.live_weight_price', 30.00);

        $sale = $this->service()->execute($pig, $user, [
            'sale_date' => now()->toDateString(),
            'sale_weight' => 80.0,
            'weight_type' => 'live',
            'sale_purpose' => 'market',
            'payment_status' => 'pending',
        ]);

        $this->assertSame($user->id, $sale->sold_by_user_id);
        $this->assertSame('Jane Doe', $sale->sold_by_name);
        // Legacy column still populated during deprecation window.
        $this->assertSame('Jane Doe', $sale->sold_by);
    }

    public function test_sold_by_relation_resolves_to_user(): void
    {
        $user = $this->makeUser('Acme Accountant');
        $pig = $this->makePig();

        $this->service()->execute($pig, $user, [
            'sale_date' => now()->toDateString(),
            'sale_weight' => 80.0,
            'weight_type' => 'live',
            'sale_purpose' => 'market',
            'payment_status' => 'pending',
        ]);

        $sale = PigSale::where('pig_id', $pig->id)->first();
        $this->assertNotNull($sale->soldBy);
        $this->assertSame($user->id, $sale->soldBy->id);
    }

    public function test_display_sold_by_prefers_sold_by_name_then_relation_then_legacy(): void
    {
        $user = $this->makeUser('Original Name');
        $pig = $this->makePig();

        $this->service()->execute($pig, $user, [
            'sale_date' => now()->toDateString(),
            'sale_weight' => 80.0,
            'weight_type' => 'live',
            'sale_purpose' => 'market',
            'payment_status' => 'pending',
        ]);

        $sale = PigSale::where('pig_id', $pig->id)->first();

        // All three fields were populated by the service — display should
        // pick sold_by_name (captured at sale time) even if user renames later.
        $user->update(['name' => 'Renamed User']);

        $this->assertSame('Original Name', $sale->display_sold_by);

        // When sold_by_name is cleared, accessor falls through to the relation.
        $sale->update(['sold_by_name' => null]);
        $this->assertSame('Renamed User', $sale->fresh()->display_sold_by);

        // When the FK is cleared too, accessor falls through to legacy string.
        $sale->update(['sold_by_user_id' => null]);
        $this->assertSame('Original Name', $sale->fresh()->display_sold_by);
    }

    public function test_service_wraps_write_in_transaction_and_soft_deletes_pig(): void
    {
        $user = $this->makeUser('Jane Doe');
        $pig = $this->makePig();

        $this->service()->execute($pig, $user, [
            'sale_date' => now()->toDateString(),
            'sale_weight' => 80.0,
            'weight_type' => 'live',
            'sale_purpose' => 'market',
            'payment_status' => 'pending',
        ]);

        $this->assertSoftDeleted('pigs_management', ['id' => $pig->id]);
        $this->assertDatabaseHas('pig_sales', ['pig_id' => $pig->id]);
    }

    public function test_service_collapses_other_sale_purpose_into_text_value(): void
    {
        $user = $this->makeUser('Jane Doe');
        $pig = $this->makePig();

        $sale = $this->service()->execute($pig, $user, [
            'sale_date' => now()->toDateString(),
            'sale_weight' => 80.0,
            'weight_type' => 'live',
            'sale_purpose' => 'other',
            'sale_purpose_other' => 'charity donation',
            'payment_status' => 'pending',
        ]);

        $this->assertSame('charity donation', $sale->sale_purpose);
    }

    public function test_service_captures_price_per_kg_from_settings_at_sale_time(): void
    {
        $user = $this->makeUser('Jane Doe');
        $pig = $this->makePig();

        Setting::set('sales.pricing.live_weight_price', 35.50);

        $sale = $this->service()->execute($pig, $user, [
            'sale_date' => now()->toDateString(),
            'sale_weight' => 100.0,
            'weight_type' => 'live',
            'sale_purpose' => 'market',
            'payment_status' => 'pending',
        ]);

        // Capture value at sale time; later setting changes don't affect it.
        Setting::set('sales.pricing.live_weight_price', 40.00);

        $this->assertSame(35.50, (float) $sale->fresh()->price_per_kg);
    }

    public function test_backfill_command_resolves_unique_name_match(): void
    {
        $user = $this->makeUser('Uniquely Named Seller');
        $pig = $this->makePig();

        // Simulate a legacy sale row: sold_by populated as string,
        // sold_by_user_id and sold_by_name both null.
        PigSale::create([
            'pig_id' => $pig->id,
            'sale_date' => now()->toDateString(),
            'sale_price' => 1000,
            'sale_weight' => 80,
            'weight_type' => 'live',
            'buyer_name' => 'Market',
            'sale_purpose' => 'market',
            'payment_status' => 'pending',
            'sold_by' => 'Uniquely Named Seller',
        ]);

        $this->artisan('chipper:backfill-pig-sale-sold-by')->assertSuccessful();

        $sale = PigSale::where('pig_id', $pig->id)->first();
        $this->assertSame($user->id, $sale->sold_by_user_id);
        $this->assertSame('Uniquely Named Seller', $sale->sold_by_name);
    }

    public function test_backfill_command_copies_name_but_leaves_fk_null_for_ambiguous_match(): void
    {
        $this->makeUser('Ambiguous Name');
        $this->makeUser('Ambiguous Name');  // Two users with the same name

        $pig = $this->makePig();
        PigSale::create([
            'pig_id' => $pig->id,
            'sale_date' => now()->toDateString(),
            'sale_price' => 1000,
            'sale_weight' => 80,
            'weight_type' => 'live',
            'buyer_name' => 'Market',
            'sale_purpose' => 'market',
            'payment_status' => 'pending',
            'sold_by' => 'Ambiguous Name',
        ]);

        $this->artisan('chipper:backfill-pig-sale-sold-by')->assertSuccessful();

        $sale = PigSale::where('pig_id', $pig->id)->first();
        $this->assertNull($sale->sold_by_user_id);
        $this->assertSame('Ambiguous Name', $sale->sold_by_name);
    }

    public function test_backfill_command_is_idempotent(): void
    {
        $user = $this->makeUser('Re-Runnable Seller');
        $pig = $this->makePig();
        PigSale::create([
            'pig_id' => $pig->id,
            'sale_date' => now()->toDateString(),
            'sale_price' => 1000,
            'sale_weight' => 80,
            'weight_type' => 'live',
            'buyer_name' => 'Market',
            'sale_purpose' => 'market',
            'payment_status' => 'pending',
            'sold_by' => 'Re-Runnable Seller',
        ]);

        $this->artisan('chipper:backfill-pig-sale-sold-by')->assertSuccessful();
        $this->artisan('chipper:backfill-pig-sale-sold-by')->assertSuccessful();

        $sale = PigSale::where('pig_id', $pig->id)->first();
        $this->assertSame($user->id, $sale->sold_by_user_id);
    }

    public function test_backfill_dry_run_writes_nothing(): void
    {
        $user = $this->makeUser('Dry Run Seller');
        $pig = $this->makePig();
        PigSale::create([
            'pig_id' => $pig->id,
            'sale_date' => now()->toDateString(),
            'sale_price' => 1000,
            'sale_weight' => 80,
            'weight_type' => 'live',
            'buyer_name' => 'Market',
            'sale_purpose' => 'market',
            'payment_status' => 'pending',
            'sold_by' => 'Dry Run Seller',
        ]);

        $this->artisan('chipper:backfill-pig-sale-sold-by', ['--dry-run' => true])
            ->assertSuccessful();

        $sale = PigSale::where('pig_id', $pig->id)->first();
        $this->assertNull($sale->sold_by_user_id);
        $this->assertNull($sale->sold_by_name);
    }
}
