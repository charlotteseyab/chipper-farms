<?php

namespace Tests\Feature\Finance\Subscribers\Inventory;

use App\Domain\Events\Inventory\RawMaterialReceived;
use App\Domain\Events\Support\SourceReference;
use App\Finance\Subscribers\Inventory\RawMaterialReceivedSubscriber;
use App\Models\Counterparty;
use App\Models\DomainEvent;
use App\Models\Finance\Account;
use App\Models\Finance\GrIrEntry;
use App\Models\Finance\InventoryAccountMapping;
use App\Models\Finance\InventoryMovement;
use App\Models\Finance\JournalBatch;
use App\Models\Finance\JournalLine;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\RawMaterial;
use App\Models\RawMaterialStockLog;
use App\Services\DomainEvents\DispatchDomainEvent;
use App\Services\Finance\Inventory\InventoryService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * Phase 4 — verify subscriber posts:
 *   PR-linked   → Dr Inventory / Cr GR/IR + gr_ir_entries row OUTSTANDING.
 *   Non-PR      → Dr Inventory / Cr Inventory Suspense; no gr_ir_entries.
 *   Stock log status updates to 'posted', journal_batch_id set.
 *   Idempotent: re-running subscriber on same domain event is a no-op.
 */
class RawMaterialReceivedSubscriberTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected Account $invAcct;
    protected Account $grIrAcct;
    protected Account $suspenseAcct;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        $this->invAcct      = $this->mkAcct('1210', 'RM Inventory', Account::TYPE_ASSET);
        $this->grIrAcct     = $this->mkAcct('2050', 'GR/IR',         Account::TYPE_LIABILITY);
        $this->suspenseAcct = $this->mkAcct('2055', 'Inv Suspense',  Account::TYPE_LIABILITY);

        InventoryAccountMapping::create([
            'organization_id'      => $this->org->id,
            'item_kind'            => 'raw_material',
            'category'             => InventoryAccountMapping::DEFAULT_CATEGORY_TOKEN,
            'is_default'           => true,
            'inventory_account_id' => $this->invAcct->id,
            'gr_ir_account_id'     => $this->grIrAcct->id,
            'suspense_account_id'  => $this->suspenseAcct->id,
        ]);
    }

    public function test_pr_linked_receipt_posts_to_gr_ir_and_creates_entry(): void
    {
        [$rm, $pr, $prItem, $supplier] = $this->makePRChain();

        // Drive a receipt through the inventory service path so movement + stock log + event all exist.
        $log = $this->makeStockLog($rm, $prItem, $supplier, qty: 50.0, unitCost: 4.0);
        $domainEvent = $this->dispatchReceivedEvent($log, $rm, $pr);

        $subscriber = $this->app->make(RawMaterialReceivedSubscriber::class);
        $subscriber->handle($domainEvent->id);

        $batch = JournalBatch::query()->first();
        $this->assertNotNull($batch);

        // Assert journal lines: Dr 1210, Cr 2050.
        $lines = JournalLine::query()->whereIn('journal_id', $batch->journals()->pluck('id'))->get();
        $this->assertCount(2, $lines);

        $dr = $lines->firstWhere('account_id', $this->invAcct->id);
        $cr = $lines->firstWhere('account_id', $this->grIrAcct->id);
        $this->assertNotNull($dr);
        $this->assertNotNull($cr);
        $this->assertEquals('200.00', $dr->debit);   // 50 * 4
        $this->assertEquals('200.00', $cr->credit);

        // Stock log marked posted.
        $log->refresh();
        $this->assertTrue((bool) $log->is_posted);
        $this->assertEquals('posted', $log->posting_status);
        $this->assertEquals($batch->id, $log->journal_batch_id);

        // gr_ir_entries created.
        $entry = GrIrEntry::query()->first();
        $this->assertNotNull($entry);
        $this->assertEquals(GrIrEntry::STATUS_OUTSTANDING, $entry->status);
        $this->assertEquals('200.0000', $entry->receipt_amount);
        $this->assertEquals($prItem->id, $entry->purchase_request_item_id);
        $this->assertEquals($supplier->id, $entry->supplier_id);
        $this->assertEquals($batch->id, $entry->receipt_journal_batch_id);

        // Inventory movement forward-linked to journal_batch.
        $movement = InventoryMovement::query()->where('source_type', InventoryMovement::SOURCE_STOCK_LOG)->first();
        $this->assertEquals($batch->id, $movement->journal_batch_id);
    }

    public function test_direct_receipt_posts_to_suspense_and_no_gr_ir_entry(): void
    {
        $rm = $this->makeRawMaterial();
        $supplier = $this->makeSupplier('Cash Vendor');

        $log = $this->makeStockLog($rm, null, $supplier, qty: 10.0, unitCost: 3.0, reason: 'donation');
        $domainEvent = $this->dispatchReceivedEvent($log, $rm, null);

        $subscriber = $this->app->make(RawMaterialReceivedSubscriber::class);
        $subscriber->handle($domainEvent->id);

        $batch = JournalBatch::query()->first();
        $this->assertNotNull($batch);

        $lines = JournalLine::query()->whereIn('journal_id', $batch->journals()->pluck('id'))->get();
        $this->assertCount(2, $lines);

        $cr = $lines->firstWhere('account_id', $this->suspenseAcct->id);
        $this->assertNotNull($cr);
        $this->assertEquals('30.00', $cr->credit);

        // No gr_ir_entries row.
        $this->assertEquals(0, GrIrEntry::count());
    }

    public function test_subscriber_is_idempotent_on_retry(): void
    {
        [$rm, $pr, $prItem, $supplier] = $this->makePRChain();
        $log = $this->makeStockLog($rm, $prItem, $supplier, 50.0, 4.0);
        $domainEvent = $this->dispatchReceivedEvent($log, $rm, $pr);

        $subscriber = $this->app->make(RawMaterialReceivedSubscriber::class);
        $subscriber->handle($domainEvent->id);

        $batchCountBefore = JournalBatch::count();

        // Re-mark event pending and run again — should skip cleanly.
        $domainEvent->refresh();
        $domainEvent->update(['status' => DomainEvent::STATUS_PENDING]);

        $subscriber->handle($domainEvent->id);

        // Still exactly ONE batch.
        $this->assertEquals($batchCountBefore, JournalBatch::count());
        // Still exactly ONE gr_ir_entries row.
        $this->assertEquals(1, GrIrEntry::count());
    }

    public function test_zero_value_receipt_is_skipped(): void
    {
        $rm = $this->makeRawMaterial();
        $supplier = $this->makeSupplier('Free Sample');
        $log = $this->makeStockLog($rm, null, $supplier, 5.0, 0.0, reason: 'sample');
        $domainEvent = $this->dispatchReceivedEvent($log, $rm, null);

        $this->app->make(RawMaterialReceivedSubscriber::class)->handle($domainEvent->id);

        $this->assertEquals(0, JournalBatch::count());
        $this->assertEquals(DomainEvent::STATUS_SKIPPED, $domainEvent->fresh()->status);
    }

    // -------- helpers --------

    protected function mkAcct(string $code, string $name, string $type): Account
    {
        return Account::firstOrCreate(
            ['organization_id' => $this->org->id, 'code' => $code],
            ['name' => $name, 'account_type' => $type, 'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => true],
        );
    }

    protected function makeRawMaterial(string $name = 'Maize'): RawMaterial
    {
        return RawMaterial::create([
            'name' => $name . '-' . uniqid(),
            'unit' => 'kg', 'quantity_in_stock' => 0, 'unit_cost' => 0, 'reorder_level' => 0,
        ]);
    }

    protected function makeSupplier(string $name): Counterparty
    {
        return Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => $name,
            'code' => 'S-' . substr(uniqid(), -6),
            'type' => Counterparty::TYPE_SUPPLIER, 'currency_code' => 'GHS',
        ]);
    }

    /** @return array{0:RawMaterial,1:PurchaseRequest,2:PurchaseRequestItem,3:Counterparty} */
    protected function makePRChain(): array
    {
        $supplier = $this->makeSupplier('PR Supplier');
        $rm = $this->makeRawMaterial();
        $pr = PurchaseRequest::create([
            'request_no' => 'PR-T-' . uniqid(),
            'request_date' => now()->toDateString(),
            'requested_by' => $this->user->id,
            'status' => 'approved',
            'total_amount' => 200,
            'supplier_id' => $supplier->id,
            'organization_id' => $this->org->id,
        ]);
        $prItem = PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'item_name' => $rm->name,
            'description' => $rm->name,
            'quantity' => 50,
            'unit_price' => 4,
            'total_price' => 200,
            'raw_material_id' => $rm->id,
        ]);
        return [$rm, $pr, $prItem, $supplier];
    }

    protected function makeStockLog(
        RawMaterial $rm,
        ?PurchaseRequestItem $prItem,
        Counterparty $supplier,
        float $qty,
        float $unitCost,
        ?string $reason = null,
    ): RawMaterialStockLog {
        // Use InventoryService to also create the movement row (matches production flow).
        $rm->increment('quantity_in_stock', $qty);
        $rm->update(['unit_cost' => $unitCost]);

        $log = RawMaterialStockLog::create([
            'raw_material_id' => $rm->id,
            'type' => 'receive',
            'quantity' => $qty,
            'unit_cost' => $unitCost,
            'performed_by' => $this->user->id,
            'organization_id' => $this->org->id,
            'supplier_id' => $supplier->id,
            'purchase_request_item_id' => $prItem?->id,
            'receipt_reason' => $reason,
            'posting_status' => 'pending',
            'is_posted' => false,
        ]);

        $this->app->make(InventoryService::class)->recordReceive(
            itemKind:   InventoryMovement::ITEM_KIND_RAW_MATERIAL,
            itemId:     $rm->id,
            quantity:   $qty,
            unitCost:   $unitCost,
            sourceType: InventoryMovement::SOURCE_STOCK_LOG,
            sourceId:   $log->id,
            occurredAt: CarbonImmutable::instance($log->created_at ?? now()),
            actorUserId: $this->user->id,
        );

        return $log;
    }

    protected function dispatchReceivedEvent(RawMaterialStockLog $log, RawMaterial $rm, ?PurchaseRequest $pr): DomainEvent
    {
        $dispatcher = $this->app->make(DispatchDomainEvent::class);
        $domainEventRecord = $dispatcher->dispatch(new RawMaterialReceived(
            source: new SourceReference('inventory', RawMaterialStockLog::class, (int) $log->id, $this->org->id),
            occurredAt: CarbonImmutable::now(),
            dispatchedByUserId: $this->user->id,
            rawMaterialId: $rm->id,
            rawMaterialName: $rm->name,
            quantity: (float) $log->quantity,
            unit: (string) $rm->unit,
            unitCost: (float) $log->unit_cost,
            totalCost: (float) $log->quantity * (float) $log->unit_cost,
            reference: null,
            purchaseRequestId: $pr?->id,
        ));
        return $domainEventRecord;
    }
}
