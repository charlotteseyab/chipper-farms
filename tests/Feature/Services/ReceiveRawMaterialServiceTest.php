<?php

namespace Tests\Feature\Services;

use App\Models\DomainEvent;
use App\Models\Organization;
use App\Models\RawMaterial;
use App\Models\Role;
use App\Models\User;
use App\Services\Inventory\ReceiveRawMaterialService;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiveRawMaterialServiceTest extends TestCase
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

    protected function makeMaterial(): RawMaterial
    {
        return RawMaterial::create([
            'name' => 'Maize',
            'category' => 'grain',
            'unit' => 'kg',
            'quantity_in_stock' => 0,
            'unit_cost' => 0,
            'reorder_level' => 100,
        ]);
    }

    public function test_receive_increments_stock_and_creates_log(): void
    {
        $material = $this->makeMaterial();
        $service = $this->app->make(ReceiveRawMaterialService::class);

        $result = $service->execute($material, $this->actor, [
            'quantity' => 200,
            'unit_cost' => 5.50,
            'reference' => 'GRN-001',
        ]);

        $fresh = $material->fresh();
        $this->assertSame('200.00', (string) $fresh->quantity_in_stock);
        $this->assertSame('5.50', (string) $fresh->unit_cost);

        $this->assertSame('receive', $result['log']->type);
        $this->assertSame('200.00', (string) $result['log']->quantity);
        $this->assertSame('GRN-001', $result['log']->reference);
    }

    public function test_receive_dispatches_raw_material_received_event(): void
    {
        $material = $this->makeMaterial();
        $service = $this->app->make(ReceiveRawMaterialService::class);

        $service->execute($material, $this->actor, [
            'quantity' => 100,
            'unit_cost' => 10,
        ]);

        $event = DomainEvent::where('event_type', 'raw_material.received')->first();

        $this->assertNotNull($event);
        $this->assertSame((int) $material->id, (int) $event->payload['raw_material_id']);
        $this->assertSame(100.0, (float) $event->payload['quantity']);
        $this->assertSame(10.0, (float) $event->payload['unit_cost']);
        $this->assertSame(1000.0, (float) $event->payload['total_cost']);
    }

    public function test_negative_quantity_throws(): void
    {
        $material = $this->makeMaterial();
        $service = $this->app->make(ReceiveRawMaterialService::class);

        $this->expectException(\InvalidArgumentException::class);
        $service->execute($material, $this->actor, [
            'quantity' => -10,
            'unit_cost' => 5,
        ]);
    }

    public function test_two_receipts_accumulate_correctly(): void
    {
        $material = $this->makeMaterial();
        $service = $this->app->make(ReceiveRawMaterialService::class);

        $service->execute($material, $this->actor, ['quantity' => 50, 'unit_cost' => 4]);
        $service->execute($material, $this->actor, ['quantity' => 30, 'unit_cost' => 6]);

        $this->assertSame('80.00', (string) $material->fresh()->quantity_in_stock);
        $this->assertSame(2, DomainEvent::where('event_type', 'raw_material.received')->count());
    }
}
