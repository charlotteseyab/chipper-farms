<?php

namespace Tests\Feature\Procurement\Services;

use App\Models\Counterparty;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\RawMaterial;
use App\Services\Procurement\ReceiveAgainstPurchaseRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class ReceiveAgainstPurchaseRequestServiceTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected ReceiveAgainstPurchaseRequestService $service;
    protected Counterparty $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        $this->supplier = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Supp', 'code' => 'S', 'type' => Counterparty::TYPE_SUPPLIER,
            'currency_code' => 'GHS',
        ]);

        $this->service = $this->app->make(ReceiveAgainstPurchaseRequestService::class);
    }

    public function test_receives_against_approved_pr_advances_quantity_received(): void
    {
        $rm = $this->mkRm();
        $prItem = $this->mkPrItem($rm, 100, 5);

        $result = $this->service->execute(
            prItem: $prItem,
            actor: $this->user,
            data: ['quantity' => 60.0, 'unit_cost' => 5.0],
        );

        $this->assertNotNull($result['log']);
        $prItem->refresh();
        $this->assertEquals('60.0000', $prItem->quantity_received);
    }

    public function test_full_receipt_closes_the_pr_item(): void
    {
        $rm = $this->mkRm();
        $prItem = $this->mkPrItem($rm, 100, 5);

        $this->service->execute(
            prItem: $prItem,
            actor: $this->user,
            data: ['quantity' => 100.0, 'unit_cost' => 5.0],
        );

        $prItem->refresh();
        $this->assertTrue((bool) $prItem->is_receipt_closed);
        $this->assertNotNull($prItem->receipt_closed_at);
    }

    public function test_unapproved_pr_blocked(): void
    {
        $rm = $this->mkRm();
        $prItem = $this->mkPrItem($rm, 100, 5);
        $prItem->purchaseRequest->update(['status' => 'pending']);

        $this->expectException(\DomainException::class);
        $this->service->execute(
            prItem: $prItem,
            actor: $this->user,
            data: ['quantity' => 10.0, 'unit_cost' => 5.0],
        );
    }

    public function test_closed_pr_item_blocked(): void
    {
        $rm = $this->mkRm();
        $prItem = $this->mkPrItem($rm, 100, 5);
        $prItem->update(['is_receipt_closed' => true, 'receipt_closed_at' => now()]);

        $this->expectException(\DomainException::class);
        $this->service->execute(
            prItem: $prItem,
            actor: $this->user,
            data: ['quantity' => 10.0, 'unit_cost' => 5.0],
        );
    }

    public function test_over_receipt_beyond_tolerance_blocked(): void
    {
        $rm = $this->mkRm();
        $prItem = $this->mkPrItem($rm, 100, 5);

        // 50% over receipt — far above default 5% tolerance.
        $this->expectException(\DomainException::class);
        $this->service->execute(
            prItem: $prItem,
            actor: $this->user,
            data: ['quantity' => 150.0, 'unit_cost' => 5.0],
        );
    }

    public function test_over_receipt_allowed_with_override_flag(): void
    {
        $rm = $this->mkRm();
        $prItem = $this->mkPrItem($rm, 100, 5);

        $result = $this->service->execute(
            prItem: $prItem,
            actor: $this->user,
            data: ['quantity' => 150.0, 'unit_cost' => 5.0],
            allowOverReceipt: true,
        );

        $this->assertNotNull($result['log']);
        $prItem->refresh();
        $this->assertEquals('150.0000', $prItem->quantity_received);
    }

    public function test_pr_item_without_raw_material_link_rejected(): void
    {
        $pr = $this->mkPr();
        $prItem = PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'item_name' => 'Service item',
            'description' => 'Free-text service',
            'quantity' => 1,
            'unit_price' => 1000,
            'total_price' => 1000,
            // No raw_material_id
        ]);

        $this->expectException(\DomainException::class);
        $this->service->execute(
            prItem: $prItem,
            actor: $this->user,
            data: ['quantity' => 1.0, 'unit_cost' => 1000.0],
        );
    }

    public function test_pr_without_supplier_rejected(): void
    {
        $rm = $this->mkRm();
        $pr = $this->mkPr();
        $pr->update(['supplier_id' => null]);
        $prItem = PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'item_name' => $rm->name, 'description' => $rm->name,
            'quantity' => 100, 'unit_price' => 5, 'total_price' => 500,
            'raw_material_id' => $rm->id,
        ]);

        $this->expectException(\DomainException::class);
        $this->service->execute(
            prItem: $prItem,
            actor: $this->user,
            data: ['quantity' => 10.0, 'unit_cost' => 5.0],
        );
    }

    // -------- helpers --------

    protected function mkRm(): RawMaterial
    {
        return RawMaterial::create([
            'name' => 'Maize-' . uniqid(),
            'unit' => 'kg', 'quantity_in_stock' => 0, 'unit_cost' => 0, 'reorder_level' => 0,
        ]);
    }

    protected function mkPr(): PurchaseRequest
    {
        return PurchaseRequest::create([
            'request_no' => 'PR-' . uniqid(),
            'request_date' => now()->toDateString(),
            'requested_by' => $this->user->id,
            'status' => 'approved',
            'total_amount' => 500,
            'supplier_id' => $this->supplier->id,
            'organization_id' => $this->org->id,
        ]);
    }

    protected function mkPrItem(RawMaterial $rm, float $qty, float $price): PurchaseRequestItem
    {
        $pr = $this->mkPr();
        return PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'item_name' => $rm->name,
            'description' => $rm->name,
            'quantity' => $qty,
            'unit_price' => $price,
            'total_price' => $qty * $price,
            'raw_material_id' => $rm->id,
            'unit' => $rm->unit,
        ]);
    }
}
