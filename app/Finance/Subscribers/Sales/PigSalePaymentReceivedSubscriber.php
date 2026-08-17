<?php

namespace App\Finance\Subscribers\Sales;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Resolvers\CounterpartyResolver;
use App\Finance\Resolvers\FinanceAccountResolver;
use App\Finance\Subscribers\Subscriber;
use App\Models\DomainEvent;
use App\Models\Finance\JournalPosting;
use App\Models\PigSale;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Subscribes to pig_sale.payment_status_changed.
 *
 * Posts a cash receipt when the status flips TO 'paid'. This is the
 * AR-clearing entry for sales that were originally credit:
 *
 *   Dr [Cash account by payment_method hint]   sale_total
 *       Cr AR Pig Sales (1110)                   sale_total
 *
 * Only posts when:
 *   - to_status == 'paid'
 *   - from_status was NOT 'paid' (i.e. it's a real transition, not a re-save)
 *
 * Other transitions are skipped:
 *   - paid → pending: the original revenue + AR posting from the recorded
 *     subscriber is correct; we'd need a reversal flow for "this payment
 *     bounced" which is a Phase 2 customer-payments-and-allocations concern.
 *   - pending → partial: partial payments need allocation logic. Phase 2.
 *   - paid → paid (idempotent re-save): no-op.
 *
 * Decision: in Phase 1c we ONLY post the clean transition. Other transitions
 * are marked SKIPPED with a clear note so the accountant can see them and
 * decide manually.
 *
 * Edge case: a sale that was originally cash (status='paid' from the start)
 * never goes through this subscriber because PigSaleRecordedSubscriber
 * already posted it as a cash sale. The original-paid flag is detected
 * via from_status — if from_status is empty/null/the same, it's not a
 * real transition.
 */
class PigSalePaymentReceivedSubscriber extends Subscriber
{
    public function __construct(
        protected FinanceAccountResolver $accountResolver,
        protected CounterpartyResolver $counterpartyResolver,
    ) {
    }

        /**
     * Phase 2c: opt out cleanly when this sale was invoiced via the new
     * Phase 2b/2c flow. The PigSalePaymentReceiptSubscriber handles those
     * by creating a receipt + allocation against the invoice.
     *
     * The early-return matches the same pattern used in Phase 2b for
     * the recorded-event legacy subscriber: returning from handle() is
     * silent and leaves the event PENDING for the next subscriber in
     * the registry to act on.
     */
    public function handle(int $domainEventId): void
    {
        $event = \App\Models\DomainEvent::find($domainEventId);
        if ($event && $event->source_id) {
            $sale = \App\Models\PigSale::find($event->source_id);
            if ($sale && $sale->invoice_id) {
                // Sale is invoiced — receipt subscriber owns this event.
                return;
            }
        }

        parent::handle($domainEventId);
    }

    protected function eventType(): string
    {
        return 'pig_sale.payment_status_changed';
    }

    protected function buildDraft(DomainEvent $event): ?JournalBatchDraft
    {
        $payload = $event->payload;

        $fromStatus = (string) ($payload['from_status'] ?? '');
        $toStatus   = (string) ($payload['to_status'] ?? '');

        // Only post for clean transitions to 'paid' from a non-paid state.
        if ($toStatus !== 'paid') {
            Log::info("PigSalePaymentReceivedSubscriber: event #{$event->id} to_status='{$toStatus}', not 'paid' — skipping");
            return null;
        }

        if ($fromStatus === 'paid') {
            // No real transition.
            return null;
        }

        if ($fromStatus === 'partial') {
            // Partial-to-paid needs allocation logic. Defer to Phase 2.
            Log::info("PigSalePaymentReceivedSubscriber: event #{$event->id} is partial→paid transition — deferred to Phase 2 receipt allocation, skipping");
            return null;
        }

        $saleTotal = (string) ((float) ($payload['sale_total'] ?? 0));
        if (bccomp($saleTotal, '0', 2) <= 0) {
            return null;
        }

        $sale = PigSale::find($event->source_id);
        if (! $sale) {
            Log::error("PigSalePaymentReceivedSubscriber: event #{$event->id} references missing PigSale id={$event->source_id}");
            return null;
        }

        // The clearing posts: Dr Cash, Cr AR.
        $cashAccount = $this->resolveCashAccountForSale($sale);
        $arAccount   = $this->accountResolver->byCode('1110');
        $counterparty = $this->counterpartyResolver->forPigSale($sale->customer_id);

        $description = sprintf(
            'Payment received for pig sale %s — %s',
            $sale->document_number ?? "sale#{$sale->id}",
            $sale->buyer_name ?? $counterparty->name,
        );

        $journal = new JournalDraft(
            journalType: 'cash_receipt',
            lines: [
                JournalLineDraft::debit(
                    accountId: $cashAccount->id,
                    amount:    $saleTotal,
                    description: $description,
                    dimensions: [
                        'counterparty_id'   => $counterparty->id,
                        'payment_method_id' => $sale->payment_method_id,
                        'source_type'       => PigSale::class,
                        'source_id'         => $sale->id,
                        'reference'         => $sale->document_number,
                    ],
                ),
                JournalLineDraft::credit(
                    accountId: $arAccount->id,
                    amount:    $saleTotal,
                    description: $description,
                    dimensions: [
                        'counterparty_id' => $counterparty->id,
                        'source_type'     => PigSale::class,
                        'source_id'       => $sale->id,
                        'reference'       => $sale->document_number,
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

        // The idempotency key must be unique PER TRANSITION. Two distinct
        // payment-received events should produce two separate cash-receipt
        // journals. Use the event id in the key so the second transition
        // (if it ever happens) gets its own entry.
        return new JournalBatchDraft(
            batchType:        'auto_pig_sale_receipt',
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
                $sale->id . ':e' . $event->id,  // include event id so multiple transitions don't collide
                JournalPosting::PURPOSE_CASH_RECEIPT,
            ),
            postingPurpose:   JournalPosting::PURPOSE_CASH_RECEIPT,
            subscriberClass:  static::class,
        );
    }

    /**
     * Same logic as the recorded subscriber. Could be extracted to a shared
     * trait, but duplicating it here keeps each subscriber self-contained
     * and easier to read.
     */
    protected function resolveCashAccountForSale(PigSale $sale)
    {
        if ($sale->payment_method_id) {
            $sale->loadMissing('paymentMethodRef');
            $method = $sale->paymentMethodRef;
            if ($method && $method->gl_account_hint) {
                try {
                    return $this->accountResolver->byHint($method->gl_account_hint);
                } catch (\App\Finance\Resolvers\MissingAccountException) {
                    // Fall through.
                }
            }
        }

        $legacyMap = [
            'cash'         => 'CASH_ON_HAND',
            'bank'         => 'BANK_MAIN',
            'mobile_money' => 'MOMO_MTN',
            'momo'         => 'MOMO_MTN',
            'paystack'     => 'PAYSTACK_CLEARING',
        ];
        $rawMethod = strtolower((string) ($sale->payment_method ?? ''));
        if (isset($legacyMap[$rawMethod])) {
            try {
                return $this->accountResolver->byHint($legacyMap[$rawMethod]);
            } catch (\App\Finance\Resolvers\MissingAccountException) {
                // Fall through.
            }
        }

        return $this->accountResolver->byHint('SUSPENSE_RECEIPT');
    }
}
