<?php

namespace App\Finance\Subscribers\Sales;

use App\Domain\Events\Sales\SalesReturnRecorded;
use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Subscribers\Subscriber;
use App\Models\DomainEvent;
use App\Models\Finance\CreditNote;
use App\Models\Finance\Invoice;
use App\Models\Finance\JournalBatch;
use App\Models\SalesReturn;
use App\Services\Finance\CreditNotes\CreditNoteService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Reverses the REVENUE side of a sales return (CF-P0-008, phase 4).
 *
 * The COGS subscribers give the goods back. This gives the money back — it
 * credits the customer, so it runs for EVERY return including restock=false,
 * where the goods stay consumed but the customer is still owed a refund.
 *
 * CREDIT-NOTE DOCUMENT
 * --------------------
 * Sales returns now create a standalone CreditNote header and line before the
 * revenue-side journal posts. The note owns the customer-facing document number,
 * invoice/order/return linkage, approval/posting state, and the idempotent
 * journal batch that adjusts revenue and AR/cash.
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
        protected CreditNoteService $creditNotes,
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

        try {
            $note = $this->creditNotes->ensureForSalesReturn(
                $return,
                CarbonImmutable::instance($event->occurred_at),
                $return->returnedBy,
            );

            if ($note->journal_batch_id !== null) {
                return null;
            }

            return $this->creditNotes->buildPostingDraft($note->loadMissing('lines'), (int) $event->id);
        } catch (\Throwable $e) {
            $reason = $e->getMessage() === 'original_revenue_not_posted'
                ? 'original_revenue_not_posted'
                : 'credit_note_failed: ' . $e->getMessage();

            $this->markReturnFailed($return, $reason);

            return null;
        }
    }

    protected function onPostedSuccessfully(DomainEvent $event, JournalBatch $batch): void
    {
        $return = SalesReturn::find($event->source_id);

        if (! $return) {
            return;
        }

        $creditNote = $return->credit_note_id ? CreditNote::find($return->credit_note_id) : null;
        if ($creditNote) {
            $this->creditNotes->markPosted($creditNote, $batch, $return->returnedBy);
        } else {
            $return->forceFill(['revenue_reversal_batch_id' => $batch->id])->save();
        }

        // Mark the invoice reversed on a FULL pig return, so the document state
        // matches the ledger. Left alone on a partial return: an invoice that is
        // only partly credited is not "reversed", and saying so would misstate
        // the customer's balance.
        if (! $return->isFullReturn()) {
            return;
        }

        $sale = $return->returnable_type::find($return->returnable_id);
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
