<?php

namespace Tests\Feature\Finance\VendorInvoicing;

use App\Domain\Events\Procurement\PurchaseRequestApproved;
use App\Domain\Events\Support\SourceReference;
use App\Finance\Subscribers\Procurement\PurchaseRequestInvoicingSubscriber;
use App\Models\Counterparty;
use App\Models\DomainEvent;
use App\Models\Finance\Account;
use App\Models\Finance\SupplierCategory;
use App\Models\Finance\VendorInvoice;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class PurchaseRequestInvoicingSubscriberTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected PurchaseRequestInvoicingSubscriber $subscriber;
    protected Counterparty $supplier;
    protected User $requester;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        Account::create([
            'organization_id' => $this->org->id, 'code' => '6010',
            'name' => 'Sundry', 'account_type' => Account::TYPE_EXPENSE,
            'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => true,
        ]);

        $cat = SupplierCategory::create([
            'organization_id' => $this->org->id,
            'code' => 'F', 'name' => 'F',
            'payment_terms_days' => 30, 'default_withholding_percent' => 0,
            'is_active' => true,
        ]);

        $this->supplier = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Test Vendor', 'code' => 'V-T',
            'type' => Counterparty::TYPE_SUPPLIER, 'currency_code' => 'GHS',
            'supplier_category_id' => $cat->id,
        ]);

        $this->requester = User::factory()->create(['organization_id' => $this->org->id]);
        $this->subscriber = $this->app->make(PurchaseRequestInvoicingSubscriber::class);
    }

    public function test_flag_off_skips_event_silently(): void
    {
        Setting::set('finance.use_vendor_invoices_for_purchase_requests', false);

        $event = $this->createPrAndEvent();
        $this->subscriber->handle($event->id);

        $event->refresh();
        $this->assertSame(DomainEvent::STATUS_PENDING, $event->status,
            'Event remains pending — not marked skipped to allow other subscribers');
        $this->assertFalse(VendorInvoice::where('source_id', $event->source_id)->exists());
    }

    public function test_flag_on_creates_draft_vendor_invoice(): void
    {
        Setting::set('finance.use_vendor_invoices_for_purchase_requests', true);

        $event = $this->createPrAndEvent();
        $this->subscriber->handle($event->id);

        $event->refresh();
        $this->assertSame(DomainEvent::STATUS_PROCESSED, $event->status);

        $vi = VendorInvoice::where('source_type', PurchaseRequest::class)
            ->where('source_id', $event->source_id)
            ->first();
        $this->assertNotNull($vi);
        $this->assertSame(VendorInvoice::STATUS_DRAFT, $vi->status);
        $this->assertSame($this->supplier->id, $vi->counterparty_id);
        $this->assertCount(2, $vi->lines);
    }

    public function test_idempotent_on_redelivery(): void
    {
        Setting::set('finance.use_vendor_invoices_for_purchase_requests', true);

        $event = $this->createPrAndEvent();
        $this->subscriber->handle($event->id);

        $countAfterFirst = VendorInvoice::where('source_id', $event->source_id)->count();

        // Re-deliver: reset event to pending, run again
        $event->update(['status' => DomainEvent::STATUS_PENDING]);
        $this->subscriber->handle($event->id);

        $countAfterSecond = VendorInvoice::where('source_id', $event->source_id)->count();
        $this->assertSame($countAfterFirst, $countAfterSecond,
            'Re-delivery should not create a duplicate VI');

        $event->refresh();
        $this->assertSame(DomainEvent::STATUS_PROCESSED, $event->status);
    }

    public function test_pr_without_supplier_id_marked_skipped(): void
    {
        Setting::set('finance.use_vendor_invoices_for_purchase_requests', true);

        $pr = PurchaseRequest::create([
            'organization_id' => $this->org->id,
            'request_no' => 'PR-NO-SUPPLIER',
            'request_date' => '2026-11-01',
            'requested_by' => $this->requester->id,
            'status' => 'approved',
            'total_amount' => 100,
            // No supplier_id!
        ]);
        PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'item_name' => 'X', 'quantity' => 1, 'unit_price' => 100, 'total_price' => 100,
        ]);

        $event = $this->createEvent($pr);
        $this->subscriber->handle($event->id);

        $event->refresh();
        $this->assertSame(DomainEvent::STATUS_SKIPPED, $event->status);
        $this->assertFalse(VendorInvoice::where('source_id', $pr->id)->exists());
    }

    public function test_supplier_with_withholding_passes_to_lines(): void
    {
        Setting::set('finance.use_vendor_invoices_for_purchase_requests', true);

        $this->supplier->update(['withholding_percent' => 7.5]);

        $event = $this->createPrAndEvent();
        $this->subscriber->handle($event->id);

        $vi = VendorInvoice::where('source_id', $event->source_id)->first();
        foreach ($vi->lines as $line) {
            $this->assertSame('7.5000', $line->withholding_rate,
                'Supplier withholding rate copied to each line');
        }
        // 7.5% on 100 + 7.5% on 200 = 7.5 + 15 = 22.5
        $this->assertSame('22.50', $vi->withholding_total);
    }

    public function test_supplier_exempt_zeroes_withholding(): void
    {
        Setting::set('finance.use_vendor_invoices_for_purchase_requests', true);

        $this->supplier->update(['withholding_percent' => 7.5, 'withholding_exempt' => true]);

        $event = $this->createPrAndEvent();
        $this->subscriber->handle($event->id);

        $vi = VendorInvoice::where('source_id', $event->source_id)->first();
        $this->assertSame('0.00', $vi->withholding_total);
    }

    public function test_pr_with_no_items_fails_event(): void
    {
        Setting::set('finance.use_vendor_invoices_for_purchase_requests', true);

        $pr = PurchaseRequest::create([
            'organization_id' => $this->org->id,
            'request_no' => 'PR-EMPTY',
            'request_date' => '2026-11-01',
            'requested_by' => $this->requester->id,
            'status' => 'approved',
            'total_amount' => 0,
            'supplier_id' => $this->supplier->id,
        ]);

        $event = $this->createEvent($pr);

        $this->expectException(\DomainException::class);
        $this->subscriber->handle($event->id);
    }

    /* helpers */

    protected function createPrAndEvent(): DomainEvent
    {
        $pr = PurchaseRequest::create([
            'organization_id' => $this->org->id,
            'request_no' => 'PR-2026-' . uniqid(),
            'request_date' => '2026-11-01',
            'requested_by' => $this->requester->id,
            'status' => 'approved',
            'total_amount' => 300,
            'supplier_id' => $this->supplier->id,
        ]);

        PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'item_name' => 'Item A', 'description' => 'thing one',
            'quantity' => 1, 'unit_price' => 100, 'total_price' => 100,
        ]);
        PurchaseRequestItem::create([
            'purchase_request_id' => $pr->id,
            'item_name' => 'Item B',
            'quantity' => 1, 'unit_price' => 200, 'total_price' => 200,
        ]);

        return $this->createEvent($pr);
    }

    protected function createEvent(PurchaseRequest $pr): DomainEvent
    {
        $domainEvent = new PurchaseRequestApproved(
            source: new SourceReference(PurchaseRequest::class, $pr->id),
            occurredAt: CarbonImmutable::now(),
            dispatchedByUserId: $this->requester->id,
            requestNumber: $pr->request_no,
            totalAmount: (float) $pr->total_amount,
            approvedByUserId: $this->requester->id,
            purpose: $pr->purpose ?? 'test purpose',
            items: [],
        );

        return DomainEvent::create([
            'event_type' => $domainEvent::TYPE,
            'source_type' => PurchaseRequest::class,
            'source_id' => $pr->id,
            'payload' => $domainEvent->payload(),
            'dispatched_by_user_id' => $this->requester->id,
            'occurred_at' => now(),
            'status' => DomainEvent::STATUS_PENDING,
        ]);
    }
}
