<?php

namespace App\Services\Finance\Loans;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Posting\PostingEngine;
use App\Finance\Resolvers\FinanceAccountResolver;
use App\Models\Finance\BankAccount;
use App\Models\Finance\Loan;
use App\Models\Finance\LoanScheduleLine;
use App\Models\Finance\LoanTransaction;
use App\Models\User;
use App\Services\Finance\Loans\Concerns\HandlesLoanDecimals;
use App\Services\Finance\Loans\Exceptions\LoanException;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Loan lifecycle orchestration with GL integration.
 *
 *   createDraft()  persist the loan + generate/persist the amortisation
 *                  schedule. No GL impact.
 *
 *   disburse()     post Dr bank / Cr principal-current (+ Dr fee expense /
 *                  Cr bank for any origination fee). Flip loan to active.
 *
 *   accrueInterest() for a due (or all due-to-date) schedule line(s): post
 *                  Dr interest expense / Cr accrued interest. Idempotent per
 *                  (loan, period) via the posting engine idempotency key.
 *
 *   repay()        split a payment into principal / interest / fee / penalty
 *                  and post Dr principal + Dr accrued-interest (+fees/pen) /
 *                  Cr bank. Updates schedule line servicing + loan running
 *                  totals; settles the loan when fully repaid.
 *
 * All GL posting goes through PostingEngine, so period locks, validation and
 * audit apply. All DB writes are atomic.
 */
class LoanService
{
    use HandlesLoanDecimals;

    public function __construct(
        protected CurrentOrganization $currentOrganization,
        protected PostingEngine $postingEngine,
        protected FinanceAccountResolver $accounts,
        protected LoanAmortisationService $amortisation,
    ) {
    }

    /**
     * Create a draft loan and its amortisation schedule.
     *
     * @param  array<string, mixed>  $data
     */
    public function createDraft(array $data, User $actor): Loan
    {
        return DB::transaction(function () use ($data, $actor) {
            $firstPayment = CarbonImmutable::parse($data['first_payment_date']);

            $loan = Loan::create([
                'organization_id'      => $this->currentOrganization->id(),
                'reference'            => $data['reference'],
                'loan_type'            => $data['loan_type'],
                'lender_name'          => $data['lender_name'],
                'counterparty_id'      => $data['counterparty_id'] ?? null,
                'principal_amount'     => $this->n((string) $data['principal_amount']),
                'annual_interest_rate' => $this->n((string) $data['annual_interest_rate']),
                'term_months'          => (int) $data['term_months'],
                'amortisation_method'  => $data['amortisation_method'] ?? config('loans.amortisation.default_method'),
                'disbursement_date'    => $data['disbursement_date'] ?? null,
                'first_payment_date'   => $firstPayment->toDateString(),
                'origination_fee'      => $this->n((string) ($data['origination_fee'] ?? '0')),
                'status'               => Loan::STATUS_DRAFT,
                'bank_account_id'      => $data['bank_account_id'] ?? null,
                'principal_outstanding'=> '0',
                'created_by_user_id'   => $actor->id,
                'notes'                => $data['notes'] ?? null,
            ]);

            $schedule = $this->amortisation->generate(
                principal:        (string) $loan->principal_amount,
                annualRate:       (string) $loan->annual_interest_rate,
                termMonths:       (int) $loan->term_months,
                method:           $loan->amortisation_method,
                firstPaymentDate: $firstPayment,
                scale:            (int) config('loans.amortisation.rounding_scale', 2),
            );

            foreach ($schedule as $line) {
                LoanScheduleLine::create([
                    'organization_id' => $this->currentOrganization->id(),
                    'loan_id'         => $loan->id,
                    'period_number'   => $line['period_number'],
                    'due_date'        => $line['due_date'],
                    'opening_balance' => $line['opening_balance'],
                    'interest_due'    => $line['interest_due'],
                    'principal_due'   => $line['principal_due'],
                    'total_due'       => $line['total_due'],
                    'closing_balance' => $line['closing_balance'],
                    'status'          => LoanScheduleLine::STATUS_PENDING,
                ]);
            }

            return $loan->fresh('scheduleLines');
        });
    }

    /**
     * Disburse a draft loan: cash in, principal liability recognised.
     */
    public function disburse(Loan $loan, User $actor, ?CarbonImmutable $date = null): Loan
    {
        if (! $loan->isDraft()) {
            throw new LoanException("Loan #{$loan->id} is not in draft status; cannot disburse.");
        }

        $date ??= $loan->disbursement_date
            ? CarbonImmutable::parse($loan->disbursement_date->toDateString())
            : CarbonImmutable::now();

        $cfg = $loan->typeConfig();
        $principalAcct = $this->accounts->byCode($cfg['principal_current'])->id;
        $cashAcctId = $this->cashAccountId($loan);

        $principal = (string) $loan->principal_amount;
        $fee = (string) $loan->origination_fee;

        return DB::transaction(function () use ($loan, $actor, $date, $principalAcct, $cashAcctId, $cfg, $principal, $fee) {
            $lines = [
                JournalLineDraft::debit($cashAcctId, $principal, "Loan disbursement {$loan->reference}"),
                JournalLineDraft::credit($principalAcct, $principal, "Loan principal {$loan->reference}"),
            ];

            // Origination fee: Dr fee expense / Cr bank (fee paid out of the
            // disbursement). Net cash received = principal − fee.
            if ($this->cmp($fee, '0') > 0) {
                $feeAcct = $this->accounts->byCode($cfg['fees_expense'])->id;
                $lines[] = JournalLineDraft::debit($feeAcct, $fee, "Loan origination fee {$loan->reference}");
                $lines[] = JournalLineDraft::credit($cashAcctId, $fee, "Loan origination fee {$loan->reference}");
            }

            $batch = $this->postingEngine->post(new JournalBatchDraft(
                batchType:      'auto_loan_disbursement',
                postingDate:    $date,
                journals:       [new JournalDraft(journalType: 'loan_disbursement', lines: $lines)],
                description:    "Loan disbursement {$loan->reference}",
                sourceModule:   'finance.loans',
                sourceType:     'loan',
                sourceId:       $loan->id,
                idempotencyKey: 'loan_disbursement:' . ($this->currentOrganization->id() ?? 'null') . ':' . $loan->id,
                postingPurpose: 'loan_disbursement',
            ), $actor);

            LoanTransaction::create([
                'organization_id'   => $this->currentOrganization->id(),
                'loan_id'           => $loan->id,
                'txn_type'          => LoanTransaction::TYPE_DISBURSEMENT,
                'txn_date'          => $date->toDateString(),
                'principal_amount'  => $principal,
                'fee_amount'        => $fee,
                'total_amount'      => $principal,
                'journal_batch_id'  => $batch->id,
                'bank_account_id'   => $loan->bank_account_id,
                'created_by_user_id'=> $actor->id,
            ]);

            $loan->update([
                'status'                => Loan::STATUS_ACTIVE,
                'disbursement_batch_id' => $batch->id,
                'disbursed_at'          => now(),
                'disbursement_date'     => $date->toDateString(),
                'principal_outstanding' => $principal,
            ]);

            return $loan->fresh();
        });
    }

    /**
     * Accrue interest for a single schedule line (Dr interest expense /
     * Cr accrued interest). Idempotent per (loan, period).
     */
    public function accrueLine(Loan $loan, LoanScheduleLine $line, User $actor, ?CarbonImmutable $date = null): ?LoanTransaction
    {
        if (! $loan->isActive()) {
            return null;
        }
        if ($line->interest_accrued) {
            return null; // already accrued
        }
        $interest = (string) $line->interest_due;
        if ($this->cmp($interest, '0') <= 0) {
            $line->update(['interest_accrued' => true]);
            return null;
        }

        $date ??= CarbonImmutable::parse($line->due_date->toDateString());
        $cfg = $loan->typeConfig();
        $interestExpense = $this->accounts->byCode($cfg['interest_expense'])->id;
        $accruedInterest = $this->accounts->byCode($cfg['accrued_interest'])->id;

        return DB::transaction(function () use ($loan, $line, $actor, $date, $interest, $interestExpense, $accruedInterest) {
            $batch = $this->postingEngine->post(new JournalBatchDraft(
                batchType:      'auto_loan_interest_accrual',
                postingDate:    $date,
                journals:       [new JournalDraft(journalType: 'loan_interest_accrual', lines: [
                    JournalLineDraft::debit($interestExpense, $interest, "Interest accrual {$loan->reference} P{$line->period_number}"),
                    JournalLineDraft::credit($accruedInterest, $interest, "Interest accrual {$loan->reference} P{$line->period_number}"),
                ])],
                description:    "Loan interest accrual {$loan->reference} period {$line->period_number}",
                sourceModule:   'finance.loans',
                sourceType:     'loan_accrual',
                sourceId:       $loan->id,
                idempotencyKey: sprintf('loan_accrual:%s:%d:%d', $this->currentOrganization->id() ?? 'null', $loan->id, $line->period_number),
                postingPurpose: 'loan_interest_accrual',
            ), $actor);

            $txn = LoanTransaction::create([
                'organization_id'   => $this->currentOrganization->id(),
                'loan_id'           => $loan->id,
                'schedule_line_id'  => $line->id,
                'txn_type'          => LoanTransaction::TYPE_ACCRUAL,
                'txn_date'          => $date->toDateString(),
                'interest_amount'   => $interest,
                'total_amount'      => $interest,
                'journal_batch_id'  => $batch->id,
                'created_by_user_id'=> $actor->id,
                'idempotency_key'   => sprintf('loan_accrual:%d:%d', $loan->id, $line->period_number),
            ]);

            $line->update(['interest_accrued' => true]);
            $loan->update([
                'interest_accrued_outstanding' => $this->add((string) $loan->interest_accrued_outstanding, $interest),
                'total_interest_charged'       => $this->add((string) $loan->total_interest_charged, $interest),
            ]);

            return $txn;
        });
    }

    /**
     * Record a repayment. Splits into principal + interest (+ optional fee /
     * penalty). Interest first clears any accrued interest; principal reduces
     * the principal liability. Cr bank for the total cash out.
     *
     * @param  array{principal:string, interest?:string, fee?:string, penalty?:string, schedule_line_id?:int, bank_account_id?:int}  $components
     */
    public function repay(Loan $loan, array $components, User $actor, ?CarbonImmutable $date = null): LoanTransaction
    {
        if (! $loan->isActive()) {
            throw new LoanException("Loan #{$loan->id} is not active; cannot record repayment.");
        }

        $date ??= CarbonImmutable::now();
        $principal = $this->n((string) ($components['principal'] ?? '0'));
        $interest  = $this->n((string) ($components['interest'] ?? '0'));
        $fee       = $this->n((string) ($components['fee'] ?? '0'));
        $penalty   = $this->n((string) ($components['penalty'] ?? '0'));

        $total = $this->add($this->add($this->add($principal, $interest), $fee), $penalty);
        if ($this->cmp($total, '0') <= 0) {
            throw new LoanException('Repayment total must be greater than zero.');
        }

        $cfg = $loan->typeConfig();
        $principalAcct = $this->accounts->byCode($cfg['principal_current'])->id;
        $accruedAcct   = $this->accounts->byCode($cfg['accrued_interest'])->id;
        $interestExp   = $this->accounts->byCode($cfg['interest_expense'])->id;
        $feeAcct       = $this->accounts->byCode($cfg['fees_expense'])->id;
        $penaltyAcct   = $this->accounts->byCode($cfg['penalty_expense'])->id;
        $cashAcctId    = $this->cashAccountId($loan, $components['bank_account_id'] ?? null);

        return DB::transaction(function () use (
            $loan, $actor, $date, $principal, $interest, $fee, $penalty, $total, $components,
            $principalAcct, $accruedAcct, $interestExp, $feeAcct, $penaltyAcct, $cashAcctId
        ) {
            $lines = [];

            if ($this->cmp($principal, '0') > 0) {
                $lines[] = JournalLineDraft::debit($principalAcct, $principal, "Loan principal repayment {$loan->reference}");
            }

            if ($this->cmp($interest, '0') > 0) {
                // Clear accrued interest first; any excess hits interest expense
                // directly (interest paid that was never accrued).
                $accruedAvail = (string) $loan->interest_accrued_outstanding;
                $fromAccrued = $this->cmp($interest, $accruedAvail) <= 0 ? $interest : $accruedAvail;
                $fromExpense = $this->sub($interest, $fromAccrued);

                if ($this->cmp($fromAccrued, '0') > 0) {
                    $lines[] = JournalLineDraft::debit($accruedAcct, $fromAccrued, "Interest paid (accrued) {$loan->reference}");
                }
                if ($this->cmp($fromExpense, '0') > 0) {
                    $lines[] = JournalLineDraft::debit($interestExp, $fromExpense, "Interest paid (current) {$loan->reference}");
                }
            }

            if ($this->cmp($fee, '0') > 0) {
                $lines[] = JournalLineDraft::debit($feeAcct, $fee, "Loan fee {$loan->reference}");
            }
            if ($this->cmp($penalty, '0') > 0) {
                $lines[] = JournalLineDraft::debit($penaltyAcct, $penalty, "Loan penalty {$loan->reference}");
            }

            $lines[] = JournalLineDraft::credit($cashAcctId, $total, "Loan repayment {$loan->reference}");

            $batch = $this->postingEngine->post(new JournalBatchDraft(
                batchType:      'auto_loan_repayment',
                postingDate:    $date,
                journals:       [new JournalDraft(journalType: 'loan_repayment', lines: $lines)],
                description:    "Loan repayment {$loan->reference}",
                sourceModule:   'finance.loans',
                sourceType:     'loan_repayment',
                sourceId:       $loan->id,
                postingPurpose: 'loan_repayment',
            ), $actor);

            $txn = LoanTransaction::create([
                'organization_id'   => $this->currentOrganization->id(),
                'loan_id'           => $loan->id,
                'schedule_line_id'  => $components['schedule_line_id'] ?? null,
                'txn_type'          => LoanTransaction::TYPE_REPAYMENT,
                'txn_date'          => $date->toDateString(),
                'principal_amount'  => $principal,
                'interest_amount'   => $interest,
                'fee_amount'        => $fee,
                'penalty_amount'    => $penalty,
                'total_amount'      => $total,
                'journal_batch_id'  => $batch->id,
                'bank_account_id'   => $components['bank_account_id'] ?? $loan->bank_account_id,
                'created_by_user_id'=> $actor->id,
            ]);

            $this->applyRepaymentToBalances($loan, $principal, $interest, $components['schedule_line_id'] ?? null, $date);

            return $txn;
        });
    }

    // ----------------------------------------------------------------

    protected function applyRepaymentToBalances(Loan $loan, string $principal, string $interest, ?int $scheduleLineId, CarbonImmutable $date): void
    {
        $newPrincipalOut = $this->sub((string) $loan->principal_outstanding, $principal);
        if ($this->cmp($newPrincipalOut, '0') < 0) {
            $newPrincipalOut = '0.00';
        }

        $accruedReduction = $this->cmp($interest, (string) $loan->interest_accrued_outstanding) <= 0
            ? $interest
            : (string) $loan->interest_accrued_outstanding;
        $newAccrued = $this->sub((string) $loan->interest_accrued_outstanding, $accruedReduction);

        $loan->update([
            'principal_outstanding'        => $newPrincipalOut,
            'interest_accrued_outstanding' => $newAccrued,
            'total_principal_repaid'       => $this->add((string) $loan->total_principal_repaid, $principal),
        ]);

        if ($scheduleLineId) {
            $line = LoanScheduleLine::find($scheduleLineId);
            if ($line) {
                $line->interest_paid  = $this->add((string) $line->interest_paid, $interest);
                $line->principal_paid = $this->add((string) $line->principal_paid, $principal);
                $paidPrincipal = $this->cmp($line->principal_paid, (string) $line->principal_due) >= 0;
                $paidInterest  = $this->cmp($line->interest_paid, (string) $line->interest_due) >= 0;
                $line->status = ($paidPrincipal && $paidInterest)
                    ? LoanScheduleLine::STATUS_PAID
                    : LoanScheduleLine::STATUS_PARTIALLY_PAID;
                if ($line->status === LoanScheduleLine::STATUS_PAID) {
                    $line->paid_date = $date->toDateString();
                }
                $line->save();
            }
        }

        // Settle the loan when principal is fully repaid.
        if ($this->cmp($newPrincipalOut, '0') === 0 && $this->cmp($newAccrued, '0') === 0) {
            $loan->update(['status' => Loan::STATUS_SETTLED, 'settled_at' => now()]);
        }
    }

    protected function cashAccountId(Loan $loan, ?int $explicitBankAccountId = null): int
    {
        $bankAccountId = $explicitBankAccountId ?? $loan->bank_account_id;
        if ($bankAccountId) {
            $bank = BankAccount::find($bankAccountId);
            if ($bank && $bank->account_id) {
                return (int) $bank->account_id;
            }
        }
        return (int) $this->accounts->byCode((string) config('loans.default_cash_account_code', '1030'))->id;
    }
}
