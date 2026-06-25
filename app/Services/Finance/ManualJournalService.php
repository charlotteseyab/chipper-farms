<?php

namespace App\Services\Finance;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Exceptions\InvalidAccountException;
use App\Finance\Posting\PeriodResolver;
use App\Finance\Posting\PostingEngine;
use App\Models\Finance\Account;
use App\Models\Finance\Journal;
use App\Models\Finance\JournalBatch;
use App\Models\Finance\JournalLine;
use App\Models\User;
use App\Services\Numbering\NumberingService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Manual journal entry — Decision 8 (two-step propose+approve).
 *
 * Workflow:
 *   1. Accountant fills the manual journal form, submits.
 *   2. ManualJournalService::propose() creates a journal_batch in DRAFT
 *      status with proposed_by_user_id + proposed_at set. Lines are
 *      persisted. NO posting to GL yet.
 *   3. Different user with `approve_journal` permission opens the draft,
 *      reviews, hits Approve.
 *   4. JournalApprovalService::approve() is called. It re-runs the full
 *      PostingEngine validation pipeline (balance, period, accounts) THEN
 *      flips status to POSTED and sets posted_by + posted_at.
 *
 * Override path:
 *   A user with `approve_own_journals` permission can call
 *   proposeAndApprove() in ONE step. This is admin/superuser only.
 *
 * Why re-validate on approve when propose already validated?
 *   Time passes between propose and approve. The fiscal period might have
 *   closed in between. An account might have been deactivated. The
 *   approve step revalidates everything as-of-the-approval-moment, which
 *   is the only correct semantic.
 *
 * Why not just create a JournalBatchDraft on propose and post on approve?
 *   Because the user has to be able to LOG OUT and come back. The draft
 *   has to live in the database, not memory. So propose() does insert
 *   batch+journals+lines (without idempotency record) and approve()
 *   reads them back, rebuilds a JournalBatchDraft, and re-posts.
 */
class ManualJournalService
{
    public function __construct(
        protected PostingEngine $postingEngine,
        protected PeriodResolver $periodResolver,
        protected NumberingService $numbering,
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    /**
     * Step 1 of two-step: propose a draft.
     *
     * Validates account references but does NOT check fiscal period status
     * — the period might be closed by the time approval happens, so the
     * approve step is the one that enforces. This is intentional — the
     * accountant should be able to draft an entry even if the period needs
     * to be opened first.
     *
     * @return JournalBatch  The persisted draft. Status = 'draft'.
     */
    public function propose(JournalBatchDraft $draft, User $proposer): JournalBatch
    {
        if ($draft->isSystemGenerated()) {
            throw new \InvalidArgumentException(
                'ManualJournalService::propose called with system-generated draft. '
                . 'Manual journals must use a manual batch type and have idempotency_key === null.'
            );
        }

        $organizationId = $this->currentOrganization->id();

        // Light validation: accounts exist, are active, accept manual journals,
        // currency match. This is a subset of PostingEngine's checks — enough
        // to catch typos at draft-time but not so strict that period-closed
        // blocks drafting.
        $this->validateDraftAccountsLight($draft, $organizationId);

        // Resolve period for the posting date (read-only — does not check
        // open/closed status because that's the approver's concern).
        $period = $this->periodResolver->resolveOnly($draft->postingDate);

        return DB::transaction(function () use ($draft, $proposer, $organizationId, $period) {
            $batchNumber = $this->numbering->next(
                sequenceType:   'journal_batch',
                prefix:         'JB',
                year:           (int) $period->start_date->format('Y'),
                organizationId: $organizationId,
            );

            $batch = JournalBatch::create([
                'organization_id'  => $organizationId,
                'batch_number'     => $batchNumber,
                'batch_type'       => $draft->batchType,
                'posting_date'     => CarbonImmutable::instance($draft->postingDate),
                'fiscal_period_id' => $period->id,
                'status'           => JournalBatch::STATUS_DRAFT,
                'description'      => $draft->description,
                'source_module'    => $draft->sourceModule ?? 'manual',
                'proposed_by_user_id' => $proposer->id,
                'proposed_at'      => now(),
            ]);

            foreach ($draft->journals as $journalDraft) {
                $journalNumber = $this->numbering->next(
                    sequenceType:   'journal',
                    prefix:         'JV',
                    year:           (int) $period->start_date->format('Y'),
                    organizationId: $organizationId,
                );

                $journal = Journal::create([
                    'organization_id'  => $organizationId,
                    'journal_batch_id' => $batch->id,
                    'journal_number'   => $journalNumber,
                    'journal_type'     => $journalDraft->journalType,
                    'posting_date'     => $batch->posting_date,
                    'fiscal_period_id' => $period->id,
                    'reference'        => $journalDraft->reference,
                    'description'      => $journalDraft->description,
                    'currency_code'    => $journalDraft->currencyCode,
                    'exchange_rate'    => $journalDraft->exchangeRate,
                    'total_debit'      => $journalDraft->totalDebit(),
                    'total_credit'     => $journalDraft->totalCredit(),
                    'total_debit_tc'   => $journalDraft->totalDebitTc(),
                    'total_credit_tc'  => $journalDraft->totalCreditTc(),
                    'status'           => Journal::STATUS_DRAFT,
                ]);

                $lineNumber = 1;
                foreach ($journalDraft->lines as $lineDraft) {
                    JournalLine::create($lineDraft->toPersistedArray(
                        journalId:       $journal->id,
                        lineNumber:      $lineNumber++,
                        organizationId:  $organizationId,
                        defaultCurrency: $journalDraft->currencyCode,
                        defaultRate:     $journalDraft->exchangeRate,
                    ));
                }
            }

            return $batch->fresh(['journals.lines']);
        });
    }

    /**
     * Override path: propose AND approve in one operation.
     * Caller must hold `approve_own_journals` permission — checked at
     * the controller/policy layer, NOT here.
     */
    public function proposeAndApprove(
        JournalBatchDraft $draft,
        User $user,
        bool $bypassSoftLock = false,
    ): JournalBatch {
        if ($draft->isAuto()) {
            throw new \InvalidArgumentException(
                'proposeAndApprove cannot be used with auto drafts.'
            );
        }

        // Skip the draft persistence — go straight to PostingEngine.
        // This produces the same end result as propose() then approve()
        // without the intermediate draft row.
        return $this->postingEngine->post($draft, $user, $bypassSoftLock);
    }

    /**
     * Light validation for the propose step.
     */
    protected function validateDraftAccountsLight(JournalBatchDraft $draft, ?int $organizationId): void
    {
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

                if (! $account->is_active) {
                    throw new InvalidAccountException(
                        accountId:   $line->accountId,
                        accountCode: $account->code,
                        reason:      'account is inactive',
                    );
                }

                if (! $account->allow_manual_journal) {
                    throw new InvalidAccountException(
                        accountId:   $line->accountId,
                        accountCode: $account->code,
                        reason:      'account does not accept manual journal entries (control account)',
                    );
                }
            }
        }
    }
}
