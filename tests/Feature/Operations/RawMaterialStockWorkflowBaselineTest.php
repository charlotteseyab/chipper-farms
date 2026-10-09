<?php

namespace Tests\Feature\Operations;

use App\Models\DomainEvent;
use App\Models\Counterparty;
use App\Models\Organization;
use App\Models\RawMaterial;
use App\Models\RawMaterialStockLog;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\Inventory\InventoryService;
use App\Services\Inventory\ReceiveRawMaterialService;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\FarmOperationsPermissionsSeeder;
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

    public function test_opening_stock_returns_a_form_error_when_supplier_is_missing(): void
    {
        $this->actingAs($this->admin)->post(route('raw-materials.store'), [
            'name' => 'Opening Maize',
            'category' => 'grain',
            'unit' => 'kg',
            'quantity_in_stock' => 100,
            'unit_cost' => 4,
            'reorder_level' => 20,
        ])->assertRedirect()
            ->assertSessionHasErrors([
                'opening_supplier_id' => 'Select the supplier responsible for the opening stock.',
            ]);

        $this->assertDatabaseMissing('raw_materials', ['name' => 'Opening Maize']);
    }

    public function test_opening_stock_is_created_with_this_farms_supplier(): void
    {
        $this->actingAs($this->admin)->post(route('raw-materials.store'), [
            'name' => 'Opening Maize',
            'category' => 'grain',
            'unit' => 'kg',
            'quantity_in_stock' => 100,
            'unit_cost' => 4,
            'reorder_level' => 20,
            'opening_supplier_id' => $this->supplier->id,
        ])->assertRedirect(route('raw-materials.index'))
            ->assertSessionHasNoErrors();

        $material = RawMaterial::where('name', 'Opening Maize')->firstOrFail();
        $this->assertSame(100.0, (float) $material->quantity_in_stock);
        $this->assertDatabaseHas('raw_material_stock_logs', [
            'raw_material_id' => $material->id,
            'organization_id' => $this->org->id,
            'supplier_id' => $this->supplier->id,
            'receipt_reason' => 'opening_stock',
        ]);
    }

    public function test_opening_stock_rejects_another_farms_supplier(): void
    {
        $other = Organization::create(['slug' => 'other-farm', 'name' => 'Other Farm']);
        $otherSupplier = Counterparty::withoutGlobalScopes()->create([
            'organization_id' => $other->id,
            'type' => Counterparty::TYPE_SUPPLIER,
            'code' => 'SUP-OTHER',
            'name' => 'Other Supplier',
            'currency_code' => 'GHS',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)->post(route('raw-materials.store'), [
            'name' => 'Private Opening Stock',
            'unit' => 'kg',
            'quantity_in_stock' => 50,
            'unit_cost' => 5,
            'opening_supplier_id' => $otherSupplier->id,
        ])->assertSessionHasErrors('opening_supplier_id');

        $this->assertDatabaseMissing('raw_materials', ['name' => 'Private Opening Stock']);
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

    public function test_adjustment_requires_a_reason_without_changing_stock(): void
    {
        $material = $this->makeMaterial(qty: 20);
        $this->actingAs($this->admin)->post(route('raw-materials.adjust', $material), ['adjustment_type' => 'deduct', 'quantity' => 1])->assertSessionHasErrors('notes');
        $this->assertSame(20.0, (float) $material->fresh()->quantity_in_stock);
    }

    public function test_cannot_deduct_more_than_available(): void
    {
        $material = $this->makeMaterial(qty: 20);

        $this->actingAs($this->admin)
            ->post(route('raw-materials.adjust', $material), [
                'adjustment_type' => 'deduct',
                'quantity' => 50,
                'notes' => 'Physical stock count correction',
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

    public function test_inventory_summary_cards_use_the_same_stock_rules_as_the_alerts(): void
    {
        // Stock enters through the ledger; the value is ledger quantity × average cost.
        RawMaterial::create(['name' => 'Empty', 'unit' => 'kg', 'quantity_in_stock' => 0, 'unit_cost' => 10, 'reorder_level' => 5]);
        $low = RawMaterial::create(['name' => 'Low', 'unit' => 'kg', 'quantity_in_stock' => 0, 'unit_cost' => 2, 'reorder_level' => 5]);
        $healthy = RawMaterial::create(['name' => 'Healthy', 'unit' => 'kg', 'quantity_in_stock' => 0, 'unit_cost' => 3, 'reorder_level' => 5]);
        $inventory = app(InventoryService::class);
        $inventory->recordReceive('raw_material', $low->id, 4, 2, 'stock_log', 990001);
        $inventory->recordReceive('raw_material', $healthy->id, 10, 3, 'stock_log', 990002);
        $this->assertEquals(4, $low->fresh()->quantity_in_stock, 'The ledger mirrors its quantity onto the material');

        $response = $this->actingAs($this->admin)->get(route('raw-materials.index'))->assertOk();

        $this->assertEquals([
            'total_materials' => 3,
            'low_stock' => 1,
            'out_of_stock' => 1,
            'stock_value' => 38.0,
        ], array_map(fn ($value) => is_numeric($value) ? $value + 0 : $value, $response->viewData('stats')));
        $this->assertCount(2, $response->viewData('lowStockAlerts'));
    }

    public function test_stock_status_filters_match_the_values_sent_by_the_page(): void
    {
        RawMaterial::create(['name' => 'Empty', 'unit' => 'kg', 'quantity_in_stock' => 0, 'unit_cost' => 1, 'reorder_level' => 5]);
        RawMaterial::create(['name' => 'Low', 'unit' => 'kg', 'quantity_in_stock' => 4, 'unit_cost' => 1, 'reorder_level' => 5]);
        RawMaterial::create(['name' => 'Healthy', 'unit' => 'kg', 'quantity_in_stock' => 10, 'unit_cost' => 1, 'reorder_level' => 5]);

        $out = $this->actingAs($this->admin)->get(route('raw-materials.index', ['status' => 'out_of_stock']))->viewData('rawMaterials');
        $low = $this->actingAs($this->admin)->get(route('raw-materials.index', ['status' => 'low_stock']))->viewData('rawMaterials');
        $in = $this->actingAs($this->admin)->get(route('raw-materials.index', ['status' => 'in_stock']))->viewData('rawMaterials');

        $this->assertSame(['Empty'], $out->pluck('name')->all());
        $this->assertSame(['Low'], $low->pluck('name')->all());
        $this->assertSame(['Healthy'], $in->pluck('name')->all());
    }
}
