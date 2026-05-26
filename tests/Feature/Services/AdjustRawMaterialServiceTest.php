<?php

namespace Tests\Feature\Services;

use App\Models\DomainEvent;
use App\Models\Organization;
use App\Models\RawMaterial;
use App\Models\Role;
use App\Models\User;
use App\Services\Inventory\AdjustRawMaterialService;
use App\Services\Inventory\ReceiveRawMaterialService;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdjustRawMaterialServiceTest extends TestCase
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

    protected function makeMaterialWithStock(float $qty, float $unitCost): RawMaterial
    {
        $material = RawMaterial::create([
            'name' => 'Soybean',
            'unit' => 'kg',
            'quantity_in_stock' => 0,
            'unit_cost' => 0,
            'reorder_level' => 50,
        ]);

        $this->app->make(ReceiveRawMaterialService::class)->execute(
            $material,
            $this->actor,
            ['quantity' => $qty, 'unit_cost' => $unitCost, 'reference' => 'Opening Stock'],
        );

        return $material->fresh();
    }

    public function test_add_mode_increments_stock_and_dispatches_event(): void
    {
        $material = $this->makeMaterialWithStock(100, 5);
        $service = $this->app->make(AdjustRawMaterialService::class);

        $result = $service->execute($material, $this->actor, [
            'adjustment_type' => 'add',
            'quantity' => 25,
            'reference' => 'Found extra',
        ]);

        $this->assertSame('125.00', (string) $material->fresh()->quantity_in_stock);
        $this->assertSame(25.0, $result['delta']);
        $this->assertSame(125.0, $result['newQuantity']);

        $event = DomainEvent::where('event_type', 'raw_material.adjusted')->latest()->first();
        $this->assertSame('add', $event->payload['adjustment_type']);
        $this->assertSame(25.0, $event->payload['quantity_delta']);
    }

    public function test_deduct_mode_decrements_stock(): void
    {
        $material = $this->makeMaterialWithStock(100, 5);
        $service = $this->app->make(AdjustRawMaterialService::class);

        $service->execute($material, $this->actor, [
            'adjustment_type' => 'deduct',
            'quantity' => 30,
            'reference' => 'Spillage',
        ]);

        $this->assertSame('70.00', (string) $material->fresh()->quantity_in_stock);

        $event = DomainEvent::where('event_type', 'raw_material.adjusted')->latest()->first();
        $this->assertSame('deduct', $event->payload['adjustment_type']);
        $this->assertSame(-30.0, $event->payload['quantity_delta']);
    }

    public function test_set_mode_resets_to_absolute_value(): void
    {
        $material = $this->makeMaterialWithStock(100, 5);
        $service = $this->app->make(AdjustRawMaterialService::class);

        $service->execute($material, $this->actor, [
            'adjustment_type' => 'set',
            'quantity' => 75,
            'reference' => 'Stock count',
        ]);

        $this->assertSame('75.00', (string) $material->fresh()->quantity_in_stock);

        $event = DomainEvent::where('event_type', 'raw_material.adjusted')->latest()->first();
        $this->assertSame('set', $event->payload['adjustment_type']);
        $this->assertSame(-25.0, $event->payload['quantity_delta']);
        $this->assertSame(75.0, $event->payload['new_quantity_on_hand']);
    }

    public function test_deduct_more_than_available_aborts(): void
    {
        $material = $this->makeMaterialWithStock(50, 5);
        $service = $this->app->make(AdjustRawMaterialService::class);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('Cannot deduct more stock than is currently available.');

        $service->execute($material, $this->actor, [
            'adjustment_type' => 'deduct',
            'quantity' => 80,
        ]);
    }

    public function test_unknown_adjustment_type_throws(): void
    {
        $material = $this->makeMaterialWithStock(50, 5);
        $service = $this->app->make(AdjustRawMaterialService::class);

        $this->expectException(\InvalidArgumentException::class);
        $service->execute($material, $this->actor, [
            'adjustment_type' => 'nonsense',
            'quantity' => 10,
        ]);
    }
}
