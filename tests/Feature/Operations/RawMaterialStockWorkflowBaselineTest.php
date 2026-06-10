<?php

namespace Tests\Feature\Operations;

use App\Models\DomainEvent;
use App\Models\Counterparty;
use App\Models\Organization;
use App\Models\RawMaterial;
use App\Models\RawMaterialStockLog;
use App\Models\Role;
use App\Models\User;
use App\Services\Inventory\ReceiveRawMaterialService;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 0.6 — regression baseline for raw material stock flows.
 *
 * Locks in:
 *   - stock receipt updates quantity_in_stock and writes a stock log
 *   - stock adjustment (add / deduct / set) behaves correctly
 *   - deduct beyond stock aborts with 422
 *   - every receive / adjust emits the corresponding domain event
 *
 * These are the flows Phase 0.7 (weighted-average valuation) will hook into.
 * The tests are deliberately strict so the valuation rework cannot silently
 * change the recorded stock quantities.
 */
class RawMaterialStockWorkflowBaselineTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected User $admin;
    protected Counterparty $supplier;

    protected function setUp(): void
    {
        parent::setUp();
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
            'type' => Counterparty::TYPE_SUPPLIER,
            'code' => 'SUP-T',
            'name' => 'Test Supplier',
            'currency_code' => 'GHS',
            'is_active' => true,
        ]);
    }

    protected function makeMaterial(float $qty = 0.0, float $unitCost = 10.00): RawMaterial
    {
        $material = RawMaterial::create([
            'name' => 'Maize bran',
            'category' => 'grain',
            'unit' => 'kg',
            'quantity_in_stock' => 0,
            'unit_cost' => 0,
            'reorder_level' => 100,
        ]);

        if ($qty > 0) {
            $this->app->make(ReceiveRawMaterialService::class)->execute(
                $material,
                $this->admin,
                [
                    'quantity' => $qty,
                    'unit_cost' => $unitCost,
                    'reference' => 'Opening Stock',
                    'supplier_id' => $this->supplier->id,
                    'receipt_reason' => 'opening_stock',
                ],
            );
        } else {
            $material->update(['unit_cost' => $unitCost]);
        }

        return $material->fresh();
    }

    public function test_receiving_stock_increments_quantity_and_writes_log(): void
    {
        $material = $this->makeMaterial(qty: 50);

        $this->actingAs($this->admin)
            ->post(route('raw-materials.receive', $material), [
                'quantity' => 30,
                'unit_cost' => 12,
                'reference' => 'GRN-001',
                'notes' => 'Weekly restock',
                'supplier_id' => $this->supplier->id,
                'receipt_reason' => 'purchase',
            ])
            ->assertRedirect();

        $material->refresh();
        $this->assertSame(80.0, (float) $material->quantity_in_stock);
        $this->assertSame(12.0, (float) $material->unit_cost);

        $log = RawMaterialStockLog::where('raw_material_id', $material->id)
            ->where('type', 'receive')
            ->where('reference', 'GRN-001')
            ->latest()
            ->first();

        $this->assertNotNull($log);
        $this->assertSame(30.0, (float) $log->quantity);
        $this->assertSame(12.0, (float) $log->unit_cost);
        $this->assertSame('GRN-001', $log->reference);
    }

    public function test_receiving_stock_emits_raw_material_received_event(): void
    {
        $material = $this->makeMaterial(qty: 0);

        $this->actingAs($this->admin)
            ->post(route('raw-materials.receive', $material), [
                'quantity' => 100,
                'unit_cost' => 8.50,
                'reference' => 'GRN-002',
                'supplier_id' => $this->supplier->id,
                'receipt_reason' => 'purchase',
            ])
            ->assertRedirect();

        $event = DomainEvent::where('event_type', 'raw_material.received')
            ->latest()->first();

        $this->assertNotNull($event);
        $this->assertSame(100.0, (float) $event->payload['quantity']);
        $this->assertSame(8.50, (float) $event->payload['unit_cost']);
        $this->assertSame(850.0, (float) $event->payload['total_cost']);
        $this->assertSame('GRN-002', $event->payload['reference']);
    }

    public function test_adjusting_stock_add_increases_quantity(): void
    {
        $material = $this->makeMaterial(qty: 50);

        $this->actingAs($this->admin)
            ->post(route('raw-materials.adjust', $material), [
                'adjustment_type' => 'add',
                'quantity' => 15,
                'notes' => 'count correction',
            ])
            ->assertRedirect();

        $this->assertSame(65.0, (float) $material->fresh()->quantity_in_stock);
    }

    public function test_adjusting_stock_deduct_decreases_quantity(): void
    {
        $material = $this->makeMaterial(qty: 50);

        $this->actingAs($this->admin)
            ->post(route('raw-materials.adjust', $material), [
                'adjustment_type' => 'deduct',
                'quantity' => 10,
                'notes' => 'spillage',
            ])
            ->assertRedirect();

        $this->assertSame(40.0, (float) $material->fresh()->quantity_in_stock);
    }

    public function test_adjusting_stock_set_replaces_quantity(): void
    {
        $material = $this->makeMaterial(qty: 50);

        $this->actingAs($this->admin)
            ->post(route('raw-materials.adjust', $material), [
                'adjustment_type' => 'set',
                'quantity' => 73,
                'notes' => 'physical count',
            ])
            ->assertRedirect();

        $this->assertSame(73.0, (float) $material->fresh()->quantity_in_stock);
    }

    public function test_cannot_deduct_more_than_available(): void
    {
        $material = $this->makeMaterial(qty: 20);

        $this->actingAs($this->admin)
            ->post(route('raw-materials.adjust', $material), [
                'adjustment_type' => 'deduct',
                'quantity' => 50,
            ])
            ->assertStatus(422);

        // No state change.
        $this->assertSame(20.0, (float) $material->fresh()->quantity_in_stock);
    }

    public function test_adjusting_stock_emits_raw_material_adjusted_event_with_signed_delta(): void
    {
        $material = $this->makeMaterial(qty: 50);

        $this->actingAs($this->admin)
            ->post(route('raw-materials.adjust', $material), [
                'adjustment_type' => 'deduct',
                'quantity' => 5,
                'notes' => 'breakage',
            ])
            ->assertRedirect();

        $event = DomainEvent::where('event_type', 'raw_material.adjusted')
            ->latest()->first();

        $this->assertNotNull($event);
        $this->assertSame('deduct', $event->payload['adjustment_type']);
        // Delta is negative for a deduct.
        $this->assertSame(-5.0, (float) $event->payload['quantity_delta']);
        $this->assertSame(45.0, (float) $event->payload['new_quantity_on_hand']);
    }
}
