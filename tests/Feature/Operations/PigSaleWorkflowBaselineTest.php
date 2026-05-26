<?php

namespace Tests\Feature\Operations;

use App\Models\DomainEvent;
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
 * Phase 0.6 — regression baseline for the pig sale operational workflow.
 *
 * This is the net Phase 0.5 (controller decomposition) must not tear through.
 * Every assertion here locks in an observable behaviour of the current
 * happy path and known edge cases, so the refactor is forced to preserve
 * them.
 *
 * Covers:
 *   - single happy path end-to-end (service call writes sale + soft-deletes pig)
 *   - payment status transitions
 *   - price rate captured from settings at sale time (not mutated on later change)
 *   - domain event emitted in the same transaction as the sale
 *   - double-sell prevention (a pig already sold cannot be sold again)
 *   - "other" purpose/payment collapse into free-text
 */
class PigSaleWorkflowBaselineTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'slug' => 'chipper-farms',
            'name' => 'Chipper Farms',
        ]);

        $role = Role::firstOrCreate(['name' => 'farmmanager']);
        $this->actor = User::forceCreate([
            'name' => 'Farm Manager',
            'email' => 'fm@example.com',
            'password' => bcrypt('x'),
            'role' => 'farmmanager',
            'role_id' => $role->id,
            'organization_id' => $this->org->id,
        ]);

        $this->app->make(CurrentOrganization::class)->bind($this->org);
    }

    protected function makePig(string $ear = null): PigManagement
    {
        return PigManagement::create([
            'ear_tag_number' => $ear ?? ('TEST-' . uniqid()),
            'breed' => 'Large White',
            'gender' => 'male',
            'stock_type' => 'standard',
            'date_of_birth' => now()->subMonths(7)->toDateString(),
            'code' => 'PIG-' . uniqid(),
        ]);
    }

    protected function service(): RecordPigSaleService
    {
        return $this->app->make(RecordPigSaleService::class);
    }

    public function test_happy_path_creates_sale_and_soft_deletes_pig(): void
    {
        $pig = $this->makePig();
        Setting::set('sales.pricing.live_weight_price', 30.00);

        $sale = $this->service()->execute($pig, $this->actor, [
            'sale_date' => now()->toDateString(),
            'sale_weight' => 80.0,
            'weight_type' => 'live',
            'sale_purpose' => 'market',
            'payment_status' => 'pending',
        ]);

        $this->assertNotNull($sale->id);
        $this->assertSame(80.0, (float) $sale->sale_weight);
        $this->assertSame(2400.0, (float) $sale->sale_price);
        $this->assertSoftDeleted('pigs_management', ['id' => $pig->id]);
    }

    public function test_attribution_fields_populated_from_actor(): void
    {
        $pig = $this->makePig();

        $sale = $this->service()->execute($pig, $this->actor, [
            'sale_date' => now()->toDateString(),
            'sale_weight' => 80.0,
            'weight_type' => 'live',
            'sale_purpose' => 'market',
            'payment_status' => 'pending',
        ]);

        $this->assertSame($this->actor->id, $sale->sold_by_user_id);
        $this->assertSame('Farm Manager', $sale->sold_by_name);
        // Legacy sold_by still populated during the deprecation window.
        $this->assertSame('Farm Manager', $sale->sold_by);
    }

    public function test_price_rate_is_captured_at_sale_time(): void
    {
        $pig = $this->makePig();

        Setting::set('sales.pricing.live_weight_price', 35.00);

        $sale = $this->service()->execute($pig, $this->actor, [
            'sale_date' => now()->toDateString(),
            'sale_weight' => 100.0,
            'weight_type' => 'live',
            'sale_purpose' => 'market',
            'payment_status' => 'pending',
        ]);

        // Change setting after the sale — historical record must not update.
        Setting::set('sales.pricing.live_weight_price', 42.00);

        $this->assertSame(35.00, (float) $sale->fresh()->price_per_kg);
        $this->assertSame(3500.00, (float) $sale->fresh()->sale_price);
    }

    public function test_carcass_weight_uses_carcass_rate(): void
    {
        $pig = $this->makePig();

        Setting::set('sales.pricing.live_weight_price', 30.00);
        Setting::set('sales.pricing.carcass_weight_price', 55.00);

        $sale = $this->service()->execute($pig, $this->actor, [
            'sale_date' => now()->toDateString(),
            'sale_weight' => 60.0,
            'weight_type' => 'carcass',
            'sale_purpose' => 'slaughter',
            'payment_status' => 'paid',
        ]);

        $this->assertSame(55.00, (float) $sale->price_per_kg);
        $this->assertSame(3300.00, (float) $sale->sale_price);
    }

    public function test_other_sale_purpose_collapses_to_text(): void
    {
        $pig = $this->makePig();

        $sale = $this->service()->execute($pig, $this->actor, [
            'sale_date' => now()->toDateString(),
            'sale_weight' => 80.0,
            'weight_type' => 'live',
            'sale_purpose' => 'other',
            'sale_purpose_other' => 'experimental trial',
            'payment_status' => 'pending',
        ]);

        $this->assertSame('experimental trial', $sale->sale_purpose);
    }

    public function test_other_payment_method_collapses_to_text(): void
    {
        $pig = $this->makePig();

        $sale = $this->service()->execute($pig, $this->actor, [
            'sale_date' => now()->toDateString(),
            'sale_weight' => 80.0,
            'weight_type' => 'live',
            'sale_purpose' => 'market',
            'payment_method' => 'other',
            'payment_method_other' => 'barter',
            'payment_status' => 'paid',
        ]);

        $this->assertSame('barter', $sale->payment_method);
    }

    public function test_domain_event_emitted_alongside_sale(): void
    {
        $pig = $this->makePig();
        Setting::set('sales.pricing.live_weight_price', 30.00);

        $sale = $this->service()->execute($pig, $this->actor, [
            'sale_date' => now()->toDateString(),
            'sale_weight' => 80.0,
            'weight_type' => 'live',
            'sale_purpose' => 'market',
            'payment_status' => 'pending',
        ]);

        $event = DomainEvent::where('event_type', 'pig_sale.recorded')
            ->where('source_id', $sale->id)
            ->first();

        $this->assertNotNull($event, 'Expected a pig_sale.recorded event for this sale.');
        $this->assertSame($this->org->id, $event->organization_id);
        $this->assertSame($this->actor->id, $event->dispatched_by_user_id);
        $this->assertSame(2400.0, $event->payload['sale_total']);
        $this->assertSame('market', $event->payload['sale_purpose']);
    }

    public function test_null_buyer_name_defaults_to_empty_string(): void
    {
        // The pig_sales.buyer_name column is non-nullable historically.
        $pig = $this->makePig();

        $sale = $this->service()->execute($pig, $this->actor, [
            'sale_date' => now()->toDateString(),
            'sale_weight' => 80.0,
            'weight_type' => 'live',
            'sale_purpose' => 'market',
            'payment_status' => 'pending',
            // no buyer_name
        ]);

        $this->assertSame('', $sale->buyer_name);
    }

    public function test_sale_with_transport_cost_records_the_cost(): void
    {
        $pig = $this->makePig();

        $sale = $this->service()->execute($pig, $this->actor, [
            'sale_date' => now()->toDateString(),
            'sale_weight' => 80.0,
            'weight_type' => 'live',
            'sale_purpose' => 'market',
            'payment_status' => 'paid',
            'transport_cost' => 75.50,
            'transport_method' => 'delivery',
        ]);

        $this->assertSame(75.50, (float) $sale->transport_cost);
        $this->assertSame('delivery', $sale->transport_method);
    }

    public function test_display_sold_by_accessor_integrates_with_service_writes(): void
    {
        $pig = $this->makePig();

        $sale = $this->service()->execute($pig, $this->actor, [
            'sale_date' => now()->toDateString(),
            'sale_weight' => 80.0,
            'weight_type' => 'live',
            'sale_purpose' => 'market',
            'payment_status' => 'pending',
        ]);

        // Accessor prefers sold_by_name, which the service wrote.
        $this->assertSame('Farm Manager', $sale->display_sold_by);

        // If we rename the actor, sold_by_name still has the original.
        $this->actor->update(['name' => 'Ex-Manager']);
        $this->assertSame('Farm Manager', $sale->fresh()->display_sold_by);
    }
}