<?php

namespace App\Services\Finance\BankReconciliation;

use App\Models\Finance\BankAccount;
use App\Models\Finance\BankReconciliation;
use App\Models\Finance\BankStatement;
use App\Models\Finance\BankStatementLine;
use App\Models\Finance\JournalBatch;
use App\Models\Finance\JournalLine;
use App\Models\Finance\Payment;
use App\Models\Finance\Receipt;
use App\Models\User;
use App\Services\Numbering\NumberingService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Bank reconciliation lifecycle + arithmetic.
 *
 * The reconciliation closes the books for a bank account at a moment
 * in time. The model:
 *
 *   GL closing balance for bank account X at period_to
 *     + outstanding withdrawals (cheques we wrote but bank hasn't cleared)
 *     - outstanding deposits     (deposits we recorded but bank hasn't credited)
 *     + bank-only debits         (bank fees, charges, errors against us)
 *     - bank-only credits        (interest, refunds, errors in our favour)
 *   = bank's stated closing balance
 *
 * Rearranged, the difference must be zero:
 *
 *   diff = bank_closing
 *        - book_closing
 *        - outstanding_deposits
 *        + outstanding_withdrawals
 *        - bank_only_credits
 *        + bank_only_debits
 *
 *   == 0 → reconcile.
 *   != 0 → either keep matching, or flag with a reason.
 *
 * The service stores both raw inputs and the computed difference.
 */
class BankReconciliationService
{
    public function __construct(
        protected NumberingService $numbering,
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    /**
     * Create a draft reconciliation for a bank account + period.
     *
     * Idempotent: if a non-archived reconciliation already exists for
     * the same (bank_account_id, period_to), return it.
     *
     * Computes opening balance per books from the most recent prior
     * reconciliation OR the bank account's opening balance if this is
     * the first one.
     */
    public function createDraft(
        BankAccount $bankAccount,
        string $periodFrom,
        string $periodTo,
        string $closingBalancePerBank,
        User $createdBy,
        ?string $openingBalancePerBank = null,
    ): BankReconciliation {
        $periodFromCarbon = CarbonImmutable::parse($periodFrom);
        $periodToCarbon   = CarbonImmutable::parse($periodTo);
        if ($periodToCarbon->lt($periodFromCarbon)) {
            throw new \InvalidArgumentException('period_to is before period_from.');
        }
        if (! $bankAccount->isUsable()) {
            throw new \DomainException(
                "Bank account {$bankAccount->id} is not usable."
            );
        }

        return DB::transaction(function () use (
            $bankAccount, $periodFromCarbon, $periodToCarbon,
            $closingBalancePerBank, $openingBalancePerBank, $createdBy,
        ) {
            // Existing recon for this period?
            $existing = BankReconciliation::query()
                ->where('bank_account_id', $bankAccount->id)
                ->where('period_to', $periodToCarbon->toDateString())
                ->whereIn('status', [
                    BankReconciliation::STATUS_DRAFT,
                    BankReconciliation::STATUS_IN_PROGRESS,
                    BankReconciliation::STATUS_RECONCILED,
                    BankReconciliation::STATUS_FLAGGED,
                ])
                ->lockForUpdate()
                ->first();
            if ($existing) {
                if ($existing->isReconciled() || $existing->isFlagged()) {
                    throw new \DomainException(
                        "Reconciliation {$existing->reconciliation_number} for this period " .
                        "already exists in status '{$existing->status}'. Reopen it if you " .
                        'need to redo the close.'
                    );
                }
                return $existing;
            }

            // Determine opening balance per books.
            $openingBookBal = $this->computeOpeningBalancePerBooks(
                $bankAccount, $periodFromCarbon
            );
            // Opening per bank: use what user supplied; otherwise carry
            // forward from prior reconciliation; otherwise use bank
            // account's opening balance.
            if ($openingBalancePerBank === null) {
                $openingBalancePerBank = $this->priorClosingBalancePerBank(
                    $bankAccount, $periodFromCarbon
                );
            }

            $number = $this->numbering->next(
                sequenceType:   'bank_reconciliation',
                prefix:         'BREC',
                year:           (int) $periodToCarbon->format('Y'),
                organizationId: $bankAccount->organization_id,
                padding:        6,
            );

            $rec = BankReconciliation::create([
                'organization_id'          => $bankAccount->organization_id,
                'bank_account_id'          => $bankAccount->id,
                'reconciliation_number'    => $number,
                'period_from'              => $periodFromCarbon->toDateString(),
                'period_to'                => $periodToCarbon->toDateString(),
                'currency_code'            => $bankAccount->currency_code,
                'opening_balance_per_bank' => $openingBalancePerBank,
                'closing_balance_per_bank' => $closingBalancePerBank,
                'opening_balance_per_books'=> $openingBookBal,
                'closing_balance_per_books'=> '0.00',  // computed in refresh
                'outstanding_deposits'     => '0.00',
                'outstanding_withdrawals'  => '0.00',
                'bank_only_credits'        => '0.00',
                'bank_only_debits'         => '0.00',
                'reconciliation_difference'=> '0.00',
                'status'                   => BankReconciliation::STATUS_DRAFT,
                'created_by_user_id'       => $createdBy->id,
            ]);

            return $this->refreshArithmeticInternal($rec);
        });
    }

    /**
     * Recompute the reconciliation's arithmetic from current data.
     *
     * Uses currently-matched bank lines + currently-unmatched
     * payments/receipts in the period to compute the four adjustments
     * (outstanding deposits/withdrawals, bank-only credits/debits) and
     * the closing balance per books from the GL.
     */
    public function refreshArithmetic(BankReconciliation $rec): BankReconciliation
    {
        if (! $rec->canEdit()) {
            throw new \DomainException(
                "Cannot refresh: reconciliation in status '{$rec->status}'."
            );
        }
        return DB::transaction(fn () => $this->refreshArithmeticInternal($rec));
    }

    protected function refreshArithmeticInternal(BankReconciliation $rec): BankReconciliation
    {
        $bankAccount = $rec->bankAccount;
        if (! $bankAccount) {
            throw new \DomainException("Reconciliation {$rec->id} has no bank account.");
        }

        // Closing book balance = opening book balance + sum of GL postings to this account in period.
        $closingBook = $this->computeBookBalance(
            $bankAccount,
            CarbonImmutable::parse($rec->period_from),
            CarbonImmutable::parse($rec->period_to),
            (string) $rec->opening_balance_per_books,
        );

        // Outstanding deposits — receipts that ARE in our books but
        // don't have a bank_statement_line_id (uncleared on bank side
        // as of period_to).
        $outstandingDeposits = (string) Receipt::query()
            ->where('organization_id', $rec->organization_id)
            ->where('payment_method_id', '>', 0)  // any payment method
            ->where('status', Receipt::STATUS_POSTED)
            ->whereDate('receipt_date', '<=', $rec->period_to)
            ->whereNull('bank_statement_line_id')
            ->whereHas('paymentMethod', function ($q) use ($bankAccount) {
                // Best-effort: link via the bank account's GL account
                // through payment_methods.gl_account_hint OR a category-
                // specific resolver. Without a hard FK, we conservatively
                // include all bank-flagged methods. The user can flag
                // wrong inclusions via 'manually_resolved'.
                $q->where('is_bank', true);
            })
            ->sum('amount');
        $outstandingDeposits = $outstandingDeposits ? number_format((float) $outstandingDeposits, 2, '.', '') : '0.00';

        // Outstanding withdrawals — payments out of THIS bank account
        // that don't have a bank_statement_line_id yet.
        $outstandingWithdrawals = (string) Payment::query()
            ->where('organization_id', $rec->organization_id)
            ->where('source_account_id', $bankAccount->account_id)
            ->where('status', Payment::STATUS_POSTED)
            ->whereDate('payment_date', '<=', $rec->period_to)
            ->whereNull('bank_statement_line_id')
            ->sum('amount');
        $outstandingWithdrawals = $outstandingWithdrawals
            ? number_format((float) $outstandingWithdrawals, 2, '.', '')
            : '0.00';

        // Bank-only credits / debits — bank statement lines in this
        // period (any non-void statement of this bank account) that are
        // unmatched OR manually_resolved AND have no matched_id.
        $bankOnly = $this->computeBankOnly($rec);

        // The reconciliation difference.
        // diff = bank_close - book_close - outstanding_deposits + outstanding_withdrawals - bank_only_credits + bank_only_debits
        $diff = bcsub((string) $rec->closing_balance_per_bank, $closingBook, 2);
        $diff = bcsub($diff, $outstandingDeposits, 2);
        $diff = bcadd($diff, $outstandingWithdrawals, 2);
        $diff = bcsub($diff, $bankOnly['credits'], 2);
        $diff = bcadd($diff, $bankOnly['debits'], 2);

        $rec->update([
            'closing_balance_per_books' => $closingBook,
            'outstanding_deposits'      => $outstandingDeposits,
            'outstanding_withdrawals'   => $outstandingWithdrawals,
            'bank_only_credits'         => $bankOnly['credits'],
            'bank_only_debits'          => $bankOnly['debits'],
            'reconciliation_difference' => $diff,
        ]);

        return $rec->fresh();
    }

    /**
     * Close the reconciliation. Transitions draft/in_progress → reconciled.
     * Snapshots contributing entries.
     */
    public function reconcile(BankReconciliation $rec, User $reconciledBy): BankReconciliation
    {
        return DB::transaction(function () use ($rec, $reconciledBy) {
            $rec = BankReconciliation::lockForUpdate()->findOrFail($rec->id);
            if (! $rec->canEdit()) {
                throw new \DomainException(
                    "Cannot reconcile: status '{$rec->status}'. Reopen first if needed."
                );
            }

            $rec = $this->refreshArithmeticInternal($rec);
            if (! $rec->balancesAgree()) {
                throw new \DomainException(
                    'Cannot reconcile: difference is ' . $rec->reconciliation_difference .
                    '. Match more lines OR use Flag to close with a reason.'
                );
            }

            $snapshot = $this->buildSnapshot($rec);
            $rec->update([
                'status'                => BankReconciliation::STATUS_RECONCILED,
                'reconciled_at'         => now(),
                'reconciled_by_user_id' => $reconciledBy->id,
                'summary_snapshot'      => $snapshot,
            ]);

            // Forward-pointers: tag each cleared payment/receipt and each
            // statement contributing.
            $this->stampReconciliationPointers($rec);

            // Update bank_account cache.
            $rec->bankAccount->update([
                'last_reconciled_balance' => $rec->closing_balance_per_books,
                'last_reconciled_date'    => $rec->period_to,
            ]);

            return $rec->fresh();
        });
    }

    /**
     * Close the reconciliation with a flag (balances disagree but
     * we're closing anyway).
     */
    public function flag(BankReconciliation $rec, User $user, string $reason): BankReconciliation
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw new \InvalidArgumentException('Flag reason (≥5 chars) is required.');
        }

        return DB::transaction(function () use ($rec, $user, $reason) {
            $rec = BankReconciliation::lockForUpdate()->findOrFail($rec->id);
            if (! $rec->canEdit()) {
                throw new \DomainException(
                    "Cannot flag: status '{$rec->status}'."
                );
            }
            $rec = $this->refreshArithmeticInternal($rec);
            $snapshot = $this->buildSnapshot($rec);
            $rec->update([
                'status'             => BankReconciliation::STATUS_FLAGGED,
                'flagged_at'         => now(),
                'flagged_by_user_id' => $user->id,
                'flag_reason'        => $reason,
                'summary_snapshot'   => $snapshot,
            ]);

            // Flag also stamps pointers so payments/receipts know which
            // recon they're filed under. Note: flagged DOES lock just
            // like reconciled — flag is "we're done with this period",
            // not "leave it open".
            $this->stampReconciliationPointers($rec);

            return $rec->fresh();
        });
    }

    /**
     * Reopen a reconciled or flagged reconciliation. Releases the
     * period-freeze lock.
     */
    public function reopen(BankReconciliation $rec, User $user, string $reason): BankReconciliation
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw new \InvalidArgumentException('Reopen reason (≥5 chars) is required.');
        }
        return DB::transaction(function () use ($rec, $user, $reason) {
            $rec = BankReconciliation::lockForUpdate()->findOrFail($rec->id);
            if (! in_array($rec->status, [
                BankReconciliation::STATUS_RECONCILED,
                BankReconciliation::STATUS_FLAGGED,
            ], true)) {
                throw new \DomainException(
                    "Cannot reopen: status '{$rec->status}'."
                );
            }

            $newNotes = trim(($rec->notes ?? '') . "\n\n[Reopened by {$user->name} on " . now()->toIso8601String() . "] {$reason}");

            $rec->update([
                'status'                => BankReconciliation::STATUS_IN_PROGRESS,
                'reconciled_at'         => null,
                'reconciled_by_user_id' => null,
                'flagged_at'            => null,
                'flagged_by_user_id'    => null,
                'flag_reason'           => null,
                'notes'                 => $newNotes,
            ]);

            // Clear bank_reconciliation_id forward-pointers so newly
            // posted payments/receipts can re-bind freely.
            Payment::where('bank_reconciliation_id', $rec->id)
                ->update(['bank_reconciliation_id' => null]);
            Receipt::where('bank_reconciliation_id', $rec->id)
                ->update(['bank_reconciliation_id' => null]);
            BankStatement::where('bank_reconciliation_id', $rec->id)
                ->update(['bank_reconciliation_id' => null]);
            JournalBatch::where('bank_reconciliation_id', $rec->id)
                ->update(['bank_reconciliation_id' => null]);

            return $rec->fresh();
        });
    }

    /**
     * Archive a closed reconciliation.
     */
    public function archive(BankReconciliation $rec, User $user, string $reason): BankReconciliation
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('Archive reason is required.');
        }
        return DB::transaction(function () use ($rec, $user, $reason) {
            $rec = BankReconciliation::lockForUpdate()->findOrFail($rec->id);
            if (! in_array($rec->status, [
                BankReconciliation::STATUS_RECONCILED,
                BankReconciliation::STATUS_FLAGGED,
            ], true)) {
                throw new \DomainException(
                    "Cannot archive: status '{$rec->status}'. Only reconciled or flagged can be archived."
                );
            }
            $rec->update([
                'status'              => BankReconciliation::STATUS_ARCHIVED,
                'archived_at'         => now(),
                'archived_by_user_id' => $user->id,
                'archive_reason'      => $reason,
            ]);
            return $rec->fresh();
        });
    }

    /**
     * Public helper used by Payment / Receipt reversal services.
     * Returns the locking reconciliations, if any.
     *
     * @return array<int, BankReconciliation>
     */
    public function lockingReconciliations(string $matchedType, int $matchedId): array
    {
        $col = match ($matchedType) {
            BankStatementLine::MATCHED_TYPE_PAYMENT => 'bank_reconciliation_id',
            BankStatementLine::MATCHED_TYPE_RECEIPT => 'bank_reconciliation_id',
            default                                  => null,
        };
        if (! $col) return [];

        $modelClass = match ($matchedType) {
            BankStatementLine::MATCHED_TYPE_PAYMENT => Payment::class,
            BankStatementLine::MATCHED_TYPE_RECEIPT => Receipt::class,
        };

        $row = $modelClass::find($matchedId);
        if (! $row || ! $row->bank_reconciliation_id) return [];

        $rec = BankReconciliation::find($row->bank_reconciliation_id);
        if (! $rec) return [];
        if (! $rec->isLocked() && ! $rec->isFlagged()) return [];

        return [$rec];
    }

    /* internals */

    /**
     * Sum of GL postings against the bank account's GL account in
     * the open period [from, to], using opening as the carry-in.
     *
     * Bank account is an asset → debit increases, credit decreases.
     */
    protected function computeBookBalance(
        BankAccount $bankAccount,
        CarbonImmutable $from,
        CarbonImmutable $to,
        string $opening,
    ): string {
        $row = JournalLine::query()
            ->whereHas('journalBatch', function ($q) use ($from, $to, $bankAccount) {
                $q->where('organization_id', $bankAccount->organization_id)
                  ->where('status', JournalBatch::STATUS_POSTED)
                  ->whereBetween('posting_date', [$from->toDateString(), $to->toDateString()]);
            })
            ->where('account_id', $bankAccount->account_id)
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->first();

        $totalDebit  = $row->total_debit  ? (string) $row->total_debit  : '0.00';
        $totalCredit = $row->total_credit ? (string) $row->total_credit : '0.00';
        // Asset: balance = opening + debit - credit.
        $closing = bcadd($opening, $totalDebit, 2);
        $closing = bcsub($closing, $totalCredit, 2);
        return number_format((float) $closing, 2, '.', '');
    }

    /**
     * Compute opening book balance for a new reconciliation by carrying
     * forward from the most recently reconciled (or flagged) period of
     * this bank account, OR using bank_account.opening_balance if none.
     */
    protected function computeOpeningBalancePerBooks(
        BankAccount $bankAccount,
        CarbonImmutable $periodFrom,
    ): string {
        $prior = BankReconciliation::query()
            ->where('bank_account_id', $bankAccount->id)
            ->whereIn('status', [
                BankReconciliation::STATUS_RECONCILED,
                BankReconciliation::STATUS_FLAGGED,
                BankReconciliation::STATUS_ARCHIVED,
            ])
            ->whereDate('period_to', '<', $periodFrom->toDateString())
            ->orderByDesc('period_to')
            ->first();

        if ($prior) {
            return (string) $prior->closing_balance_per_books;
        }
        return number_format((float) $bankAccount->opening_balance, 2, '.', '');
    }

    protected function priorClosingBalancePerBank(
        BankAccount $bankAccount,
        CarbonImmutable $periodFrom,
    ): string {
        $prior = BankReconciliation::query()
            ->where('bank_account_id', $bankAccount->id)
            ->whereIn('status', [
                BankReconciliation::STATUS_RECONCILED,
                BankReconciliation::STATUS_FLAGGED,
                BankReconciliation::STATUS_ARCHIVED,
            ])
            ->whereDate('period_to', '<', $periodFrom->toDateString())
            ->orderByDesc('period_to')
            ->first();
        if ($prior) {
            return (string) $prior->closing_balance_per_bank;
        }
        return number_format((float) $bankAccount->opening_balance, 2, '.', '');
    }

    /**
     * Bank-only credits/debits = sum of statement lines in the period
     * for this account that are unmatched (neither matched, manually-
     * resolved, nor matched-to-journal). These represent transactions
     * the bank knows about that we don't (yet).
     *
     * @return array{credits: string, debits: string}
     */
    protected function computeBankOnly(BankReconciliation $rec): array
    {
        $row = BankStatementLine::query()
            ->whereHas('bankStatement', function ($q) use ($rec) {
                $q->where('bank_account_id', $rec->bank_account_id)
                  ->where('organization_id', $rec->organization_id)
                  ->where('status', '!=', BankStatement::STATUS_VOID)
                  ->whereDate('period_to', '<=', $rec->period_to)
                  ->whereDate('period_from', '>=', $rec->period_from);
            })
            ->where('match_status', BankStatementLine::STATUS_UNMATCHED)
            ->selectRaw('SUM(credit_amount) as credits, SUM(debit_amount) as debits')
            ->first();

        return [
            'credits' => $row && $row->credits
                ? number_format((float) $row->credits, 2, '.', '')
                : '0.00',
            'debits'  => $row && $row->debits
                ? number_format((float) $row->debits, 2, '.', '')
                : '0.00',
        ];
    }

    /**
     * Snapshot the contributing entries at close, for reproducible reporting.
     */
    protected function buildSnapshot(BankReconciliation $rec): array
    {
        $bankAccount = $rec->bankAccount;

        $matched = BankStatementLine::query()
            ->whereIn('match_status', [
                BankStatementLine::STATUS_MATCHED,
                BankStatementLine::STATUS_MATCHED_TO_JOURNAL,
            ])
            ->whereHas('bankStatement', function ($q) use ($rec) {
                $q->where('bank_account_id', $rec->bank_account_id)
                  ->whereDate('period_to', '<=', $rec->period_to)
                  ->whereDate('period_from', '>=', $rec->period_from);
            })
            ->get();

        $clearedPayments = [];
        $clearedReceipts = [];
        $clearedJournals = [];
        foreach ($matched as $line) {
            $entry = [
                'bsl_id'     => $line->id,
                'value_date' => $line->value_date->toDateString(),
                'amount'     => $line->absoluteAmount(),
            ];
            if ($line->matched_type === BankStatementLine::MATCHED_TYPE_PAYMENT) {
                $p = Payment::find($line->matched_id);
                if ($p) {
                    $clearedPayments[] = array_merge($entry, [
                        'id'             => $p->id,
                        'payment_number' => $p->payment_number,
                    ]);
                }
            } elseif ($line->matched_type === BankStatementLine::MATCHED_TYPE_RECEIPT) {
                $r = Receipt::find($line->matched_id);
                if ($r) {
                    $clearedReceipts[] = array_merge($entry, [
                        'id'             => $r->id,
                        'receipt_number' => $r->receipt_number,
                    ]);
                }
            } elseif ($line->matched_type === BankStatementLine::MATCHED_TYPE_JOURNAL_LINE) {
                $jb = JournalBatch::find($line->matched_id);
                if ($jb) {
                    $clearedJournals[] = array_merge($entry, [
                        'id'        => $jb->id,
                        'reference' => $jb->reference ?? null,
                    ]);
                }
            }
        }

        $bankOnly = BankStatementLine::query()
            ->whereHas('bankStatement', function ($q) use ($rec) {
                $q->where('bank_account_id', $rec->bank_account_id)
                  ->whereDate('period_to', '<=', $rec->period_to)
                  ->whereDate('period_from', '>=', $rec->period_from);
            })
            ->where('match_status', BankStatementLine::STATUS_UNMATCHED)
            ->get(['id', 'value_date', 'description', 'debit_amount', 'credit_amount'])
            ->map(fn ($l) => [
                'bsl_id'      => $l->id,
                'value_date'  => $l->value_date->toDateString(),
                'description' => $l->description,
                'debit'       => (string) $l->debit_amount,
                'credit'      => (string) $l->credit_amount,
            ])->all();

        $outstandingDeposits = Receipt::query()
            ->where('organization_id', $rec->organization_id)
            ->where('status', Receipt::STATUS_POSTED)
            ->whereDate('receipt_date', '<=', $rec->period_to)
            ->whereNull('bank_statement_line_id')
            ->whereHas('paymentMethod', fn ($q) => $q->where('is_bank', true))
            ->get(['id', 'receipt_number', 'amount', 'receipt_date'])
            ->map(fn ($r) => [
                'id'             => $r->id,
                'type'           => 'receipt',
                'receipt_number' => $r->receipt_number,
                'amount'         => (string) $r->amount,
                'date'           => $r->receipt_date?->toDateString(),
            ])->all();

        $outstandingWithdrawals = Payment::query()
            ->where('organization_id', $rec->organization_id)
            ->where('source_account_id', $bankAccount->account_id)
            ->where('status', Payment::STATUS_POSTED)
            ->whereDate('payment_date', '<=', $rec->period_to)
            ->whereNull('bank_statement_line_id')
            ->get(['id', 'payment_number', 'amount', 'payment_date'])
            ->map(fn ($p) => [
                'id'             => $p->id,
                'type'           => 'payment',
                'payment_number' => $p->payment_number,
                'amount'         => (string) $p->amount,
                'date'           => $p->payment_date?->toDateString(),
            ])->all();

        return [
            'as_of'                    => now()->toIso8601String(),
            'cleared_payments'         => $clearedPayments,
            'cleared_receipts'         => $clearedReceipts,
            'cleared_journal_lines'    => $clearedJournals,
            'outstanding_deposits'     => $outstandingDeposits,
            'outstanding_withdrawals'  => $outstandingWithdrawals,
            'bank_only_lines'          => $bankOnly,
        ];
    }

    /**
     * After a recon closes, stamp the recon id into every contributing
     * row so reverse() guards have a single column to look at.
     */
    protected function stampReconciliationPointers(BankReconciliation $rec): void
    {
        // For payments/receipts, the link is via the bank_statement_line.
        $clearedPaymentIds = collect($rec->summary_snapshot['cleared_payments'] ?? [])
            ->pluck('id')->all();
        $clearedReceiptIds = collect($rec->summary_snapshot['cleared_receipts'] ?? [])
            ->pluck('id')->all();
        $clearedJournalIds = collect($rec->summary_snapshot['cleared_journal_lines'] ?? [])
            ->pluck('id')->all();

        if (! empty($clearedPaymentIds)) {
            Payment::whereIn('id', $clearedPaymentIds)
                ->update(['bank_reconciliation_id' => $rec->id]);
        }
        if (! empty($clearedReceiptIds)) {
            Receipt::whereIn('id', $clearedReceiptIds)
                ->update(['bank_reconciliation_id' => $rec->id]);
        }
        if (! empty($clearedJournalIds)) {
            JournalBatch::whereIn('id', $clearedJournalIds)
                ->update(['bank_reconciliation_id' => $rec->id]);
        }

        // Tag any non-void statement intersecting the period.
        BankStatement::query()
            ->where('bank_account_id', $rec->bank_account_id)
            ->where('status', '!=', BankStatement::STATUS_VOID)
            ->whereDate('period_to', '<=', $rec->period_to)
            ->whereDate('period_from', '>=', $rec->period_from)
            ->update(['bank_reconciliation_id' => $rec->id]);
    }
}
