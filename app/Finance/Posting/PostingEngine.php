<?php

namespace App\Finance\Posting;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Exceptions\CurrencyMismatchException;
use App\Finance\Exceptions\DuplicatePostingException;
use App\Finance\Exceptions\InvalidAccountException;
use App\Models\Finance\Account;
use App\Models\Finance\Journal;
use App\Models\Finance\JournalBatch;
use App\Models\Finance\JournalLine;
use App\Models\Finance\JournalPosting;
use App\Models\User;
use App\Services\Numbering\NumberingService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The heart of the finance posting flow.
 *
 * Single public entry point: post(). Takes a JournalBatchDraft, validates
 * everything that requires database access, persists batch + journals +
 * lines + postings atomically, and returns the persisted JournalBatch.
 *
 * Responsibilities (in this order):
 *   1. Resolve fiscal_period for the batch posting_date.
 *   2. Check period status + period_lock_dates (delegated to PeriodResolver).
 *   3. Validate every account referenced — exists, active, same org,
 *      currency match, manual-journal-allowed if applicable.
 *   4. Pre-check idempotency_key uniqueness (fast-fail with DuplicatePostingException
 *      that exposes the existing batch).
 *   5. Open transaction.
 *   6. Allocate batch_number and journal_numbers via NumberingService.
 *   7. Insert journal_batches row in 'draft' status.
 *   8. Insert journals + journal_lines.
 *   9. Update journal totals.
 *   10. Insert journal_postings row (the idempotency record) — UNIQUE
 *       constraint here is the final defence-in-depth against double posting.
 *   11. Flip status to 'posted', set posted_by + posted_at on batch.
 *   12. Update fiscal_period audit columns (first_journal_posted_at,
 *       last_journal_posted_at, posting_count++).
 *   13. Commit.
 *
 * Why pre-check idempotency THEN have UNIQUE catch?
 *   The pre-check returns a clean DuplicatePostingException with the
 *   existing batch id for the caller. Without it, the only signal would
 *   be a raw QueryException with a unique-key violation message. The
 *   UNIQUE is the final guarantee against the race where two processes
 *   pre-check at the same time and both pass.
 *
 * Manual journals (idempotency_key === null):
 *   These are persisted in 'draft' status by ManualJournalService, NOT
 *   by post(). post() is for atomic-commit auto journals AND for the
 *   eventual JournalApprovalService which calls post() on a previously-
 *   drafted manual journal. The mode is signalled by $approvedManualBatch.
 */
class PostingEngine
{
    public function __construct(
        protected PeriodResolver $periodResolver,
        protected NumberingService $numbering,
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    /**
     * Post a draft batch atomically.
     *
     * @param  JournalBatchDraft  $draft  The batch to post.
     * @param  User|null  $postingUser    The user causing the post. Required for
     *                                    audit columns. For subscriber-driven
     *                                    posts, pass the user who triggered the
     *                                    source action; if absent, leave null
     *                                    and only system audit applies.
     * @param  bool  $bypassSoftLock      True if caller has bypass_period_lock
     *                                    permission. PostingEngine doesn't check
     *                                    the permission — caller does — but the
     *                                    flag is plumbed through to PeriodResolver.
     * @return JournalBatch                The persisted, posted batch.
     */
    public function post(
        JournalBatchDraft $draft,
        ?User $postingUser = null,
        bool $bypassSoftLock = false,
    ): JournalBatch {
        return DB::transaction(fn () => $this->postInTransaction($draft, $postingUser, $bypassSoftLock), 3);
    }

    private function postInTransaction(JournalBatchDraft $draft, ?User $postingUser, bool $bypassSoftLock): JournalBatch
    {
        // 1+2. Resolve period + check locks (throws if rejected).
        $period = $this->periodResolver->resolveForPosting($draft->postingDate, $bypassSoftLock);

        // 3. Validate every account referenced.
        $this->validateAccounts($draft);

        // 4. Pre-check idempotency.
        $organizationId = $this->currentOrganization->id();
        if ($draft->isAuto()) {
            $existing = JournalPosting::query()
                ->when(
                    $organizationId !== null,
                    fn ($q) => $q->where('organization_id', $organizationId),
                    fn ($q) => $q->whereNull('organization_id'),
                )
                ->where('idempotency_key', $draft->idempotencyKey)
                ->first();

            if ($existing) {
                throw new DuplicatePostingException(
                    idempotencyKey:   $draft->idempotencyKey,
                    existingBatchId:  (int) $existing->journal_batch_id,
                );
            }
        }

        // 5-13. Atomic persistence.
        try {
            return DB::transaction(function () use ($draft, $period, $postingUser, $organizationId) {
                $batch = $this->persistBatch($draft, $period, $postingUser, $organizationId);

                foreach ($draft->journals as $journalDraft) {
                    $this->persistJournal($journalDraft, $batch, $period, $organizationId);
                }

                if ($draft->isAuto()) {
                    $this->persistIdempotencyRecord($draft, $batch, $organizationId);
                }

                $this->markBatchPosted($batch, $postingUser);

                $this->updatePeriodAuditCounters($period, $batch);

                return $batch->fresh(['journals.lines']);
            });
        } catch (QueryException $e) {
            // Catch the UNIQUE constraint defence-in-depth violation. If we
            // got here, something raced past the pre-check. Translate to
            // a clean DuplicatePostingException.
            if ($draft->isAuto() && $this->isUniqueViolation($e)) {
                $existing = JournalPosting::query()
                    ->when(
                        $organizationId !== null,
                        fn ($q) => $q->where('organization_id', $organizationId),
                        fn ($q) => $q->whereNull('organization_id'),
                    )
                    ->where('idempotency_key', $draft->idempotencyKey)
                    ->first();

                if ($existing) {
                    throw new DuplicatePostingException(
                        idempotencyKey:  $draft->idempotencyKey,
                        existingBatchId: (int) $existing->journal_batch_id,
                    );
                }
            }
            throw $e;
        }
    }

    public function validateAccounts(JournalBatchDraft $draft): void
    {
        $organizationId = $this->currentOrganization->id();

        $accountIds = [];
        foreach ($draft->journals as $journal) {
            foreach ($journal->lines as $line) {
                $accountIds[$line->accountId] = true;
            }
        }

        $accounts = Account::query()
            ->whereIn('id', array_keys($accountIds))
            ->get()
            ->keyBy('id');

        foreach ($draft->journals as $journal) {
            foreach ($journal->lines as $line) {
                $account = $accounts->get($line->accountId);

                if (! $account) {
                    throw new InvalidAccountException(
                        accountId:   $line->accountId,
                        accountCode: null,
                        reason:      'account does not exist',
                    );
                }

                if ($organizationId !== null && $account->organization_id !== null && $account->organization_id !== $organizationId) {
                    throw new InvalidAccountException(
                        accountId:   $line->accountId,
                        accountCode: $account->code,
                        reason:      'account belongs to a different organization',
                    );
                }

                if (! $account->is_active) {
                    throw new InvalidAccountException(
                        accountId:   $line->accountId,
                        accountCode: $account->code,
                        reason:      'account is inactive',
                    );
                }

                // Currency consistency: line currency must equal account currency
                // OR line uses default GHS and account is GHS.
                $lineCurrency = $line->currencyCode ?? $journal->currencyCode;
                if ($account->currency_code !== $lineCurrency) {
                    throw new CurrencyMismatchException(
                        journalCurrency: $lineCurrency,
                        accountCurrency: $account->currency_code,
                        accountCode:     $account->code,
                    );
                }

                // Manual journals: refuse posting to control accounts that
                // disallow manual journal entries.
                if ($draft->isManual() && ! $account->allow_manual_journal) {
                    throw new InvalidAccountException(
                        accountId:   $line->accountId,
                        accountCode: $account->code,
                        reason:      'account does not accept manual journal entries (control account)',
                    );
                }
            }
        }
    }

    protected function persistBatch(
        JournalBatchDraft $draft,
        $period,
        ?User $postingUser,
        ?int $organizationId,
    ): JournalBatch {
        $batchNumber = $this->numbering->next(
            sequenceType:   'journal_batch',
            prefix:         'JB',
            year:           (int) $period->start_date->format('Y'),
            organizationId: $organizationId,
        );

        return JournalBatch::create([
            'organization_id'  => $organizationId,
            'batch_number'     => $batchNumber,
            'batch_type'       => $draft->batchType,
            'posting_date'     => CarbonImmutable::instance($draft->postingDate),
            'fiscal_period_id' => $period->id,
            'status'           => JournalBatch::STATUS_DRAFT,
            'description'      => $draft->description,
            'source_module'    => $draft->sourceModule,
            'source_type'      => $draft->sourceType,
            'source_id'        => $draft->sourceId,
            'domain_event_id'  => $draft->domainEventId,
            'reverses_batch_id'=> $draft->reversesBatchId,
            'proposed_by_user_id' => $postingUser?->id,
            'proposed_at'      => now(),
        ]);
    }

    protected function persistJournal(
        JournalDraft $journalDraft,
        JournalBatch $batch,
        $period,
        ?int $organizationId,
    ): Journal {
        $journalNumber = $this->numbering->next(
            sequenceType:   'journal',
            prefix:         'JV',
            year:           (int) $period->start_date->format('Y'),
            organizationId: $organizationId,
        );

        $journal = Journal::create([
            'organization_id'    => $organizationId,
            'journal_batch_id'   => $batch->id,
            'journal_number'     => $journalNumber,
            'journal_type'       => $journalDraft->journalType,
            'posting_date'       => $batch->posting_date,
            'fiscal_period_id'   => $period->id,
            'reference'          => $journalDraft->reference,
            'description'        => $journalDraft->description,
            'currency_code'      => $journalDraft->currencyCode,
            'exchange_rate'      => $journalDraft->exchangeRate,
            'total_debit'        => $journalDraft->totalDebit(),
            'total_credit'       => $journalDraft->totalCredit(),
            'total_debit_tc'     => $journalDraft->totalDebitTc(),
            'total_credit_tc'    => $journalDraft->totalCreditTc(),
            'status'             => Journal::STATUS_DRAFT,
            'source_type'        => $journalDraft->sourceType,
            'source_id'          => $journalDraft->sourceId,
            'reverses_journal_id'=> $journalDraft->reversesJournalId,
        ]);

        // Persist lines.
        $lineNumber = 1;
        foreach ($journalDraft->lines as $lineDraft) {
            JournalLine::create($lineDraft->toPersistedArray(
                journalId:        $journal->id,
                lineNumber:       $lineNumber++,
                organizationId:   $organizationId,
                defaultCurrency:  $journalDraft->currencyCode,
                defaultRate:      $journalDraft->exchangeRate,
            ));
        }

        // Flip journal status to posted (after all lines are in, satisfying
        // the balanced-when-posted CHECK).
        $journal->update(['status' => Journal::STATUS_POSTED]);

        return $journal;
    }

    protected function persistIdempotencyRecord(
        JournalBatchDraft $draft,
        JournalBatch $batch,
        ?int $organizationId,
    ): JournalPosting {
        return JournalPosting::create([
            'organization_id'  => $organizationId,
            'idempotency_key'  => $draft->idempotencyKey,
            'journal_batch_id' => $batch->id,
            'domain_event_id'  => $draft->domainEventId,
            'posting_purpose'  => $draft->postingPurpose,
            'subscriber_class' => $draft->subscriberClass,
        ]);
    }

    protected function markBatchPosted(JournalBatch $batch, ?User $postingUser): void
    {
        $batch->update([
            'status'             => JournalBatch::STATUS_POSTED,
            'posted_by_user_id'  => $postingUser?->id,
            'posted_at'          => now(),
        ]);
    }

    protected function updatePeriodAuditCounters($period, JournalBatch $batch): void
    {
        $now = now();

        $update = [
            'last_journal_posted_at' => $now,
            'posting_count'          => DB::raw('posting_count + 1'),
        ];

        if ($period->first_journal_posted_at === null) {
            $update['first_journal_posted_at'] = $now;
        }

        DB::table($period->getTable())
            ->where($period->getKeyName(), $period->getKey())
            ->update($update);
    }

    protected function isUniqueViolation(QueryException $e): bool
    {
        // SQLSTATE 23000 = integrity constraint violation. The driver-specific
        // error code 1062 (MySQL) or 19 (SQLite) narrows further but checking
        // SQLSTATE alone is sufficient for portability.
        $sqlState = $e->errorInfo[0] ?? null;
        return $sqlState === '23000' || $sqlState === '23505';
        // 23505 is Postgres unique violation.
    }
}
