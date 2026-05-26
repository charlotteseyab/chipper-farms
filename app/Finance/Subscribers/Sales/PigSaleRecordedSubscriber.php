<?php

namespace App\Finance\Subscribers\Sales;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Resolvers\CounterpartyResolver;
use App\Finance\Resolvers\FinanceAccountResolver;
use App\Finance\Subscribers\Subscriber;
use App\Models\DomainEvent;
use App\Models\Finance\JournalBatch;
use App\Models\Finance\JournalPosting;
use App\Models\PigSale;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Subscribes to pig_sale.recorded.
 *
 * Posts the revenue side. Whether the offsetting debit hits Cash or AR
 * depends on payment_status:
 *
 *   payment_status='paid' (cash sale):
 *     Dr [Cash account by payment_method hint]   sale_total
 *         Cr Pig Sales Revenue (4010)              sale_total
 *
 *   payment_status='pending' or 'partial' (credit sale):
 *     Dr AR Pig Sales (1110)                     sale_total
 *         Cr Pig Sales Revenue (4010)              sale_total
 *
 * COGS posting is NOT done here in Phase 1c. That requires the biological
 * asset register (cumulative cost per pig) which is Phase 4. This subscriber
 * only does the revenue + cash/AR side. When Phase 4 lands, a separate
 * subscriber can listen for the same event and post COGS with a different
 * posting_purpose, taking advantage of the multi-purpose-per-source
 * idempotency support already built.
 *
 * Tax handling: Phase 1c does NOT split out VAT/NHIL/GETFund/COVID.
 * That requires the tax_groups table and rule engine which is a Phase 2
 * concern. For now, the gross sale_total is treated as revenue with no
 * tax split — this is consistent with current operations where no VAT
 * has been recorded on pig sales.
 *
 * Revenue account selection: Phase 1c uses '4010' (Live Pigs Standard)
 * for ALL pig sales regardless of weight_type or sale_purpose. Refining
 * this into per-purpose mapping (4010/4020/4030/4040) is a Phase 2
 * accounting policy enhancement.
 *
 * Currency: GHS only per Decision 3.
 */
class PigSaleRecordedSubscriber extends Subscriber
{
    public function __construct(
        protected FinanceAccountResolver $accountResolver,
        protected CounterpartyResolver $counterpartyResolver,
    ) {
    }

        /**
     * Phase 2b: opt out cleanly when the invoice flow is enabled. We
     * override handle() rather than return null from buildDraft() because
     * returning null from buildDraft() causes the base to mark the event
     * STATUS_SKIPPED, which would prevent PigSaleInvoicingSubscriber
     * from acting on it later in the same processing loop.
     */
    public function handle(int $domainEventId): void
    {
        if (\App\Models\Setting::get('finance.use_invoices_for_pig_sales', false)) {
            // Flag on — let PigSaleInvoicingSubscriber handle it.
            return;
        }

        $event = \App\Models\DomainEvent::find($domainEventId);
        if ($event && $event->source_id) {
            $sale = \App\Models\PigSale::find($event->source_id);
            // Already invoiced via the new path (e.g. flag was on then off
            // mid-processing) — skip silently.
            if ($sale && $sale->invoice_id) {
                return;
            }
        }

        parent::handle($domainEventId);
    }

    protected function eventType(): string
    {
        return 'pig_sale.recorded';
    }

    protected function buildDraft(DomainEvent $event): ?JournalBatchDraft
    {
        $payload = $event->payload;

        // Defensive: payload was created by our own dispatcher, but if the
        // schema ever evolved and a legacy event has a different shape,
        // we'd rather skip than misnumber the GL.
        $required = ['sale_total', 'payment_status', 'sale_purpose'];
        foreach ($required as $field) {
            if (! array_key_exists($field, $payload)) {
                Log::warning("PigSaleRecordedSubscriber: event #{$event->id} missing payload field '{$field}', skipping");
                return null;
            }
        }

        $saleTotal = (string) ((float) $payload['sale_total']);
        $paymentStatus = (string) $payload['payment_status'];

        if (bccomp($saleTotal, '0', 2) <= 0) {
            // Zero-revenue sale (rare — possibly a corrupt record).
            // No journal needed.
            Log::info("PigSaleRecordedSubscriber: event #{$event->id} has non-positive sale_total ({$saleTotal}), skipping");
            return null;
        }

        // Resolve the source pig_sale row to access dimensions not in the payload.
        // (Specifically: payment_method_id, customer_id — not all events carry these.)
        $sale = PigSale::find($event->source_id);
        if (! $sale) {
            Log::error("PigSaleRecordedSubscriber: event #{$event->id} references missing PigSale id={$event->source_id}");
            return null;
        }

        $revenueAccount = $this->accountResolver->byCode('4010');

        // Choose the offsetting account based on payment status.
        if ($paymentStatus === 'paid') {
            $offsetAccount = $this->resolveCashAccountForSale($sale);
        } else {
            // 'pending', 'partial', or anything else → AR.
            $offsetAccount = $this->accountResolver->byCode('1110');  // AR Pig Sales
        }

        $counterparty = $this->counterpartyResolver->forPigSale($sale->customer_id);

        $description = sprintf(
            'Pig sale: %s @ %s/kg (%s) — %s',
            $payload['pig_code'] ?? "pig#{$payload['pig_id']}",
            number_format((float) ($payload['price_per_kg'] ?? 0), 2),
            $payload['weight_type'] ?? 'live',
            $payload['buyer_name'] ?? $counterparty->name,
        );

        $journal = new JournalDraft(
            journalType: 'sales_revenue',
            lines: [
                JournalLineDraft::debit(
                    accountId: $offsetAccount->id,
                    amount:    $saleTotal,
                    description: $description,
                    dimensions: [
                        'counterparty_id'    => $counterparty->id,
                        'payment_method_id'  => $sale->payment_method_id,
                        'sales_channel_id'   => $sale->sales_channel_id,
                        'cost_centre_id'     => $sale->cost_centre_id,
                        'tax_code_id'        => $sale->tax_code_id,
                        'source_type'        => PigSale::class,
                        'source_id'          => $sale->id,
                        'reference'          => $sale->document_number,
                    ],
                ),
                JournalLineDraft::credit(
                    accountId: $revenueAccount->id,
                    amount:    $saleTotal,
                    description: $description,
                    dimensions: [
                        'counterparty_id'   => $counterparty->id,
                        'sales_channel_id'  => $sale->sales_channel_id,
                        'cost_centre_id'    => $sale->cost_centre_id,
                        'source_type'       => PigSale::class,
                        'source_id'         => $sale->id,
                        'reference'         => $sale->document_number,
                    ],
                ),
            ],
            reference:    $sale->document_number,
            description:  $description,
            currencyCode: 'GHS',
            exchangeRate: '1',
            sourceType:   PigSale::class,
            sourceId:     $sale->id,
        );

        return new JournalBatchDraft(
            batchType:        'auto_pig_sale',
            postingDate:      CarbonImmutable::instance($event->occurred_at),
            journals:         [$journal],
            description:      $description,
            sourceModule:     'sales',
            sourceType:       PigSale::class,
            sourceId:         $sale->id,
            domainEventId:    $event->id,
            idempotencyKey:   JournalPosting::buildIdempotencyKey(
                $event->event_type,
                PigSale::class,
                $sale->id,
                JournalPosting::PURPOSE_REVENUE,
            ),
            postingPurpose:   JournalPosting::PURPOSE_REVENUE,
            subscriberClass:  static::class,
        );
    }

    /**
     * Pick the cash/bank account based on the payment method's hint, with
     * fallback to Suspense - Receipts if the method isn't resolvable.
     */
    protected function resolveCashAccountForSale(PigSale $sale)
    {
        // If the sale has a payment_method_id, use its gl_account_hint.
        if ($sale->payment_method_id) {
            $sale->loadMissing('paymentMethodRef');
            $method = $sale->paymentMethodRef;
            if ($method && $method->gl_account_hint) {
                try {
                    return $this->accountResolver->byHint($method->gl_account_hint);
                } catch (\App\Finance\Resolvers\MissingAccountException $e) {
                    Log::warning("PigSaleRecordedSubscriber: hint '{$method->gl_account_hint}' didn't resolve, falling back to suspense");
                }
            }
        }

        // Legacy sales might have payment_method as a string with no FK.
        // Try the string against the hint map.
        $legacyMap = [
            'cash'         => 'CASH_ON_HAND',
            'bank'         => 'BANK_MAIN',
            'mobile_money' => 'MOMO_MTN',
            'momo'         => 'MOMO_MTN',
        ];
        $rawMethod = strtolower((string) ($sale->payment_method ?? ''));
        if (isset($legacyMap[$rawMethod])) {
            try {
                return $this->accountResolver->byHint($legacyMap[$rawMethod]);
            } catch (\App\Finance\Resolvers\MissingAccountException $e) {
                // Fall through to suspense.
            }
        }

        // Final fallback: suspense receipts. The accountant will see this
        // in the suspense report and reclassify manually.
        return $this->accountResolver->byHint('SUSPENSE_RECEIPT');
    }

    /**
     * After successful posting, flip is_posted on the source pig_sale.
     * Operational dashboards can then filter "posted vs not yet posted".
     */
    protected function onPostedSuccessfully(DomainEvent $event, JournalBatch $batch): void
    {
        if (! $event->source_id) {
            return;
        }

        PigSale::where('id', $event->source_id)
            ->update([
                'is_posted' => true,
                'posted_at' => now(),
            ]);
    }
}
