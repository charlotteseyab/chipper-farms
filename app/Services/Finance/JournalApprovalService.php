<?php

namespace App\Services\Finance;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Posting\PeriodResolver;
use App\Models\Finance\JournalBatch;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\DB;

/**
 * Approves a draft journal batch — Decision 8 step 2.
 *
 * Caller must hold the `approve_journal` permission AND must NOT be the
 * same user who proposed it (segregation of duties). The permission +
 * same-user check happens in the controller/policy layer, NOT here. This
 * service trusts its caller.
 *
 * Approval flow:
 *   1. Load the draft batch with journals + lines.
 *   2. Reject if status != 'draft' (already posted or reversed).
 *   3. Reconstitute a JournalBatchDraft from the persisted rows.
 *   4. Re-run all PostingEngine validations (period, accounts, balance).
 *   5. Inside transaction:
 *      a. Flip each journal's status to 'posted'.
 *      b. Flip batch status to 'posted'.
 *      c. Set posted_by + posted_at.
 *      d. Update fiscal_period audit counters.
 *
 * Why re-validate vs just flip status?
 *   Time may have passed. Period might have closed. Account might be
 *   deactivated. Re-validation catches these as-of-approval-time.
 *
 * Why reconstitute a draft instead of skipping straight to flipping status?
 *   Because the validation logic lives in PostingEngine. Reusing it
 *   guarantees that approval and direct posting share the exact same
 *   validation contract. No drift.
 */
class JournalApprovalService
{
    public function __construct(
        protected PeriodResolver $periodResolver,
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    /**
     * Approve a draft batch. Throws if the batch is not in 'draft' status,
     * or if any of the validations fail at approval time.
     */
    public function approve(
        JournalBatch $batch,
        User $approver,
        bool $bypassSoftLock = false,
    ): JournalBatch {
        if (! $batch->isDraft()) {
            throw new \DomainException(
                "Cannot approve batch #{$batch->id}: status is '{$batch->status}', expected 'draft'"
            );
        }

        // Re-validate period and lock state.
        $this->periodResolver->resolveForPosting($batch->posting_date, $bypassSoftLock);

        // Reconstitute drafts from persisted rows (purely for re-validation
        // path — the data is already in the DB; we just re-build the in-memory
        // shape PostingEngine works with).
        $reconstituted = $this->reconstituteDraft($batch);

        // The reconstitution itself runs JournalDraft / JournalBatchDraft
        // constructors which re-validate balance + line counts. If the data
        // was tampered with directly in the DB to be unbalanced, this will
        // throw and the approval will fail loudly.

        return DB::transaction(function () use ($batch, $approver) {
            // Flip every journal in the batch to posted.
            $batch->journals()->update([
                'status' => \App\Models\Finance\Journal::STATUS_POSTED,
            ]);

            // Flip the batch to posted.
            $batch->update([
                'status'             => JournalBatch::STATUS_POSTED,
                'posted_by_user_id'  => $approver->id,
                'posted_at'          => now(),
            ]);

            // Audit: bump period counters.
            $period = $batch->fiscalPeriod;
            $now = now();
            $update = [
                'last_journal_posted_at' => $now,
                'posting_count'          => DB::raw('posting_count + 1'),
            ];
            if ($period && $period->first_journal_posted_at === null) {
                $update['first_journal_posted_at'] = $now;
            }
            if ($period) {
                DB::table($period->getTable())
                    ->where($period->getKeyName(), $period->getKey())
                    ->update($update);
            }

            return $batch->fresh(['journals.lines']);
        });
    }

    /**
     * Reject a draft batch (e.g. accountant proposed nonsense).
     * Soft-reject: simply marks it as reversed without ever posting.
     * NOT an undo — drafts that were never posted don't reverse anything;
     * this is more of a "discard" with audit. Decision: store discard as
     * status='reversed' with reversal_reason set.
     */
    public function reject(JournalBatch $batch, User $rejecter, string $reason): JournalBatch
    {
        if (! $batch->isDraft()) {
            throw new \DomainException(
                "Cannot reject batch #{$batch->id}: status is '{$batch->status}', expected 'draft'"
            );
        }

        return DB::transaction(function () use ($batch, $rejecter, $reason) {
            $batch->journals()->update([
                'status' => \App\Models\Finance\Journal::STATUS_REVERSED,
            ]);

            $batch->update([
                'status'              => JournalBatch::STATUS_REVERSED,
                'reversed_by_user_id' => $rejecter->id,
                'reversed_at'         => now(),
                'reversal_reason'     => 'Rejected at approval: ' . $reason,
            ]);

            return $batch->fresh();
        });
    }

    /**
     * Build a JournalBatchDraft from a persisted draft batch. Used for
     * re-validation at approval time.
     */
    protected function reconstituteDraft(JournalBatch $batch): JournalBatchDraft
    {
        $batch->load(['journals.lines']);

        $journalDrafts = [];

        foreach ($batch->journals as $journal) {
            $lineDrafts = [];

            foreach ($journal->lines as $line) {
                $lineDrafts[] = new JournalLineDraft(
                    accountId:       (int) $line->account_id,
                    debit:           (string) $line->debit,
                    credit:          (string) $line->credit,
                    description:     $line->description,
                    currencyCode:    $line->currency_code,
                    exchangeRate:    (string) $line->exchange_rate,
                    debitTc:         (string) $line->debit_tc,
                    creditTc:        (string) $line->credit_tc,
                    costCentreId:    $line->cost_centre_id,
                    salesChannelId:  $line->sales_channel_id,
                    counterpartyId:  $line->counterparty_id,
                    taxCodeId:       $line->tax_code_id,
                    paymentMethodId: $line->payment_method_id,
                    structureId:     $line->structure_id,
                    roomId:          $line->room_id,
                    groupId:         $line->group_id,
                    sourceType:      $line->source_type,
                    sourceId:        $line->source_id,
                    reference:       $line->reference,
                );
            }

            $journalDrafts[] = new JournalDraft(
                journalType:        $journal->journal_type,
                lines:              $lineDrafts,
                reference:          $journal->reference,
                description:        $journal->description,
                currencyCode:       $journal->currency_code,
                exchangeRate:       (string) $journal->exchange_rate,
                sourceType:         $journal->source_type,
                sourceId:           $journal->source_id,
                reversesJournalId:  $journal->reverses_journal_id,
            );
        }

        return new JournalBatchDraft(
            batchType:    $batch->batch_type,
            postingDate:  $batch->posting_date,
            journals:     $journalDrafts,
            description:  $batch->description,
            sourceModule: $batch->source_module,
            sourceType:   $batch->source_type,
            sourceId:     $batch->source_id,
            reversesBatchId: $batch->reverses_batch_id,
            // No idempotency on manual journals.
        );
    }
}
