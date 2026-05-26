<?php

namespace Tests\Feature\Finance\VendorInvoicing;

use App\Models\Counterparty;
use App\Models\Finance\Account;
use App\Models\Finance\SupplierCategory;
use App\Models\Finance\VendorInvoice;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\RawMaterial;
use App\Models\RawMaterialStockLog;
use App\Services\Finance\VendorInvoicing\ThreeWayMatchingService;
use App\Services\Finance\VendorInvoicing\VendorInvoiceLineDraft;
use App\Services\Finance\VendorInvoicing\VendorInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class ThreeWayMatchingServiceTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected ThreeWayMatchingService $service;
    protected VendorInvoiceService $viService;
    protected Counterparty $supplier;
    protected Account $expense;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        $this->expense = Account::firstOrCreate(
            ['organization_id' => $this->org->id, 'code' => '6020'],
            ['name' => 'Expense', 'account_type' => Account::TYPE_EXPENSE, 'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => true],
        );

        $category = SupplierCategory::create([
            'organization_id' => $this->org->id, 'code' => 'TEST', 'name' => 'Test',
            'default_expense_account_id' => $this->expense->id,
            'payment_terms_days' => 30, 'default_withholding_percent' => 0, 'is_active' => true,
        ]);

        $this->supplier = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Supp', 'code' => 'S', 'type' => Counterparty::TYPE_SUPPLIER,
            'currency_code' => 'GHS', 'supplier_category_id' => $category->id,
        ]);

        $this->service   = $this->app->make(ThreeWayMatchingService::class);
        $this->viService = $this->app->make(VendorInvoiceService::class);
    }

    public function test_no_pr_linked_lines_returns_null_status(): void
    {
        $invoice = $this->createInvoice(prItem: null, billedQty: 10, billedPrice: 5);

        $result = $this->service->evaluate($invoice);
        $this->assertNull($result['match_status']);
        $this->assertEmpty($result['match_variances']);
    }

    public function test_perfect_match_returns_clean(): void
    {
        $rm = $this->mkRm();
        $prItem = $this->mkPrItem($rm, qty: 10, price: 5);
        $this->mkReceipt($rm, $prItem, qty: 10);

        $invoice = $this->createInvoice($prItem, billedQty: 10, billedPrice: 5);

        $result = $this->service->evaluate($invoice);
        $this->assertEquals(VendorInvoice::MATCH_CLEAN, $result['match_status']);
        $this->assertEmpty($result['match_variances']);
    }

    public function test_no_receipt_variance(): void
    {
        $rm = $this->mkRm();
        $prItem = $this->mkPrItem($rm, qty: 10, price: 5);
        // no receipt

        $invoice = $this->createInvoice($prItem, billedQty: 10, billedPrice: 5);
        $result = $this->service->evaluate($invoice);

        $this->assertEquals(VendorInvoice::MATCH_VARIANCE, $result['match_status']);
        $types = collect($result['match_variances'])->pluck('type')->all();
        $this->assertContains('no_receipt', $types);
    }

    public function test_qty_pr_mismatch_variance(): void
    {
        $rm = $this->mkRm();
        $prItem = $this->mkPrItem($rm, qty: 10, price: 5);
        $this->mkReceipt($rm, $prItem, qty: 20);  // received matches billed but not PR

        $invoice = $this->createInvoice($prItem, billedQty: 20, billedPrice: 5);
        $result = $this->service->evaluate($invoice);

        $types = collect($result['match_variances'])->pluck('type')->all();
        $this->assertContains('qty_pr_mismatch', $types);
    }

    public function test_price_pr_mismatch_variance(): void
    {
        $rm = $this->mkRm();
        $prItem = $this->mkPrItem($rm, qty: 10, price: 5);
        $this->mkReceipt($rm, $prItem, qty: 10);

        // Bill at 7 — PR was 5. 40% variance.
        $invoice = $this->createInvoice($prItem, billedQty: 10, billedPrice: 7);
        $result = $this->service->evaluate($invoice);

        $types = collect($result['match_variances'])->pluck('type')->all();
        $this->assertContains('price_pr_mismatch', $types);
    }

    public function test_qty_receipt_mismatch_variance(): void
    {
        $rm = $this->mkRm();
        $prItem = $this->mkPrItem($rm, qty: 10, price: 5);
        $this->mkReceipt($rm, $prItem, qty: 10);  // matches PR

        $invoice = $this->createInvoice($prItem, billedQty: 12, billedPrice: 5);  // bills 12 — 20% over receipt
        $result = $this->service->evaluate($invoice);

        $types = collect($result['match_variances'])->pluck('type')->all();
        $this->assertContains('qty_receipt_mismatch', $types);
    }

    public function test_raw_material_mismatch_variance(): void
    {
        $rm1 = $this->mkRm('Maize');
        $rm2 = $this->mkRm('Soya');
        $prItem = $this->mkPrItem($rm1, qty: 10, price: 5);
        $this->mkReceipt($rm2, $prItem, qty: 10);  // receipt attributed to wrong material

        $invoice = $this->createInvoice($prItem, billedQty: 10, billedPrice: 5);
        $result = $this->service->evaluate($invoice);

        $types = collect($result['match_variances'])->pluck('type')->all();
        $this->assertContains('raw_material_mismatch', $types);
    }

    public function test_pr_item_missing_variance(): void
    {
        $rm = $this->mkRm();
        $prItem = $this->mkPrItem($rm, qty: 10, price: 5);

        $invoice = $this->createInvoice($prItem, billedQty: 10, billedPrice: 5);
        // Delete the PR item AFTER invoice creation but BEFORE evaluation.
        $prItem->delete();

        $result = $this->service->evaluate($invoice);
        $types = collect($result['match_variances'])->pluck('type')->all();
        $this->assertContains('pr_item_missing', $types);
    }

    // -------- helpers --------

    protected function mkRm(string $name = 'Maize'): RawMaterial
    {
        return RawMaterial::create([
            'name' => $name . '-' . uniqid(), 'unit' => 'kg',
            'quantity_in_stock' => 0, 'unit_cost' => 0, 'reorder_level' => 0,
        ]);
    }

    protected function mkPrItem(RawMaterial $rm, float $qty, float $price): PurchaseRequestItem
    {
        $pr = PurchaseRequest::create([
            'request_no' => 'PR-' . uniqid(),
            'request_date' => now()->toDateString(),
            'requested_by' => $this->user->id,
            'status' => 'approved',
            'total_amount' => $qty * $price,
            'supplier_id' => $this->supplier->id,
            'organization_id' => $this->org->id,
        ]);
        return PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'item_name' => $rm->name, 'description' => $rm->name,
            'quantity' => $qty, 'unit_price' => $price, 'total_price' => $qty * $price,
            'raw_material_id' => $rm->id,
        ]);
    }

    protected function mkReceipt(RawMaterial $rm, PurchaseRequestItem $prItem, float $qty): RawMaterialStockLog
    {
        return RawMaterialStockLog::create([
            'raw_material_id' => $rm->id,
            'type' => 'receive',
            'quantity' => $qty,
            'unit_cost' => 5,
            'performed_by' => $this->user->id,
            'organization_id' => $this->org->id,
            'supplier_id' => $this->supplier->id,
            'purchase_request_item_id' => $prItem->id,
        ]);
    }

    protected function createInvoice(?PurchaseRequestItem $prItem, float $billedQty, float $billedPrice): VendorInvoice
    {
        $line = new VendorInvoiceLineDraft(
            description: $prItem?->description ?? 'free text',
            quantity:    number_format($billedQty, 4, '.', ''),
            unitCost:    number_format($billedPrice, 6, '.', ''),
            rawMaterialId: $prItem?->raw_material_id,
            purchaseRequestItemId: $prItem?->id,
            accountId: $this->expense->id,
        );

        return $this->viService->createDraft(
            supplier: $this->supplier,
            headerData: ['invoice_date' => now()->toDateString(), 'currency_code' => 'GHS'],
            lineDrafts: [$line],
            createdBy: $this->user,
        );
    }
}
