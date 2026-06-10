<?php

namespace Tests\Feature\Inventory\Controllers;

use App\Models\Counterparty;
use App\Models\Finance\GrIrEntry;
use App\Models\Permission;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\RawMaterial;
use App\Models\RawMaterialStockLog;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class GrIrAgeingControllerTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();
        $this->grantPermission('view_gr_ir_ageing');
    }

    public function test_index_groups_by_supplier_and_age_buckets(): void
    {
        $supplier = $this->makeSupplier('Acme Feed');
        $rm = $this->makeRawMaterial();

        // Plant three outstanding entries at different ages
        $this->makeGrIrEntry($supplier, $rm, 100.0, 5.0, CarbonImmutable::now()->subDays(3));    // 0-7
        $this->makeGrIrEntry($supplier, $rm,  50.0, 8.0, CarbonImmutable::now()->subDays(45));   // 31-60
        $this->makeGrIrEntry($supplier, $rm,  20.0, 10.0, CarbonImmutable::now()->subDays(120)); // 90+

        $resp = $this->actingAs($this->user)->get(route('finance.inventory.gr-ir-ageing.index'));

        $resp->assertOk();
        $resp->assertSee('Acme Feed');
        // 3 entries, total 100*5 + 50*8 + 20*10 = 500 + 400 + 200 = 1100
        $resp->assertSee('1,100.00');
    }

    public function test_index_excludes_cleared_entries(): void
    {
        $supplier = $this->makeSupplier('Acme Feed');
        $rm = $this->makeRawMaterial();

        $entry = $this->makeGrIrEntry($supplier, $rm, 100.0, 5.0, CarbonImmutable::now()->subDays(2));
        $entry->update(['status' => GrIrEntry::STATUS_CLEARED]);

        $resp = $this->actingAs($this->user)->get(route('finance.inventory.gr-ir-ageing.index'));

        $resp->assertOk();
        $resp->assertSee('No outstanding GR/IR balances.');
    }

    public function test_show_lists_entries_for_supplier(): void
    {
        $supplier = $this->makeSupplier('Acme Feed');
        $rm = $this->makeRawMaterial();

        $this->makeGrIrEntry($supplier, $rm, 100.0, 5.0, CarbonImmutable::now()->subDays(7));

        $resp = $this->actingAs($this->user)->get(route('finance.inventory.gr-ir-ageing.show', $supplier->id));

        $resp->assertOk();
        $resp->assertSee('Acme Feed');
        $resp->assertSee($rm->name);
    }

    public function test_show_404_when_no_entries(): void
    {
        $supplier = $this->makeSupplier('Empty Supplier');

        $resp = $this->actingAs($this->user)->get(route('finance.inventory.gr-ir-ageing.show', $supplier->id));

        $resp->assertNotFound();
    }

    public function test_index_forbidden_without_permission(): void
    {
        $this->user->role()->first()?->permissions()->detach();

        $resp = $this->actingAs($this->user)->get(route('finance.inventory.gr-ir-ageing.index'));

        $resp->assertForbidden();
    }

    // -------- helpers --------

    protected function makeSupplier(string $name): Counterparty
    {
        return Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => $name,
            'code' => 'S-' . substr(uniqid(), -6),
            'type' => Counterparty::TYPE_SUPPLIER,
            'currency_code' => 'GHS',
        ]);
    }

    protected function makeRawMaterial(): RawMaterial
    {
        return RawMaterial::create([
            'name' => 'Maize-' . uniqid(),
            'unit' => 'kg',
            'quantity_in_stock' => 0,
            'unit_cost' => 0,
            'reorder_level' => 0,
        ]);
    }

    protected function makeGrIrEntry(Counterparty $supplier, RawMaterial $rm, float $qty, float $unitCost, CarbonImmutable $when): GrIrEntry
    {
        // Need a PR + PR item + stock log to satisfy FKs.
        $pr = PurchaseRequest::create([
            'request_no' => 'PR-' . uniqid(),
            'request_date' => $when->toDateString(),
            'requested_by' => $this->user->id,
            'status' => 'approved',
            'total_amount' => $qty * $unitCost,
            'supplier_id' => $supplier->id,
            'organization_id' => $this->org->id,
        ]);

        $prItem = PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'item_name' => $rm->name,
            'description' => $rm->name,
            'quantity' => $qty,
            'unit_price' => $unitCost,
            'total_price' => $qty * $unitCost,
            'raw_material_id' => $rm->id,
        ]);

        $log = RawMaterialStockLog::create([
            'raw_material_id' => $rm->id,
            'type' => 'receive',
            'quantity' => $qty,
            'unit_cost' => $unitCost,
            'performed_by' => $this->user->id,
            'organization_id' => $this->org->id,
            'supplier_id' => $supplier->id,
            'purchase_request_item_id' => $prItem->id,
            'created_at' => $when,
            'updated_at' => $when,
        ]);

        return GrIrEntry::create([
            'organization_id' => $this->org->id,
            'stock_log_id' => $log->id,
            'purchase_request_item_id' => $prItem->id,
            'supplier_id' => $supplier->id,
            'raw_material_id' => $rm->id,
            'quantity' => $qty,
            'receipt_unit_cost' => $unitCost,
            'receipt_amount' => $qty * $unitCost,
            'status' => GrIrEntry::STATUS_OUTSTANDING,
            'occurred_at' => $when,
        ]);
    }

    protected function grantPermission(string $code): void
    {
        $perm = Permission::firstOrCreate(['name' => $code], [
            'name' => $code, 'description' => 'test', 'module' => 'finance',
        ]);
        $this->user->role()->first()?->permissions()->syncWithoutDetaching([$perm->id]);
    }
}
