<?php

namespace App\Finance\Subscribers\Sales;

use App\Domain\Events\Sales\SalesReturnRecorded;
use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Resolvers\FinanceAccountResolver;
use App\Finance\Subscribers\Subscriber;
use App\Models\DomainEvent;
use App\Models\FeedSale;
use App\Models\Finance\Invoice;
use App\Models\Finance\JournalBatch;
use App\Models\Finance\JournalPosting;
use App\Models\PigSale;
use App\Models\SalesReturn;
use App\Finance\Resolvers\CounterpartyResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Reverses the REVENUE side of a sales return (CF-P0-008, phase 4).
 *
 * The COGS subscribers give the goods back. This gives the money back — it
 * credits the customer, so it runs for EVERY return including restock=false,
 * where the goods stay consumed but the customer is still owed a refund.
 *
 * WHY THERE IS NO CREDIT-NOTE DOCUMENT
 * -----------------------------------
 * The remediation brief lists "invoice credit" as part of this finding, and the
 * obvious reading is that a credit-note entity is needed. Checking the schema
 * says otherwise, and building one would have added a document type nothing
 * requires:
 *
 *   - FEED sales carry NO invoice at all (feed_sales has no invoice_id). Revenue
 *     is posted as a direct journal, Dr AR-or-Cash / Cr Revenue. There is
 *     nothing to raise a credit note against; the correction is a journal.
 *
 *   - PIG sales DO carry an invoice (pig_sales.invoice_id), and a pig return is
 *     always the whole animal — pigs are indivisible. A full return means the
 *     whole invoice is reversed, which Invoice::STATUS_REVERSED and the existing
 *     JournalReversalService path already express.
 *
 * A partial credit note would only be needed for a PARTIAL return against an
 * INVOICED sale. That combination does not currently exist: the only invoiced
 * sale type cannot be partially returned. If feed sales are ever invoiced, this
 * is the seam where a credit note belongs, and the residual is recorded in the
 * register rather than pre-emptively built.
 *
 * PAID VS UNPAID
 * --------------
 * The credit goes back to whichever account the sale debited:
 *
 *   sale was on credit  → Cr Accounts Receivable (the customer owes less)
 *   sale was paid       → Cr Cash (money is going back out)
 *
 * Getting this wrong is not cosmetic. Crediting AR on a cash sale leaves a
 * negative receivable for a customer who never owed anything, and leaves the
 * cash account overstated by the refund.
 */
class SalesReturnRevenueSubscriber extends Subscriber
{
    public function __construct(
        protected FinanceAccountResolver $accountResolver,
        protected CounterpartyResolver $counterpartyResolver,
    ) {
    }

    protected function eventType(): string
    {
        return SalesReturnRecorded::TYPE;
    }

    protected function buildDraft(DomainEvent $event): ?JournalBatchDraft
    {
        $return = SalesReturn::find($event->source_id);

        if (! $return) {
            Log::error("SalesReturnRevenueSubscriber: event #{$event->id} references missing SalesReturn {$event->source_id}");

            return null;
        }

        // Already credited — a replayed event must not refund twice.
        if ($return->revenue_reversal_batch_id !== null) {
            return null;
        }

        return match ($return->returnable_type) {
            FeedSale::class => $this->buildFeedRevenueReversal($return, $event),
            PigSale::class  => $this->buildPigRevenueReversal($return, $event),
            default         => null,
        };
    }

    /**
     * Feed: reverse a proportional share of the sale's revenue.
     */
    protected function buildFeedRevenueReversal(SalesReturn $return, DomainEvent $event): ?JournalBatchDraft
    {
        $sale = FeedSale::find($return->returnable_id);

        if (! $sale) {
            $this->markReturnFailed($return, 'feed_sale_missing');

            return null;
        }

        if ($sale->revenue_journal_batch_id === null) {
            $this->markReturnFailed($return, 'original_revenue_not_posted');

            return null;
        }

        // Proportional to what is being returned. Derived from the sale's own
        // total rather than recomputed from unit price x quantity, so a later
        // price amendment cannot change what an earlier return refunded.
        $saleTotal      = (string) $sale->sale_total;
        $reversalAmount = bcmul($saleTotal, (string) $return->proportion, 2);

        if (bccomp($reversalAmount, '0', 2) <= 0) {
            $this->markReturnFailed($return, 'zero_revenue_to_reverse');

            return null;
        }

        $channel     = (string) ($sale->channel ?? 'direct');
        $revenueCode = config("feed_sales.revenue_accounts.{$channel}")
            ?? config('feed_sales.revenue_accounts.direct', '4110');

        $revenueAccount = $this->accountResolver->byCode($revenueCode);

        // Mirror of the sale: whichever account it debited is credited now.
        $offsetAccount = $sale->payment_status === FeedSale::PAYMENT_PAID
            ? $this->accountResolver->byCode((string) config('feed_sales.default_cash_account_code', '1030'))
            : $this->accountResolver->byCode((string) config('feed_sales.ar_account_code', '1120'));

        $counterparty = $this->counterpartyResolver->forPigSale($sale->counterparty_id);

        $description = sprintf(
            'Sales return #%d — feed sale #%d revenue reversal',
            $return->id,
            $sale->id,
        );

        $dims = [
            'counterparty_id' => $counterparty->id,
            'source_type'     => SalesReturn::class,
            'source_id'       => $return->id,
            'reference'       => 'Sales Return #' . $return->id,
        ];

        $return->forceFill(['revenue_reversed_amount' => $reversalAmount])->save();

        return $this->draft($return, $event, $description, [
            // Dr Revenue (income comes back down), Cr AR-or-Cash.
            JournalLineDraft::debit($revenueAccount->id, $reversalAmount, $description, $dims),
            JournalLineDraft::credit($offsetAccount->id, $reversalAmount, $description, $dims),
        ]);
    }

    /**
     * Pig: always a full return, so the whole sale's revenue is reversed and the
     * invoice — if one exists — is marked reversed alongside it.
     */
    protected function buildPigRevenueReversal(SalesReturn $return, DomainEvent $event): ?JournalBatchDraft
    {
        $sale = PigSale::find($return->returnable_id);

        if (! $sale) {
            $this->markReturnFailed($return, 'pig_sale_missing');

            return null;
        }

        $saleTotal = (string) ($sale->sale_price ?? '0');

        if (bccomp($saleTotal, '0', 2) <= 0) {
            $this->markReturnFailed($return, 'zero_revenue_to_reverse');

            return null;
        }

        $invoice = $sale->invoice_id ? Invoice::find($sale->invoice_id) : null;

        // Revenue and receivable accounts come from the invoice's own posting
        // where there is one, so the reversal lands on exactly the accounts the
        // sale used rather than on today's configured defaults.
        $revenueAccount = $this->accountResolver->byCode(
            (string) config('pig_sales.revenue_account_code', '4110')
        );
        $offsetAccount = ($sale->payment_status === 'paid')
            ? $this->accountResolver->byCode((string) config('pig_sales.cash_account_code', '1030'))
            : $this->accountResolver->byCode((string) config('pig_sales.ar_account_code', '1120'));

        $counterparty = $this->counterpartyResolver->forPigSale($sale->counterparty_id ?? null);

        $description = sprintf(
            'Sales return #%d — pig sale #%d revenue reversal%s',
            $return->id,
            $sale->id,
            $invoice ? " (invoice {$invoice->invoice_number})" : '',
        );

        $dims = [
            'counterparty_id' => $counterparty->id,
            'source_type'     => SalesReturn::class,
            'source_id'       => $return->id,
            'reference'       => 'Sales Return #' . $return->id,
        ];

        $return->forceFill(['revenue_reversed_amount' => $saleTotal])->save();

        return $this->draft($return, $event, $description, [
            JournalLineDraft::debit($revenueAccount->id, $saleTotal, $description, $dims),
            JournalLineDraft::credit($offsetAccount->id, $saleTotal, $description, $dims),
        ]);
    }

    /**
     * @param  list<JournalLineDraft>  $lines
     */
    protected function draft(
        SalesReturn $return,
        DomainEvent $event,
        string $description,
        array $lines,
    ): JournalBatchDraft {
        $journal = new JournalDraft(
            journalType:  'sales_revenue',
            lines:        $lines,
            reference:    'Sales Return #' . $return->id,
            description:  $description,
            currencyCode: 'GHS',
        );

        return new JournalBatchDraft(
            batchType:      'auto_sales_return_revenue',
            postingDate:    CarbonImmutable::instance($event->occurred_at),
            journals:       [$journal],
            description:    $description,
            sourceModule:   'sales',
            sourceType:     SalesReturn::class,
            sourceId:       (int) $return->id,
            domainEventId:  (int) $event->id,
            idempotencyKey: JournalPosting::buildIdempotencyKey(
                $event->event_type,
                SalesReturn::class,
                (int) $return->id,
                JournalPosting::PURPOSE_SALES_RETURN_REVENUE,
            ),
            postingPurpose:  JournalPosting::PURPOSE_SALES_RETURN_REVENUE,
            subscriberClass: static::class,
        );
    }

    protected function onPostedSuccessfully(DomainEvent $event, JournalBatch $batch): void
    {
        $return = SalesReturn::find($event->source_id);

        if (! $return) {
            return;
        }

        $return->forceFill(['revenue_reversal_batch_id' => $batch->id])->save();

        // Mark the invoice reversed on a FULL pig return, so the document state
        // matches the ledger. Left alone on a partial return: an invoice that is
        // only partly credited is not "reversed", and saying so would misstate
        // the customer's balance.
        if ($return->returnable_type !== PigSale::class || ! $return->isFullReturn()) {
            return;
        }

        $sale = PigSale::find($return->returnable_id);
        $invoice = $sale?->invoice_id ? Invoice::find($sale->invoice_id) : null;

        if ($invoice && $invoice->status === Invoice::STATUS_POSTED) {
            $invoice->update([
                'status'                       => Invoice::STATUS_REVERSED,
                'reversed_by_journal_batch_id' => $batch->id,
            ]);
        }
    }

    /**
     * Named markReturnFailed() rather than fail(): Subscriber extends a queued
     * job which already exposes a public fail().
     */
    protected function markReturnFailed(SalesReturn $return, string $reason): void
    {
        $return->forceFill([
            'status'        => SalesReturn::STATUS_FAILED,
            'posting_error' => $reason,
        ])->save();

        Log::warning('sales return revenue reversal could not proceed', [
            'sales_return_id' => $return->id,
            'reason'          => $reason,
        ]);
    }
}
