<?php

namespace Tests\Feature\Finance\VendorInvoicing;

use App\Models\Counterparty;
use App\Models\Finance\Account;
use App\Models\Finance\GrIrEntry;
use App\Models\Finance\InventoryAccountMapping;
use App\Models\Finance\JournalLine;
use App\Models\Finance\SupplierCategory;
use App\Models\Finance\VendorInvoice;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\RawMaterial;
use App\Models\RawMaterialStockLog;
use App\Models\TaxCode;
use App\Services\Finance\VendorInvoicing\VendorInvoiceLineDraft;
use App\Services\Finance\VendorInvoicing\VendorInvoicePostingService;
use App\Services\Finance\VendorInvoicing\VendorInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * Phase 4 — vendor invoice posting verifies:
 *   - Standard (non-GR/IR) posting unchanged
 *   - PR-linked line with outstanding GR/IR → clears at receipt amount + PPV
 *   - Recoverable VAT no longer throws ArgumentCountError
 *   - Over-tolerance variance throws DomainException
 *   - Pro-rata clearing across multiple GR/IR entries
 *   - Favourable variance routes Cr PPV correctly
 */
class VendorInvoiceCutoverTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected VendorInvoiceService $service;
    protected VendorInvoicePostingService $postingService;
    protected Counterparty $supplier;
    protected Account $invAcct;
    protected Account $grIrAcct;
    protected Account $apControl;
    protected Account $inputVat;
    protected Account $ppvAcct;
    protected Account $suspenseAcct;
    protected Account $sundryAcct;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        $this->invAcct      = $this->mkAcct('1210', 'RM Inventory',  Account::TYPE_ASSET);
        $this->grIrAcct     = $this->mkAcct('2050', 'GR/IR',          Account::TYPE_LIABILITY);
        $this->apControl    = $this->mkAcct('2010', 'AP Control',     Account::TYPE_LIABILITY);
        $this->inputVat     = $this->mkAcct('2211', 'Input VAT',      Account::TYPE_LIABILITY);
        $this->ppvAcct      = $this->mkAcct('5310', 'PPV',            Account::TYPE_EXPENSE);
        $this->suspenseAcct = $this->mkAcct('2055', 'Suspense',       Account::TYPE_LIABILITY);
        $this->sundryAcct   = $this->mkAcct('6990', 'Sundry Expense', Account::TYPE_EXPENSE);

        InventoryAccountMapping::create([
            'organization_id'      => $this->org->id,
            'item_kind'            => 'raw_material',
            'category'             => InventoryAccountMapping::DEFAULT_CATEGORY_TOKEN,
            'is_default'           => true,
            'inventory_account_id' => $this->invAcct->id,
            'gr_ir_account_id'     => $this->grIrAcct->id,
            'suspense_account_id'  => $this->suspenseAcct->id,
            'ppv_account_id'       => $this->ppvAcct->id,
        ]);

        $category = SupplierCategory::create([
            'organization_id' => $this->org->id,
            'code' => 'FEED', 'name' => 'Feed Suppliers',
            'default_expense_account_id' => $this->sundryAcct->id,
            'ap_account_id' => $this->apControl->id,
            'payment_terms_days' => 30,
            'default_withholding_percent' => 0,
            'is_active' => true,
        ]);

        $this->supplier = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Feed Supplier Ltd',
            'code' => 'S-FEED', 'type' => Counterparty::TYPE_SUPPLIER, 'currency_code' => 'GHS',
            'supplier_category_id' => $category->id,
        ]);

        $this->service        = $this->app->make(VendorInvoiceService::class);
        $this->postingService = $this->app->make(VendorInvoicePostingService::class);
    }

    public function test_pr_linked_line_with_zero_variance_clears_gr_ir_cleanly(): void
    {
        // 100 kg @ 5.00 received.
        [$pr, $prItem, $rm, $entry] = $this->setupPRWithOutstandingGrIr(100.0, 5.0);

        // Now invoice it for exactly 100 @ 5.00 = 500.00.
        $invoice = $this->createInvoice($prItem, 100.0, 5.0, 0.0);

        $this->postingService->post($invoice, $this->user);

        $invoice->refresh();
        $this->assertEquals(VendorInvoice::STATUS_POSTED, $invoice->status);

        // GR/IR entry cleared.
        $entry->refresh();
        $this->assertEquals(GrIrEntry::STATUS_CLEARED, $entry->status);
        $this->assertEquals('500.0000', $entry->cleared_amount);
        $this->assertEquals('0.0000',   $entry->variance_amount);

        // GL lines: Dr GR/IR 500, Cr AP 500. No PPV.
        $lines = JournalLine::query()->whereIn('journal_id', $invoice->journalBatch->journals()->pluck('id'))->get();
        $this->assertCount(2, $lines);

        $drGrIr = $lines->firstWhere('account_id', $this->grIrAcct->id);
        $crAp = $lines->firstWhere('account_id', $this->apControl->id);
        $this->assertEquals('500.00', $drGrIr->debit);
        $this->assertEquals('500.00', $crAp->credit);
    }

    public function test_pr_linked_line_with_unfavourable_variance_posts_ppv_debit(): void
    {
        // Receipt 100 @ 5.00 = 500.
        [$pr, $prItem, $rm, $entry] = $this->setupPRWithOutstandingGrIr(100.0, 5.0);

        // Invoice 100 @ 5.05 = 505. Variance = +5 (1% — within default 2% tolerance).
        $invoice = $this->createInvoice($prItem, 100.0, 5.05, 0.0);
        $this->postingService->post($invoice, $this->user);

        $entry->refresh();
        $this->assertEquals(GrIrEntry::STATUS_CLEARED, $entry->status);
        $this->assertEquals('505.0000', $entry->cleared_amount);
        $this->assertEquals('5.0000',   $entry->variance_amount);

        $lines = JournalLine::query()->whereIn('journal_id', $invoice->journalBatch->journals()->pluck('id'))->get();
        // Dr GR/IR 500, Dr PPV 5, Cr AP 505 → 3 lines.
        $this->assertCount(3, $lines);

        $drPpv = $lines->firstWhere('account_id', $this->ppvAcct->id);
        $this->assertNotNull($drPpv);
        $this->assertEquals('5.00', $drPpv->debit);
    }

    public function test_pr_linked_line_with_favourable_variance_credits_ppv(): void
    {
        // Receipt 100 @ 5.00 = 500.
        [$pr, $prItem, $rm, $entry] = $this->setupPRWithOutstandingGrIr(100.0, 5.0);

        // Invoice 100 @ 4.95 = 495. Variance = -5 (favourable).
        $invoice = $this->createInvoice($prItem, 100.0, 4.95, 0.0);
        $this->postingService->post($invoice, $this->user);

        $entry->refresh();
        $this->assertEquals('495.0000', $entry->cleared_amount);
        $this->assertEquals('-5.0000',  $entry->variance_amount);

        $lines = JournalLine::query()->whereIn('journal_id', $invoice->journalBatch->journals()->pluck('id'))->get();
        $crPpv = $lines->firstWhere('account_id', $this->ppvAcct->id);
        $this->assertNotNull($crPpv);
        $this->assertEquals('5.00', $crPpv->credit);
    }

    public function test_over_tolerance_variance_blocks_post(): void
    {
        // Receipt 100 @ 5.00 = 500.
        [$pr, $prItem, $rm, $entry] = $this->setupPRWithOutstandingGrIr(100.0, 5.0);

        // Invoice 100 @ 6.00 = 600 (20% variance — far above default 2%).
        $invoice = $this->createInvoice($prItem, 100.0, 6.0, 0.0);

        $this->expectException(\DomainException::class);
        $this->postingService->post($invoice, $this->user);
    }

    public function test_recoverable_vat_line_no_longer_throws_argument_count_error(): void
    {
        // The Phase 3b bug was resolveInputVatAccountId() called with TaxCode but
        // declared as taking no args. Phase 4 fixes the signature. Smoke test
        // that the recoverable-VAT path posts cleanly.
        [$pr, $prItem, $rm, $entry] = $this->setupPRWithOutstandingGrIr(100.0, 5.0);

        $vatCode = TaxCode::create([
            'organization_id' => $this->org->id,
            'code' => 'VAT15', 'name' => 'VAT 15%',
            'rate_type' => 'percent', 'rate' => 15, 'applies_to' => 'purchase',
            'is_recoverable' => true, 'is_withholding' => false,
            'gl_account_hint' => '2211',
            'is_active' => true,
        ]);

        $invoice = $this->createInvoice($prItem, 100.0, 5.00, 15.0, $vatCode);
        $this->postingService->post($invoice, $this->user);

        $invoice->refresh();
        $this->assertEquals(VendorInvoice::STATUS_POSTED, $invoice->status);

        $lines = JournalLine::query()->whereIn('journal_id', $invoice->journalBatch->journals()->pluck('id'))->get();
        $drVat = $lines->firstWhere('account_id', $this->inputVat->id);
        $this->assertNotNull($drVat, 'Recoverable VAT line should be debited to 2211');
        $this->assertEquals('75.00', $drVat->debit);  // 15% of 500
    }

    public function test_pro_rata_clearing_across_multiple_gr_ir_entries(): void
    {
        // Two separate receipts on the same PR line, then ONE invoice.
        $rm = $this->mkRm();
        $pr = $this->mkPr();
        $prItem = PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'item_name' => $rm->name, 'description' => $rm->name,
            'quantity' => 100, 'unit_price' => 5, 'total_price' => 500,
            'raw_material_id' => $rm->id,
        ]);

        $e1 = $this->mkGrIrEntry($prItem, $rm, qty: 40, unitCost: 5.0);   // 200
        $e2 = $this->mkGrIrEntry($prItem, $rm, qty: 60, unitCost: 5.0);   // 300

        // Invoice 100 @ 5.05 → billed 505. Total receipt = 500. Variance = +5.
        // Pro-rata: entry 1 gets 200/500 = 40% × 505 = 202; entry 2 gets the rest = 303.
        $invoice = $this->createInvoice($prItem, 100.0, 5.05, 0.0);
        $this->postingService->post($invoice, $this->user);

        $e1->refresh();
        $e2->refresh();

        $this->assertEquals(GrIrEntry::STATUS_CLEARED, $e1->status);
        $this->assertEquals(GrIrEntry::STATUS_CLEARED, $e2->status);
        $this->assertEquals('202.0000', $e1->cleared_amount);
        $this->assertEquals('303.0000', $e2->cleared_amount);

        // Sum should be exact 505 (last entry absorbs rounding).
        $total = bcadd($e1->cleared_amount, $e2->cleared_amount, 4);
        $this->assertEquals('505.0000', $total);
    }

    // -------- helpers --------

    /** @return array{0:PurchaseRequest,1:PurchaseRequestItem,2:RawMaterial,3:GrIrEntry} */
    protected function setupPRWithOutstandingGrIr(float $qty, float $unitCost): array
    {
        $rm = $this->mkRm();
        $pr = $this->mkPr();
        $prItem = PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'item_name' => $rm->name, 'description' => $rm->name,
            'quantity' => $qty, 'unit_price' => $unitCost, 'total_price' => $qty * $unitCost,
            'raw_material_id' => $rm->id,
        ]);

        $entry = $this->mkGrIrEntry($prItem, $rm, $qty, $unitCost);
        return [$pr, $prItem, $rm, $entry];
    }

    protected function mkGrIrEntry(PurchaseRequestItem $prItem, RawMaterial $rm, float $qty, float $unitCost): GrIrEntry
    {
        $log = RawMaterialStockLog::create([
            'raw_material_id' => $rm->id, 'type' => 'receive',
            'quantity' => $qty, 'unit_cost' => $unitCost,
            'performed_by' => $this->user->id, 'organization_id' => $this->org->id,
            'supplier_id' => $this->supplier->id,
            'purchase_request_item_id' => $prItem->id,
        ]);

        return GrIrEntry::create([
            'organization_id' => $this->org->id,
            'stock_log_id' => $log->id,
            'purchase_request_item_id' => $prItem->id,
            'supplier_id' => $this->supplier->id,
            'raw_material_id' => $rm->id,
            'quantity' => $qty,
            'receipt_unit_cost' => $unitCost,
            'receipt_amount' => $qty * $unitCost,
            'status' => GrIrEntry::STATUS_OUTSTANDING,
            'occurred_at' => now(),
        ]);
    }

    protected function mkRm(): RawMaterial
    {
        return RawMaterial::create([
            'name' => 'Maize-' . uniqid(), 'unit' => 'kg',
            'quantity_in_stock' => 0, 'unit_cost' => 0, 'reorder_level' => 0,
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

    protected function createInvoice(
        PurchaseRequestItem $prItem,
        float $billedQty,
        float $billedUnitCost,
        float $taxRate = 0.0,
        ?TaxCode $taxCode = null,
    ): VendorInvoice {
        return $this->service->createDraft(
            supplier: $this->supplier,
            headerData: [
                'invoice_date' => now()->toDateString(),
                'currency_code' => 'GHS',
            ],
            lineDrafts: [
                new VendorInvoiceLineDraft(
                    description: $prItem->description,
                    quantity:    number_format($billedQty, 4, '.', ''),
                    unitCost:    number_format($billedUnitCost, 6, '.', ''),
                    rawMaterialId: $prItem->raw_material_id,
                    purchaseRequestItemId: $prItem->id,
                    taxRate: number_format($taxRate, 4, '.', ''),
                    taxCodeId: $taxCode?->id,
                    accountId: $this->invAcct->id,
                ),
            ],
            createdBy: $this->user,
        );
    }

    protected function mkAcct(string $code, string $name, string $type): Account
    {
        return Account::firstOrCreate(
            ['organization_id' => $this->org->id, 'code' => $code],
            ['name' => $name, 'account_type' => $type, 'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => true],
        );
    }
}
