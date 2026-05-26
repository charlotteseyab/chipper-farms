<?php

namespace App\Services\Finance\BankReconciliation;

use App\Models\Finance\BankAccount;
use App\Models\Finance\BankStatement;
use App\Models\Finance\BankStatementLine;
use App\Models\Finance\JournalBatch;
use App\Models\Finance\Payment;
use App\Models\Finance\Receipt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Matches imported bank statement lines against our payments,
 * receipts, and (rarely) journal batches.
 *
 * Strategy stack — first hit wins, processed per-line:
 *
 *   1. EXACT REFERENCE
 *      Bank ref or counterparty_reference matches our
 *      payment.external_reference / receipt.external_reference exactly
 *      (case-insensitive trimmed). One candidate → matched. Multiple
 *      candidates → duplicate.
 *
 *   2. AMOUNT + DATE PROXIMITY
 *      For unmatched lines, look for our docs with same absolute
 *      amount, same direction (debit ↔ payment, credit ↔ receipt),
 *      same source bank account, and date within ±5 days. One
 *      candidate → matched. More than one → duplicate.
 *
 *   3. DESCRIPTIVE EMBEDDED REFERENCE
 *      The bank's `description` often contains our payment number
 *      verbatim ("PMT REF P-2026-001234"). For unmatched lines, do
 *      a pattern search inside description against our payment_number
 *      and receipt_number. One candidate → matched.
 *
 * Lines that already have a status of `manually_resolved`, `matched`,
 * or `matched_to_journal` are skipped on subsequent passes.
 *
 * Side-effects of a match:
 *   - bank_statement_line: set match_status, matched_type, matched_id,
 *     matched_at, matched_by_user_id, amount_difference
 *   - target document: set bank_statement_line_id (forward pointer)
 *   - bank_statement: update its status if all lines are now matched
 *
 * Unmatching reverses all of the above. Both must be atomic.
 */
class BankStatementMatchingService
{
    /** Date proximity (days) for amount+date fallback. */
    public const DATE_PROXIMITY_DAYS = 5;

    /**
     * Run the matching engine over every open line of a statement.
     *
     * @return array{
     *   total: int, matched: int, duplicates: int, unmatched: int,
     *   skipped_resolved: int, skipped_already_matched: int
     * }
     */
    public function autoMatch(BankStatement $statement, User $user): array
    {
        if ($statement->isVoid()) {
            throw new \DomainException('Cannot match: statement is void.');
        }

        $stats = [
            'total'                   => 0,
            'matched'                 => 0,
            'duplicates'              => 0,
            'unmatched'               => 0,
            'skipped_resolved'        => 0,
            'skipped_already_matched' => 0,
        ];

        $bankAccount = $statement->bankAccount;

        // Iterate fresh from DB to keep this idempotent across re-runs.
        BankStatementLine::query()
            ->where('bank_statement_id', $statement->id)
            ->orderBy('line_number')
            ->each(function (BankStatementLine $line) use (&$stats, $user, $bankAccount, $statement) {
                $stats['total']++;

                if ($line->isManuallyResolved()) {
                    $stats['skipped_resolved']++;
                    return;
                }
                if ($line->isMatched()) {
                    $stats['skipped_already_matched']++;
                    return;
                }

                // For lines currently flagged as duplicate, re-run from
                // scratch — maybe a candidate has been removed since.
                if ($line->isDuplicate()) {
                    $line->duplicate_candidate_ids = null;
                    $line->match_status = BankStatementLine::STATUS_UNMATCHED;
                }

                $matched = $this->tryAllStrategies($line, $bankAccount, $statement, $user);
                if ($matched === 'matched') {
                    $stats['matched']++;
                } elseif ($matched === 'duplicate') {
                    $stats['duplicates']++;
                } else {
                    $stats['unmatched']++;
                }
            });

        $this->refreshStatementStatus($statement);

        return $stats;
    }

    /**
     * Tries strategies 1, 2, 3 in order. Returns 'matched', 'duplicate',
     * or 'unmatched'.
     */
    protected function tryAllStrategies(
        BankStatementLine $line,
        BankAccount $bankAccount,
        BankStatement $statement,
        User $user,
    ): string {
        // Strategy 1 — exact reference.
        $r = $this->matchByReference($line, $bankAccount);
        if ($r !== 'no_match') return $r;

        // Strategy 2 — amount + date proximity.
        $r = $this->matchByAmountDate($line, $bankAccount);
        if ($r !== 'no_match') return $r;

        // Strategy 3 — embedded ref in description.
        $r = $this->matchByEmbeddedRef($line, $bankAccount);
        if ($r !== 'no_match') return $r;

        // Persist as unmatched.
        $line->forceFill([
            'match_status' => BankStatementLine::STATUS_UNMATCHED,
            'matched_type' => null,
            'matched_id'   => null,
        ])->save();

        return 'unmatched';
    }

    /**
     * Strategy 1.
     *
     * Returns 'matched', 'duplicate', or 'no_match'.
     */
    protected function matchByReference(BankStatementLine $line, BankAccount $bankAccount): string
    {
        $refs = array_filter([
            trim((string) $line->bank_reference),
            trim((string) $line->counterparty_reference),
        ]);
        if (empty($refs)) return 'no_match';

        $isDebit = $line->isDebit();
        $modelClass = $isDebit ? Payment::class : Receipt::class;
        $matchedType = $isDebit
            ? BankStatementLine::MATCHED_TYPE_PAYMENT
            : BankStatementLine::MATCHED_TYPE_RECEIPT;

        $candidates = collect();
        foreach ($refs as $ref) {
            $found = $modelClass::query()
                ->where('organization_id', $bankAccount->organization_id)
                ->where('status', $modelClass::STATUS_POSTED)
                ->whereRaw('UPPER(TRIM(external_reference)) = UPPER(TRIM(?))', [$ref])
                ->when($isDebit,
                    fn ($q) => $q->where('source_account_id', $bankAccount->account_id))
                ->whereNull('bank_statement_line_id')  // not already matched elsewhere
                ->get();
            $candidates = $candidates->merge($found);
        }
        // De-dup.
        $candidates = $candidates->unique('id')->values();

        if ($candidates->isEmpty()) return 'no_match';

        if ($candidates->count() > 1) {
            $line->forceFill([
                'match_status'             => BankStatementLine::STATUS_DUPLICATE,
                'duplicate_candidate_ids'  => $candidates->pluck('id')->all(),
            ])->save();
            return 'duplicate';
        }

        $this->bindMatch($line, $candidates->first(), $matchedType);
        return 'matched';
    }

    /**
     * Strategy 2 — amount + direction + date proximity.
     */
    protected function matchByAmountDate(BankStatementLine $line, BankAccount $bankAccount): string
    {
        $amount = $line->absoluteAmount();
        if (bccomp($amount, '0', 2) <= 0) return 'no_match';

        $isDebit = $line->isDebit();
        $modelClass = $isDebit ? Payment::class : Receipt::class;
        $matchedType = $isDebit
            ? BankStatementLine::MATCHED_TYPE_PAYMENT
            : BankStatementLine::MATCHED_TYPE_RECEIPT;
        $dateColumn = $isDebit ? 'payment_date' : 'receipt_date';
        $amountColumn = 'amount';

        $low  = CarbonImmutable::instance($line->value_date)->subDays(self::DATE_PROXIMITY_DAYS);
        $high = CarbonImmutable::instance($line->value_date)->addDays(self::DATE_PROXIMITY_DAYS);

        $q = $modelClass::query()
            ->where('organization_id', $bankAccount->organization_id)
            ->where('status', $modelClass::STATUS_POSTED)
            ->whereBetween($dateColumn, [$low->toDateString(), $high->toDateString()])
            ->where($amountColumn, $amount)
            ->whereNull('bank_statement_line_id');

        if ($isDebit) {
            $q->where('source_account_id', $bankAccount->account_id);
        }

        $candidates = $q->get();
        if ($candidates->isEmpty()) return 'no_match';

        if ($candidates->count() > 1) {
            $line->forceFill([
                'match_status'             => BankStatementLine::STATUS_DUPLICATE,
                'duplicate_candidate_ids'  => $candidates->pluck('id')->all(),
            ])->save();
            return 'duplicate';
        }

        $this->bindMatch($line, $candidates->first(), $matchedType);
        return 'matched';
    }

    /**
     * Strategy 3 — descriptive embedded reference (e.g. "PMT P-2026-000123" in description).
     */
    protected function matchByEmbeddedRef(BankStatementLine $line, BankAccount $bankAccount): string
    {
        $desc = trim((string) $line->description);
        if ($desc === '' || mb_strlen($desc) < 6) return 'no_match';

        $isDebit = $line->isDebit();
        $modelClass = $isDebit ? Payment::class : Receipt::class;
        $matchedType = $isDebit
            ? BankStatementLine::MATCHED_TYPE_PAYMENT
            : BankStatementLine::MATCHED_TYPE_RECEIPT;
        $numberCol  = $isDebit ? 'payment_number' : 'receipt_number';

        // Look for tokens that look like our numbering pattern in the
        // description: P-2026-000123, R-2026-000456 etc.
        if (! preg_match_all('/\b([A-Z]{1,4}-\d{4}-\d{4,8})\b/iu', $desc, $matches)) {
            return 'no_match';
        }
        $tokens = array_unique(array_map('strtoupper', $matches[1]));
        if (empty($tokens)) return 'no_match';

        $candidates = $modelClass::query()
            ->where('organization_id', $bankAccount->organization_id)
            ->where('status', $modelClass::STATUS_POSTED)
            ->whereIn($numberCol, $tokens)
            ->whereNull('bank_statement_line_id')
            ->when($isDebit,
                fn ($q) => $q->where('source_account_id', $bankAccount->account_id))
            ->get();

        if ($candidates->isEmpty()) return 'no_match';

        if ($candidates->count() > 1) {
            $line->forceFill([
                'match_status'             => BankStatementLine::STATUS_DUPLICATE,
                'duplicate_candidate_ids'  => $candidates->pluck('id')->all(),
            ])->save();
            return 'duplicate';
        }

        $this->bindMatch($line, $candidates->first(), $matchedType);
        return 'matched';
    }

    /**
     * Manually bind a bank statement line to a specific our-side document.
     * Idempotent — re-binding to the same target is a no-op. Re-binding
     * to a different target unbinds the old one first.
     */
    public function manualMatch(
        BankStatementLine $line,
        string $matchedType,
        int $matchedId,
        User $user,
    ): BankStatementLine {
        if (! in_array($matchedType, BankStatementLine::ALL_MATCHED_TYPES, true)) {
            throw new \InvalidArgumentException("Invalid matched_type: {$matchedType}");
        }

        return DB::transaction(function () use ($line, $matchedType, $matchedId, $user) {
            $line = BankStatementLine::lockForUpdate()->findOrFail($line->id);

            // If already matched to the SAME target, no-op.
            if ($line->isMatched()
                && $line->matched_type === $matchedType
                && (int) $line->matched_id === $matchedId) {
                return $line;
            }

            // If matched to something else, unbind first.
            if ($line->matched_id) {
                $this->unmatchInternal($line);
            }

            $target = $this->loadTarget($matchedType, $matchedId);
            if (! $target) {
                throw new \DomainException(
                    "No {$matchedType} with id {$matchedId} found."
                );
            }

            // Refuse if target's bank account doesn't match our line's
            // statement bank account (only relevant for payments).
            if ($matchedType === BankStatementLine::MATCHED_TYPE_PAYMENT) {
                $expectedAcctId = $line->bankStatement->bankAccount->account_id;
                if ((int) $target->source_account_id !== (int) $expectedAcctId) {
                    throw new \DomainException(
                        "Payment {$target->payment_number} was paid from a different bank " .
                        'account than the one this statement is for.'
                    );
                }
            }

            // Refuse if target is already matched to another line (
            // forward pointer is the source of truth).
            if ($matchedType !== BankStatementLine::MATCHED_TYPE_JOURNAL_LINE
                && $target->bank_statement_line_id
                && (int) $target->bank_statement_line_id !== (int) $line->id) {
                throw new \DomainException(
                    "Already matched to bank statement line #{$target->bank_statement_line_id}."
                );
            }

            $this->bindMatch(
                $line,
                $target,
                $matchedType,
                $user,
                $matchedType === BankStatementLine::MATCHED_TYPE_JOURNAL_LINE
                    ? BankStatementLine::STATUS_MATCHED_TO_JOURNAL
                    : BankStatementLine::STATUS_MATCHED,
            );

            $line->refresh();
            $this->refreshStatementStatus($line->bankStatement);
            return $line;
        });
    }

    /**
     * Unbind a match. Reverses both directions of the link.
     */
    public function unmatch(BankStatementLine $line, User $user): BankStatementLine
    {
        return DB::transaction(function () use ($line, $user) {
            $line = BankStatementLine::lockForUpdate()->findOrFail($line->id);
            if (! $line->isMatched()) {
                return $line;
            }

            // Period-freeze guard: if the line is part of a closed
            // reconciliation, refuse.
            $stmt = $line->bankStatement;
            if ($stmt && $stmt->bank_reconciliation_id) {
                $rec = \App\Models\Finance\BankReconciliation::find($stmt->bank_reconciliation_id);
                if ($rec && $rec->isLocked()) {
                    throw new \DomainException(
                        "Cannot unmatch: this line is part of closed reconciliation " .
                        "{$rec->reconciliation_number}. Unreconcile it first."
                    );
                }
            }

            $this->unmatchInternal($line);

            $line->update([
                'matched_at'         => null,
                'matched_by_user_id' => $user->id,  // record who unmatched
            ]);
            $line->refresh();
            $this->refreshStatementStatus($line->bankStatement);
            return $line;
        });
    }

    /**
     * Mark a line as ignorable (e.g. test transactions, instantly-reversed bank entries).
     */
    public function markManuallyResolved(
        BankStatementLine $line,
        User $user,
        string $note,
    ): BankStatementLine {
        $note = trim($note);
        if (mb_strlen($note) < 3) {
            throw new \InvalidArgumentException('A resolution note (≥3 chars) is required.');
        }

        return DB::transaction(function () use ($line, $user, $note) {
            $line = BankStatementLine::lockForUpdate()->findOrFail($line->id);

            if ($line->isMatched()) {
                throw new \DomainException(
                    'Line is already matched. Unmatch it first if you want to mark it as resolved-no-action.'
                );
            }

            $line->update([
                'match_status'        => BankStatementLine::STATUS_MANUALLY_RESOLVED,
                'match_notes'         => $note,
                'matched_at'          => now(),
                'matched_by_user_id'  => $user->id,
                'duplicate_candidate_ids' => null,
            ]);

            $this->refreshStatementStatus($line->bankStatement);
            return $line->fresh();
        });
    }

    /* internals */

    /**
     * @param BankStatementLine $line
     * @param Payment|Receipt|JournalBatch $target
     */
    protected function bindMatch(
        BankStatementLine $line,
        $target,
        string $matchedType,
        ?User $user = null,
        ?string $statusOverride = null,
    ): void {
        $signedTargetAmount = $matchedType === BankStatementLine::MATCHED_TYPE_PAYMENT
            ? (string) $target->amount
            : ($matchedType === BankStatementLine::MATCHED_TYPE_RECEIPT
                ? (string) $target->amount
                : '0');

        $diff = bcsub($line->absoluteAmount(), $signedTargetAmount, 2);

        $line->forceFill([
            'match_status'            => $statusOverride ?? BankStatementLine::STATUS_MATCHED,
            'matched_type'            => $matchedType,
            'matched_id'               => $target->id,
            'amount_difference'        => $diff,
            'matched_at'               => now(),
            'matched_by_user_id'       => $user?->id,
            'duplicate_candidate_ids'  => null,
        ])->save();

        // Forward pointer + reconciled_at on the our-side document.
        if ($matchedType !== BankStatementLine::MATCHED_TYPE_JOURNAL_LINE
            && method_exists($target, 'forceFill')) {
            $target->forceFill([
                'bank_statement_line_id' => $line->id,
                'reconciled_at'          => now(),
                'reconciled_by_user_id'  => $user?->id ?? $target->reconciled_by_user_id,
            ])->save();
        } elseif ($matchedType === BankStatementLine::MATCHED_TYPE_JOURNAL_LINE) {
            $target->forceFill([
                'bank_statement_line_id' => $line->id,
            ])->save();
        }
    }

    /**
     * Reverse the binding side-effects.
     */
    protected function unmatchInternal(BankStatementLine $line): void
    {
        $matchedType = $line->matched_type;
        $matchedId   = $line->matched_id;
        if (! $matchedId || ! $matchedType) {
            $line->update([
                'match_status'            => BankStatementLine::STATUS_UNMATCHED,
                'matched_type'            => null,
                'matched_id'              => null,
                'amount_difference'       => '0.00',
                'duplicate_candidate_ids' => null,
            ]);
            return;
        }

        $target = $this->loadTarget($matchedType, $matchedId);
        if ($target && method_exists($target, 'forceFill')) {
            // Only clear forward pointer + reconciliation flag if the
            // target still points at this line (defensive against
            // race conditions).
            if ((int) ($target->bank_statement_line_id ?? 0) === (int) $line->id) {
                $clear = ['bank_statement_line_id' => null];
                if ($matchedType !== BankStatementLine::MATCHED_TYPE_JOURNAL_LINE) {
                    $clear['reconciled_at']         = null;
                    $clear['reconciled_by_user_id'] = null;
                }
                $target->forceFill($clear)->save();
            }
        }

        $line->update([
            'match_status'            => BankStatementLine::STATUS_UNMATCHED,
            'matched_type'            => null,
            'matched_id'              => null,
            'amount_difference'       => '0.00',
            'duplicate_candidate_ids' => null,
        ]);
    }

    protected function loadTarget(string $matchedType, int $matchedId)
    {
        return match ($matchedType) {
            BankStatementLine::MATCHED_TYPE_PAYMENT      => Payment::find($matchedId),
            BankStatementLine::MATCHED_TYPE_RECEIPT      => Receipt::find($matchedId),
            BankStatementLine::MATCHED_TYPE_JOURNAL_LINE => JournalBatch::find($matchedId),
            default                                      => null,
        };
    }

    protected function refreshStatementStatus(BankStatement $statement): void
    {
        $statement = $statement->fresh();
        if (! $statement || $statement->isVoid()) return;

        $totals = BankStatementLine::query()
            ->where('bank_statement_id', $statement->id)
            ->selectRaw(
                "COUNT(*) as total, " .
                "SUM(CASE WHEN match_status IN ('matched', 'matched_to_journal', 'manually_resolved') THEN 1 ELSE 0 END) as closed"
            )
            ->first();

        if (! $totals || (int) $totals->total === 0) return;

        $newStatus = ((int) $totals->closed === (int) $totals->total)
            ? BankStatement::STATUS_FULLY_RECONCILED
            : (((int) $totals->closed > 0)
                ? BankStatement::STATUS_PARTIALLY_RECONCILED
                : BankStatement::STATUS_IMPORTED);

        if ($statement->status !== $newStatus
            && $statement->status !== BankStatement::STATUS_VOID) {
            $statement->update(['status' => $newStatus]);
        }
    }
}
