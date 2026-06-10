<?php

namespace App\Services\Finance;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Posting\PostingEngine;
use App\Models\Finance\JournalBatch;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Reverses a posted journal batch by creating a NEW batch with mirrored
 * (debit↔credit-swapped) lines, then linking the original to the reversal.
 *
 * Why a separate reversing batch instead of "undoing" the original?
 *   - Audit trail. The original entry stays in place forever; the reversal
 *     is a separate event with its own date, user, reason.
 *   - Period integrity. Reversals usually post to the CURRENT period, not
 *     the original's period. If the original period is closed, you can't
 *     touch it — but you can still reverse via a current-period entry.
 *   - This is the universally accepted ERP pattern.
 *
 * Reversal posting date:
 *   By default, today's date. Caller can override (e.g. to reverse into
 *   the same period as the original, if it's still open).
 *
 * Idempotency:
 *   The reversal batch gets its own idempotency_key — '{original_idempotency_key}:reversal'.
 *   This way, a duplicate-reverse attempt is caught the same way duplicate
 *   posts are. For manual original batches (no idempotency_key), the reversal
 *   also has none.
 *
 * Restrictions:
 *   - Cannot reverse a batch that's already reversed.
 *   - Cannot reverse a draft batch (only posted).
 */
class JournalReversalService
{
    public function __construct(protected PostingEngine $postingEngine)
    {
    }

    /**
     * Reverse a posted batch.
     *
     * @param  JournalBatch  $original         The posted batch to reverse.
     * @param  User  $reverser                 The user causing the reversal.
     * @param  string  $reason                 Required reason — recorded on both sides.
     * @param  \DateTimeInterface|null  $reversalDate  Defaults to today.
     * @param  bool  $bypassSoftLock           True if caller has bypass_period_lock
     *                                          and the reversal date falls before a
     *                                          soft lock.
     * @return JournalBatch  The newly-created reversal batch (posted).
     */
    public function reverse(
        JournalBatch $original,
        User $reverser,
        string $reason,
        ?\DateTimeInterface $reversalDate = null,
        bool $bypassSoftLock = false,
    ): JournalBatch {
        if ($original->reversed_by_batch_id !== null || $original->status === JournalBatch::STATUS_REVERSED) {
            $suffix = $original->reversed_by_batch_id
                ? " by batch #{$original->reversed_by_batch_id}"
                : '';
            throw new \DomainException(
                "Batch #{$original->id} is already reversed{$suffix}"
            );
        }

        if (! $original->isPosted()) {
            throw new \DomainException(
                "Cannot reverse batch #{$original->id}: status is '{$original->status}', expected 'posted'"
            );
        }

        $original->load(['journals.lines', 'postings']);

        $reversalDate ??= CarbonImmutable::now();

        // Build the reversal draft from the original.
        $reversalDraft = $this->buildReversalDraft($original, $reason, $reversalDate);

        return DB::transaction(function () use ($original, $reverser, $reason, $reversalDraft, $bypassSoftLock) {
            // Post the reversal via PostingEngine — full validation runs.
            $reversalBatch = $this->postingEngine->post($reversalDraft, $reverser, $bypassSoftLock);

            // Link the original to the reversal.
            $original->update([
                'status'              => JournalBatch::STATUS_REVERSED,
                'reversed_by_batch_id'=> $reversalBatch->id,
                'reversed_by_user_id' => $reverser->id,
                'reversed_at'         => now(),
                'reversal_reason'     => $reason,
            ]);

            // Mark the original's journals as reversed too.
            $original->journals()->update([
                'status' => \App\Models\Finance\Journal::STATUS_REVERSED,
            ]);

            return $reversalBatch->fresh(['journals.lines']);
        });
    }

    /**
     * Build a JournalBatchDraft that mirrors the original but with debits
     * and credits swapped on every line.
     */
    protected function buildReversalDraft(
        JournalBatch $original,
        string $reason,
        \DateTimeInterface $reversalDate,
    ): JournalBatchDraft {
        $reversedJournals = [];

        foreach ($original->journals as $origJournal) {
            $reversedLines = [];

            foreach ($origJournal->lines as $origLine) {
                // Swap debit and credit. The original's debit becomes the
                // reversal's credit and vice versa. All other fields preserved.
                $reversedLines[] = new JournalLineDraft(
                    accountId:       (int) $origLine->account_id,
                    debit:           (string) $origLine->credit,   // SWAPPED
                    credit:          (string) $origLine->debit,    // SWAPPED
                    description:     $origLine->description ? "REVERSAL: {$origLine->description}" : "REVERSAL of original line",
                    currencyCode:    $origLine->currency_code,
                    exchangeRate:    (string) $origLine->exchange_rate,
                    debitTc:         (string) $origLine->credit_tc, // SWAPPED
                    creditTc:        (string) $origLine->debit_tc,  // SWAPPED
                    costCentreId:    $origLine->cost_centre_id,
                    salesChannelId:  $origLine->sales_channel_id,
                    counterpartyId:  $origLine->counterparty_id,
                    taxCodeId:       $origLine->tax_code_id,
                    paymentMethodId: $origLine->payment_method_id,
                    structureId:     $origLine->structure_id,
                    roomId:          $origLine->room_id,
                    groupId:         $origLine->group_id,
                    sourceType:      $origLine->source_type,
                    sourceId:        $origLine->source_id,
                    reference:       'REVERSAL of journal #' . $origJournal->id,
                );
            }

            $reversedJournals[] = new JournalDraft(
                journalType:        $origJournal->journal_type,
                lines:              $reversedLines,
                reference:          'REV-' . ($origJournal->reference ?? $origJournal->journal_number),
                description:        "REVERSAL: {$reason}",
                currencyCode:       $origJournal->currency_code,
                exchangeRate:       (string) $origJournal->exchange_rate,
                sourceType:         $origJournal->source_type,
                sourceId:           $origJournal->source_id,
                reversesJournalId:  (int) $origJournal->id,
            );
        }

        // Idempotency key for the reversal — derived from the original's
        // posting record if present, otherwise null (manual reversal).
        $reversalIdempotencyKey = null;
        $reversalPostingPurpose = null;
        if ($original->postings->isNotEmpty()) {
            $origPosting = $original->postings->first();
            $reversalIdempotencyKey = $origPosting->idempotency_key . ':reversal';
            $reversalPostingPurpose = $origPosting->posting_purpose . '_reversal';
        }

        return new JournalBatchDraft(
            batchType:        'reversal',
            postingDate:      $reversalDate,
            journals:         $reversedJournals,
            description:      "REVERSAL of batch #{$original->id} ({$original->batch_number}): {$reason}",
            sourceModule:     $original->source_module,
            sourceType:       $original->source_type,
            sourceId:         $original->source_id,
            domainEventId:    null,
            idempotencyKey:   $reversalIdempotencyKey,
            postingPurpose:   $reversalPostingPurpose,
            subscriberClass:  null,
            reversesBatchId:  (int) $original->id,
        );
    }
}
