<?php

namespace Tests\Feature\Operations;

use App\Models\DomainEvent;
use App\Models\FeedBatch;
use App\Models\FeedType;
use App\Models\Counterparty;
use App\Models\Organization;
use App\Models\RawMaterial;
use App\Models\Role;
use App\Models\User;
use App\Services\Inventory\AdjustRawMaterialService;
use App\Services\Inventory\ReceiveRawMaterialService;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\FarmOperationsPermissionsSeeder;
use Tests\TestCase;

/**
 * Phase 0.6 — regression baseline for feed batch production.
 *
 * Locks in:
 *   - mixFeed consumes raw materials per recipe at correct quantities
 *   - raw material stock is decremented, stock logs are written
 *   - total_cost is computed from actual unit_cost at mix time
 *   - insufficient stock aborts the batch atomically (no partial state)
 *   - FeedBatchProduced domain event is emitted with the full ingredient set
 *
 * Phase 0.7 (weighted-average valuation) will change how unit_cost is
 * computed; these tests lock the OUTPUTS (stock deductions, batch cost,
 * event payload shape) so the valuation rework is forced to be observationally
 * compatible with the current behaviour for existing test data.
 */
class FeedBatchWorkflowBaselineTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected User $admin;
    protected Counterparty $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        // Farm-operations permissions gate the feed, raw-material and
        // weight-alert routes exercised here (CF-P0-002). Without this the
        // admin fixture holds no livestock/feed permission and gets 403.
        $this->seed(FarmOperationsPermissionsSeeder::class);

        $this->org = Organization::create(['slug' => 'chipper-farms', 'name' => 'Chipper Farms']);

        $role = Role::firstOrCreate(['name' => 'admin']);
        $this->admin = User::forceCreate([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('x'),
            'role' => 'admin',
            'role_id' => $role->id,
            'organization_id' => $this->org->id,
        ]);

        $this->app->make(CurrentOrganization::class)->bind($this->org);

        $this->supplier = Counterparty::create([
            'organization_id' => $this->org->id,
            'type' => 'supplier',
            'name' => 'Opening Stock Supplier',
            'currency_code' => 'GHS',
            'is_active' => true,
        ]);
    }

    protected function seedFeedSetup(): array
    {
        $maize = $this->makeMaterial('Maize', 'grain', 500, 5.00);
        $soya = $this->makeMaterial('Soya meal', 'protein', 200, 9.00);

        $feedType = FeedType::create(['name' => 'Grower mix']);

        $feedType->recipes()->createMany([
            ['raw_material_id' => $maize->id, 'quantity_per_100kg' => 70],
            ['raw_material_id' => $soya->id,  'quantity_per_100kg' => 30],
        ]);

        return compact('maize', 'soya', 'feedType');
    }

    protected function makeMaterial(string $name, string $category, float $quantity, float $unitCost): RawMaterial
    {
        $material = RawMaterial::create([
            'name' => $name,
            'category' => $category,
            'unit' => 'kg',
            'quantity_in_stock' => 0,
            'unit_cost' => 0,
            'reorder_level' => 50,
        ]);

        $this->app->make(ReceiveRawMaterialService::class)->execute(
            $material,
            $this->admin,
            [
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'reference' => 'Opening ' . $name,
                'supplier_id' => $this->supplier->id,
                'receipt_reason' => 'opening_stock',
            ],
        );

        return $material->fresh();
    }

    public function test_mix_feed_consumes_raw_materials_per_recipe(): void
    {
        ['maize' => $maize, 'soya' => $soya, 'feedType' => $feedType] = $this->seedFeedSetup();

        $this->actingAs($this->admin)
            ->post(route('feed-production.mix'), [
                'feed_type_id' => $feedType->id,
                'batch_date' => now()->toDateString(),
                'batch_size_kg' => 200,  // multiplier = 2 (per 100kg)
            ])
            ->assertRedirect();

        // Expected deductions:
        //   maize: 70 * 2 = 140
        //   soya:  30 * 2 = 60
        $this->assertSame(360.0, (float) $maize->fresh()->quantity_in_stock); // 500 - 140
        $this->assertSame(140.0, (float) $soya->fresh()->quantity_in_stock);  // 200 - 60
    }

    public function test_mix_feed_records_total_cost_from_unit_costs(): void
    {
        ['feedType' => $feedType] = $this->seedFeedSetup();

        $this->actingAs($this->admin)
            ->post(route('feed-production.mix'), [
                'feed_type_id' => $feedType->id,
                'batch_date' => now()->toDateString(),
                'batch_size_kg' => 100,  // multiplier = 1
            ])
            ->assertRedirect();

        // 70 kg maize @ 5 = 350, 30 kg soya @ 9 = 270, total = 620
        $batch = FeedBatch::latest()->first();
        $this->assertNotNull($batch);
        $this->assertSame(620.0, (float) $batch->total_cost);
        $this->assertSame(100.0, (float) $batch->batch_size_kg);
    }

    public function test_mix_feed_aborts_atomically_when_stock_is_insufficient(): void
    {
        ['maize' => $maize, 'soya' => $soya, 'feedType' => $feedType] = $this->seedFeedSetup();

        // Drain soya so the batch will fail on the second ingredient.
        $this->app->make(AdjustRawMaterialService::class)->execute(
            $soya,
            $this->admin,
            [
                'adjustment_type' => 'deduct',
                'quantity' => 195,
                'reference' => 'Drain for insufficient stock test',
            ],
        );
        $maizeBefore = $maize->quantity_in_stock;

        $this->actingAs($this->admin)
            ->post(route('feed-production.mix'), [
                'feed_type_id' => $feedType->id,
                'batch_date' => now()->toDateString(),
                'batch_size_kg' => 100,  // needs 30 kg soya, only 5 available
            ])
            ->assertRedirect(route('feed-production.index'))
            ->assertSessionHas('mix_error', fn ($message) => str_contains($message, 'Not enough stock for Soya meal'));

        // No stock changed — atomic failure.
        $this->assertSame((float) $maizeBefore, (float) $maize->fresh()->quantity_in_stock);
        $this->assertSame(5.0, (float) $soya->fresh()->quantity_in_stock);

        // No batch was created.
        $this->assertSame(0, FeedBatch::count());
    }

    public function test_mix_feed_emits_feed_batch_produced_event_with_ingredients(): void
    {
        ['feedType' => $feedType] = $this->seedFeedSetup();

        $this->actingAs($this->admin)
            ->post(route('feed-production.mix'), [
                'feed_type_id' => $feedType->id,
                'batch_date' => now()->toDateString(),
                'batch_size_kg' => 100,
            ])
            ->assertRedirect();

        $event = DomainEvent::where('event_type', 'feed_batch.produced')->latest()->first();

        $this->assertNotNull($event);
        $this->assertSame(100.0, (float) $event->payload['batch_size_kg']);
        $this->assertSame(620.0, (float) $event->payload['total_cost']);
        $this->assertCount(2, $event->payload['ingredients']);

        $ingredientNames = array_column($event->payload['ingredients'], 'raw_material_name');
        $this->assertContains('Maize', $ingredientNames);
        $this->assertContains('Soya meal', $ingredientNames);
    }

    public function test_feed_type_without_recipe_is_rejected(): void
    {
        $feedType = FeedType::create(['name' => 'Unconfigured mix']);

        $this->actingAs($this->admin)
            ->post(route('feed-production.mix'), [
                'feed_type_id' => $feedType->id,
                'batch_date' => now()->toDateString(),
                'batch_size_kg' => 100,
            ])
            ->assertRedirect(route('feed-production.index'))
            ->assertSessionHas('mix_error', 'This feed type does not have a recipe yet.');

        $this->assertSame(0, FeedBatch::count());
    }
}
