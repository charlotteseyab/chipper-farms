<?php

namespace App\Finance\Subscribers\Sales;

use App\Domain\Events\Sales\StorefrontOrderConfirmed;
use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Subscribers\Subscriber;
use App\Models\DomainEvent;
use App\Models\FeedType;
use App\Models\FeedSale;
use App\Models\Counterparty;
use App\Models\Finance\Invoice;
use App\Models\PigManagement;
use App\Models\PigSale;
use App\Models\PaymentMethod;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontOrder;
use App\Models\User;
use App\Services\Finance\Invoicing\InvoiceLineDraft;
use App\Services\Finance\Invoicing\InvoicePostingService;
use App\Services\Finance\Invoicing\InvoiceService;
use App\Services\Sales\RecordFeedSaleService;
use App\Services\Sales\RecordPigSaleService;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\Log;

/**
 * Phase 4 — storefront finance integration.
 *
 * Consumes storefront_order.confirmed and routes each order line into the
 * EXISTING sale-recording spine, so revenue/AR + COGS/inventory post through
 * the same battle-tested subscribers (no parallel posting logic, no drift):
 *
 *   - feed (sold in kg)              → RecordFeedSaleService(channel=storefront)
 *   - live_pig / breeding_stock      → RecordPigSaleService(explicit price)
 *     (priced per-kg with a weight)
 *
 * Settlement: paymentStatus 'paid' (Paystack already verified) books a cash
 * sale; 'pending' (COD) books AR now and collects later (Phase 5).
 *
 * Like PigSaleInvoicingSubscriber, this routes through services rather than
 * producing a JournalBatchDraft, so it overrides handle() and returns null
 * from buildDraft().
 *
 * Lines that cannot be mapped EXACTLY onto the kg×rate spine (fixed-price pigs
 * without a weight, feed not sold in kg, manure/service/lots) are DEFERRED with
 * a recorded reason rather than posted with wrong numbers — production-safe.
 * The order's finance_posting_status reflects the outcome.
 */
class StorefrontOrderConfirmedSubscriber extends Subscriber
{
    public function __construct(
        protected RecordFeedSaleService $feedSales,
        protected RecordPigSaleService $pigSales,
        protected CurrentOrganization $currentOrganization,
        protected InvoiceService $invoices,
        protected InvoicePostingService $invoicePosting,
    ) {
    }

    protected function eventType(): string
    {
        return StorefrontOrderConfirmed::TYPE;
    }

    public function handle(int $domainEventId): void
    {
        $event = DomainEvent::find($domainEventId);
        if (! $event || $event->event_type !== $this->eventType()) {
            return;
        }

        $order = StorefrontOrder::with('items')->find($event->source_id);
        if (! $order) {
            $this->markEvent($event, DomainEvent::STATUS_FAILED, 'Storefront order missing');
            return;
        }

        // Idempotency: a re-delivered event for an already-posted order is a
        // no-op.
        if ($order->finance_posting_status === 'posted') {
            $this->markEvent($event, DomainEvent::STATUS_PROCESSED);
            return;
        }

        // Bind org context defensively (the processor job binds it too).
        $this->currentOrganization->bind(
            \App\Models\Organization::find($order->organization_id)
        );

        $actor = $this->resolveActor($event, $order);
        if ($actor === null) {
            $this->markEvent($event, DomainEvent::STATUS_FAILED, 'No actor to attribute the sale to');
            return;
        }

        $paymentStatus = (string) ($event->payload['payment_status'] ?? 'pending');
        $paymentMethod = $event->payload['payment_method'] ?? null;
        $buyerName     = $event->payload['buyer_name'] ?? null;
        $counterpartyId = $event->payload['counterparty_id'] ?? null;

        $posted = 0;
        $deferred = [];

        // IMP-091 — split the order's tax across its lines before routing any
        // of them. Each line becomes its own sale and its own invoice, so the
        // tax the buyer was charged has to be apportioned; allocating here
        // rather than per line is what makes the parts sum to the whole. See
        // StorefrontTaxService::allocate() for why independent rounding does
        // not add up.
        $taxByItem = $this->allocateTax($order);

        try {
            foreach ($order->items as $item) {
                $reason = $this->routeLine($item, $order, $actor, $paymentStatus, $paymentMethod, $buyerName, $counterpartyId, $taxByItem[$item->id] ?? '0.00');
                if ($reason === null) {
                    $posted++;
                } else {
                    $deferred[] = "item#{$item->id}: {$reason}";
                }
            }
        } catch (\Throwable $e) {
            $order->forceFill([
                'finance_posting_status' => 'failed',
                'finance_posting_error'  => substr($e->getMessage(), 0, 500),
            ])->save();
            $this->markEvent($event, DomainEvent::STATUS_FAILED, $e->getMessage());
            Log::error("StorefrontOrderConfirmedSubscriber failed for order #{$order->id}", ['error' => $e->getMessage()]);
            throw $e;
        }

        // Reflect the outcome on the order.
        if ($posted > 0 && empty($deferred)) {
            $status = 'posted';
            $error = null;
        } elseif ($posted > 0) {
            $status = 'posted'; // partially posted — record what was deferred
            $error = 'Deferred lines: ' . implode('; ', $deferred);
        } else {
            $status = 'skipped';
            $error = empty($deferred) ? 'No order lines' : 'Deferred lines: ' . implode('; ', $deferred);
        }

        $order->forceFill([
            'finance_posting_status' => $status,
            'finance_posting_error'  => $error ? substr($error, 0, 500) : null,
        ])->save();

        // Audit trail on the order timeline.
        \App\Models\Storefront\StorefrontOrderEvent::create([
            'organization_id' => $order->organization_id,
            'order_id'        => $order->id,
            'type'            => 'system',
            'note'            => "Finance: {$posted} line(s) recorded" . ($deferred ? ', ' . count($deferred) . ' deferred' : '') . '.',
            'actor_user_id'   => $actor->id,
        ]);

        $this->markEvent($event, DomainEvent::STATUS_PROCESSED);
    }

    protected function buildDraft(DomainEvent $event): ?JournalBatchDraft
    {
        return null; // routes through services, not a journal draft
    }

    /**
     * Route a single line. Returns null on success, or a defer-reason string.
     */
    /**
     * The order's tax, split across its lines.
     *
     * @return array<int, string> order item id => tax amount
     */
    protected function allocateTax(\App\Models\Storefront\StorefrontOrder $order): array
    {
        $snapshot = $order->tax_snapshot;

        if (! is_array($snapshot) || $snapshot === []) {
            return [];
        }

        $calculation = \App\Services\Finance\Tax\TaxCalculation::fromSnapshot($snapshot);

        $lineTotals = [];
        foreach ($order->items as $item) {
            $lineTotals[$item->id] = (string) $item->line_total;
        }

        // Delivery is part of the taxable base but is not an order line, so its
        // share of the tax rides on the lines proportionally. The alternative —
        // a phantom delivery line on one of the invoices — would put a charge
        // on a document that never mentions it.
        return app(\App\Storefront\Services\StorefrontTaxService::class)
            ->allocate($calculation, $lineTotals);
    }

    protected function routeLine(
        $item,
        StorefrontOrder $order,
        User $actor,
        string $paymentStatus,
        ?string $paymentMethod,
        ?string $buyerName,
        ?int $counterpartyId,
        string $lineTax = '0.00',
    ): ?string {
        $kind = $item->kind;

        if ($this->soldByAllocation($item)) {
            return null;
        }

        if (in_array($kind, [StorefrontListing::KIND_LIVE_PIG, StorefrontListing::KIND_BREEDING_STOCK], true)) {
            return $this->routePigLine($item, $actor, $paymentStatus, $paymentMethod, $buyerName, $counterpartyId, $order, $lineTax);
        }

        if ($kind === StorefrontListing::KIND_FEED && $item->source_type === FeedType::class && $item->source_id !== null) {
            return $this->routeFeedLine($item, $actor, $paymentStatus, $paymentMethod, $buyerName, $counterpartyId, $order, $lineTax);
        }

        if ($this->isManualInvoiceLine($item)) {
            return $this->routeManualInvoiceLine($item, $actor, $paymentStatus, $paymentMethod, $counterpartyId, $order, $lineTax);
        }

        return 'kind_not_auto_postable:' . $kind;
    }

    /**
     * @param  string  $lineTax  this line's share of the order's tax (IMP-091)
     */
    protected function routePigLine($item, User $actor, string $paymentStatus, ?string $paymentMethod, ?string $buyerName, ?int $counterpartyId, ?StorefrontOrder $order = null, string $lineTax = '0.00'): ?string
    {
        // A POOLED line was already sold, animal by animal, at approval.
        //
        // `StorefrontPigAllocationService` calls `RecordPigSaleService` once per
        // selected pig and each of those dispatches its own `PigSaleRecorded`,
        // so finance has already posted. There is nothing left for this
        // subscriber to do, and trying would be the duplicate sale the whole
        // design exists to prevent.
        //
        // Returning null rather than a deferral reason matters: a pooled line
        // is HANDLED, not skipped. Reporting it as deferred would put a false
        // problem on the order's finance panel and in Needs Attention — the
        // "skipped is not an error" confusion Phase 15 removed.
        if ($this->soldByAllocation($item)) {
            return null;
        }

        // A pooled line that reaches confirmation with no sold allocations has
        // not been through approval. That IS a real deferral: staff must pick
        // the animals, and the line must not be silently dropped.
        if ($this->requiresPigSelection($item)) {
            return 'pig_awaiting_pig_selection';
        }

        if ($item->source_type !== PigManagement::class || $item->source_id === null) {
            return 'pig_source_missing';
        }
        if (! in_array($item->price_basis, [StorefrontListing::BASIS_LIVE, StorefrontListing::BASIS_CARCASS], true)) {
            return 'pig_requires_per_kg_basis';
        }
        if ($item->weight_kg === null || bccomp((string) $item->weight_kg, '0', 4) <= 0) {
            return 'pig_requires_weight';
        }

        $pig = PigManagement::withTrashed()->find($item->source_id);
        if ($pig === null) {
            return 'pig_not_found';
        }
        if ($pig->trashed()) {
            return 'pig_already_sold';
        }

        $weight    = (string) $item->weight_kg;
        $lineTotal = (string) $item->line_total;
        $pricePerKg = bcdiv($lineTotal, $weight, 4);

        $sale = $this->pigSales->execute($pig, $actor, [
            'price_source'   => 'explicit',
            'sale_weight'    => (float) $weight,
            'price_per_kg'   => (float) $pricePerKg,
            'sale_price'     => (float) $lineTotal,
            'weight_type'    => $item->price_basis,
            'sale_date'      => now()->toDateString(),
            // A storefront pig sale IS a market sale. 'storefront' is the
            // CHANNEL, not the purpose — and the feed path right below already
            // models it that way, passing channel => 'storefront' as its own
            // field. Pig sales stuffing it into sale_purpose was the
            // inconsistency, and it put a value in the column that no CHECK
            // constraint permitted, so every storefront pig sale failed to post.
            //
            // The channel remains recoverable: storefront_order_items carries
            // posted_sale_id back to this sale.
            'sale_purpose'   => 'market',
            // IMP-091 — the tax the buyer was actually charged, carried onto
            // the sale so the invoice totals the same number. The snapshot
            // rides along because a credit note must reverse the regime that
            // applied at the time, not today's (IMP-014).
            'tax_profile_id' => $order?->tax_profile_id,
            'tax_amount'     => $lineTax,
            'tax_snapshot'   => $order?->tax_snapshot,
            'payment_status' => $paymentStatus,
            'payment_method' => $paymentMethod,
            'buyer_name'     => $buyerName,
            'customer_id'    => $counterpartyId,
        ]);

        $item->forceFill([
            'posted_sale_type' => PigSale::class,
            'posted_sale_id'   => $sale->id,
        ])->save();

        return null;
    }

    /**
     * Did approval already sell this line's animals?
     *
     * Read from the allocations rather than `posted_sale_id`, because that
     * column holds one id and a pooled line produces several sales.
     */
    protected function soldByAllocation($item): bool
    {
        return \App\Models\Storefront\StorefrontOrderItemPigAllocation::query()
            ->where('storefront_order_item_id', $item->id)
            ->where('status', \App\Models\Storefront\StorefrontOrderItemPigAllocation::STATUS_SOLD)
            ->exists();
    }

    /**
     * Is this a line whose listing draws from a pig pool?
     *
     * A legacy single-pig line has no pool and keeps the old path below
     * untouched, which is what stops this change reaching existing orders.
     */
    protected function requiresPigSelection($item): bool
    {
        if ($item->listing_id === null) {
            return false;
        }

        return \App\Models\Storefront\StorefrontListingPig::query()
            ->where('storefront_listing_id', $item->listing_id)
            ->exists();
    }

    /**
     * @param  string  $lineTax  this line's share of the order's tax (IMP-091)
     */
    protected function routeFeedLine($item, User $actor, string $paymentStatus, ?string $paymentMethod, ?string $buyerName, ?int $counterpartyId, ?StorefrontOrder $order = null, string $lineTax = '0.00'): ?string
    {
        if ($item->source_type !== FeedType::class || $item->source_id === null) {
            return 'feed_source_missing';
        }
        if ($item->quantity_unit !== 'kg') {
            return 'feed_requires_kg_unit';
        }

        $feedType = FeedType::find($item->source_id);
        if ($feedType === null) {
            return 'feed_type_not_found';
        }

        $sale = $this->feedSales->execute($feedType, $actor, [
            'quantity_kg'     => (float) $item->quantity,
            'unit_price'      => (float) $item->unit_price,
            'channel'         => 'storefront',
            'tax_profile_id'  => $order?->tax_profile_id,
            'tax_amount'      => $lineTax,
            'tax_snapshot'    => $order?->tax_snapshot,
            'payment_status'  => $paymentStatus,
            'payment_method'  => $paymentMethod,
            'counterparty_id' => $counterpartyId,
            'buyer_name'      => $buyerName,
            'sale_date'       => now()->toDateString(),
        ]);

        $item->forceFill([
            'posted_sale_type' => FeedSale::class,
            'posted_sale_id'   => $sale->id,
        ])->save();

        return null;
    }

    protected function isManualInvoiceLine($item): bool
    {
        if ($item->purchase_type === \App\Storefront\Services\StorefrontPigPricingService::PURCHASE_MANUAL
            || $item->source_category === \App\Storefront\Services\StorefrontPigPricingService::CATEGORY_MANUAL) {
            return true;
        }

        return in_array($item->kind, [
            StorefrontListing::KIND_FEED,
            StorefrontListing::KIND_MANURE,
            StorefrontListing::KIND_SERVICE,
        ], true) && ($item->source_type === null || $item->source_id === null);
    }

    protected function routeManualInvoiceLine(
        $item,
        User $actor,
        string $paymentStatus,
        ?string $paymentMethod,
        ?int $counterpartyId,
        StorefrontOrder $order,
        string $lineTax = '0.00',
    ): ?string {
        if ($item->posted_sale_type === Invoice::class && $item->posted_sale_id !== null && Invoice::find($item->posted_sale_id) !== null) {
            return null;
        }

        $counterparty = Counterparty::find($counterpartyId ?: $order->counterparty_id);

        if ($counterparty === null) {
            return 'manual_invoice_counterparty_missing';
        }

        $paymentMethodId = $this->paymentMethodIdForInvoice($order, $paymentStatus, $paymentMethod);

        $invoice = $this->invoices->createDraft(
            customer: $counterparty,
            headerData: [
                'invoice_date'      => now()->toDateString(),
                'due_date'          => now()->toDateString(),
                'currency_code'     => $order->currency_code ?: 'GHS',
                'payment_method_id' => $paymentMethodId,
                'source_type'       => $item::class,
                'source_id'         => $item->id,
                'reference'         => $order->reference,
                'description'       => "Storefront order {$order->reference}",
                'notes'             => $item->description,
            ],
            lineDrafts: [
                new InvoiceLineDraft(
                    description: (string) $item->description,
                    quantity: bcadd((string) $item->quantity, '0', 4),
                    unitPrice: bcadd((string) $item->unit_price, '0', 2),
                    unitOfMeasure: (string) ($item->quantity_unit ?: 'unit'),
                    taxAmount: bccomp($lineTax, '0', 2) > 0 ? $lineTax : null,
                ),
            ],
            createdBy: $actor,
        );

        $posted = $this->invoicePosting->post($invoice, $actor);

        $item->forceFill([
            'posted_sale_type' => Invoice::class,
            'posted_sale_id'   => $posted->id,
        ])->save();

        return null;
    }

    protected function paymentMethodIdForInvoice(StorefrontOrder $order, string $paymentStatus, ?string $paymentMethod): ?int
    {
        if ($paymentStatus !== 'paid' && $order->payment_method !== StorefrontOrder::METHOD_COD) {
            return null;
        }

        $code = $paymentMethod === StorefrontOrder::METHOD_PAYSTACK || $order->payment_method === StorefrontOrder::METHOD_PAYSTACK
            ? 'PAYSTACK'
            : 'CASH';

        $method = PaymentMethod::query()
            ->where(function ($q) use ($order) {
                $q->where('organization_id', $order->organization_id)
                    ->orWhereNull('organization_id');
            })
            ->where('code', $code)
            ->where('is_active', true)
            ->orderByRaw('organization_id is null')
            ->first();

        return $method?->id;
    }

    protected function resolveActor(DomainEvent $event, StorefrontOrder $order): ?User
    {
        if ($event->dispatched_by_user_id) {
            $user = User::find($event->dispatched_by_user_id);
            if ($user !== null) {
                return $user;
            }
        }

        return User::query()
            ->where('organization_id', $order->organization_id)
            ->whereIn('role', ['superuser', 'admin'])
            ->first();
    }

    protected function markEvent(DomainEvent $event, string $status, ?string $error = null): void
    {
        $event->update([
            'status'              => $status,
            'processed_at'        => now(),
            'processing_error'    => $error,
            'processing_attempts' => ($event->processing_attempts ?? 0) + 1,
        ]);
    }
}
