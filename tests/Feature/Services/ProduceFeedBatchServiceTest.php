<?php

namespace Tests\Feature\Services;

use App\Models\DomainEvent;
use App\Models\FeedType;
use App\Models\Organization;
use App\Models\RawMaterial;
use App\Models\Role;
use App\Models\User;
use App\Services\Feed\ProduceFeedBatchService;
use App\Services\Inventory\ReceiveRawMaterialService;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProduceFeedBatchServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create(['slug' => 'cf', 'name' => 'CF']);
        $role = Role::firstOrCreate(['name' => 'manager']);
        $this->actor = User::forceCreate([
            'name' => 'Mgr', 'email' => 'mgr_' . uniqid() . '@example.com',
            'password' => bcrypt('x'),
            'role' => 'manager', 'role_id' => $role->id,
            'organization_id' => $this->org->id,
        ]);
        $this->app->make(CurrentOrganization::class)->bind($this->org);
    }

    protected function setupRecipe(): FeedType
    {
        $maize    = $this->makeMaterial('Maize', 500, 4.00);
        $soya     = $this->makeMaterial('Soya', 200, 8.00);

        $feedType = FeedType::create(['name' => 'Grower Mix']);

        $feedType->recipes()->createMany([
            ['raw_material_id' => $maize->id, 'quantity_per_100kg' => 70],
            ['raw_material_id' => $soya->id,  'quantity_per_100kg' => 30],
        ]);

        return $feedType;
    }

    protected function makeMaterial(string $name, float $qty, float $cost): RawMaterial
    {
        $m = RawMaterial::create([
            'name' => $name,
            'unit' => 'kg',
            'quantity_in_stock' => 0,
            'unit_cost' => 0,
            'reorder_level' => 50,
        ]);

        $this->app->make(ReceiveRawMaterialService::class)->execute(
            $m, $this->actor,
            ['quantity' => $qty, 'unit_cost' => $cost, 'reference' => 'Opening'],
        );

        return $m->fresh();
    }

    public function test_mix_creates_feed_batch_with_total_cost_at_wac(): void
    {
        $feedType = $this->setupRecipe();
        $service = $this->app->make(ProduceFeedBatchService::class);

        $result = $service->execute($this->actor, [
            'feed_type_id'  => $feedType->id,
            'batch_date'    => now()->toDateString(),
            'batch_size_kg' => 100,
        ]);

        $batch = $result['feed_batch'];

        // 70kg Maize @ 4.00 = 280
        // 30kg Soya  @ 8.00 = 240
        // Total = 520
        $this->assertSame(520.0, (float) $batch->total_cost);
        $this->assertSame(2, $batch->items()->count());
    }

    public function test_mix_decrements_raw_materials(): void
    {
        $feedType = $this->setupRecipe();
        $service = $this->app->make(ProduceFeedBatchService::class);

        $service->execute($this->actor, [
            'feed_type_id'  => $feedType->id,
            'batch_date'    => now()->toDateString(),
            'batch_size_kg' => 100,
        ]);

        $maize = RawMaterial::where('name', 'Maize')->first();
        $soya  = RawMaterial::where('name', 'Soya')->first();

        $this->assertSame('430.00', (string) $maize->fresh()->quantity_in_stock);  // 500 - 70
        $this->assertSame('170.00', (string) $soya->fresh()->quantity_in_stock);   // 200 - 30
    }

    public function test_mix_dispatches_feed_batch_produced_event(): void
    {
        $feedType = $this->setupRecipe();
        $service = $this->app->make(ProduceFeedBatchService::class);

        $service->execute($this->actor, [
            'feed_type_id'  => $feedType->id,
            'batch_date'    => now()->toDateString(),
            'batch_size_kg' => 100,
        ]);

        $event = DomainEvent::where('event_type', 'feed_batch.produced')->latest()->first();

        $this->assertNotNull($event);
        $this->assertSame((int) $feedType->id, (int) $event->payload['feed_type_id']);
        $this->assertSame(100.0, (float) $event->payload['batch_size_kg']);
        $this->assertSame(520.0, (float) $event->payload['total_cost']);
        $this->assertCount(2, $event->payload['ingredients']);
    }

    public function test_mix_aborts_if_recipe_empty(): void
    {
        $feedType = FeedType::create(['name' => 'Empty Recipe']);
        $service = $this->app->make(ProduceFeedBatchService::class);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('does not have a recipe yet');

        $service->execute($this->actor, [
            'feed_type_id'  => $feedType->id,
            'batch_date'    => now()->toDateString(),
            'batch_size_kg' => 100,
        ]);
    }

    public function test_mix_aborts_if_insufficient_stock(): void
    {
        $feedType = $this->setupRecipe();

        // Drain Maize so we cannot mix.
        $maize = RawMaterial::where('name', 'Maize')->first();
        $maize->update(['quantity_in_stock' => 10]);

        $service = $this->app->make(ProduceFeedBatchService::class);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('Not enough stock for Maize');

        $service->execute($this->actor, [
            'feed_type_id'  => $feedType->id,
            'batch_date'    => now()->toDateString(),
            'batch_size_kg' => 100,
        ]);
    }
}
