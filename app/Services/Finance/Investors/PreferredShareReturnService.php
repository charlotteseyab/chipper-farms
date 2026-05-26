<?php

namespace App\Services\Finance\Investors;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Posting\PostingEngine;
use App\Finance\Resolvers\FinanceAccountResolver;
use App\Models\Finance\BankAccount;
use App\Models\Finance\Investor;
use App\Models\Finance\InvestorTransaction;
use App\Models\User;
use App\Services\Finance\Investors\Concerns\HandlesInvestorDecimals;
use App\Services\Finance\Investors\Exceptions\InvestorException;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Preferred-share returns for preferred-class investors.
 *
 *   accrueReturn()  declare/accrue a period return:
 *                   Dr 7120 Preferred Share Returns / Cr 2630 Accrued Returns.
 *                   Amount is supplied (board-declared) or computed from the
 *                   investor's preferred_share_capital × preferred_return_rate
 *                   for a period fraction.
 *
 *   payReturn()     pay an accrued return:
 *                   Dr 2630 Accrued Returns / Cr cash.
 *
 * Gated by config('investors.preferred_shares.enabled'). These are LIABILITY/
 * finance-cost postings (preferred shares are debt-like here), distinct from
 * equity profit allocation.
 */
class PreferredShareReturnService
{
    use HandlesInvestorDecimals;

    public function __construct(
        protected CurrentOrganization $currentOrganization,
        protected PostingEngine $postingEngine,
        protected FinanceAccountResolver $accounts,
    ) {
    }

    /**
     * Accrue a preferred-share return. If $amount is null, computes
     * capital × annual_rate × (months/12).
     */
    public function accrueReturn(
        Investor $investor,
        User $actor,
        ?string $amount = null,
        int $months = 1,
        ?CarbonImmutable $date = null,
        ?string $notes = null,
    ): InvestorTransaction {
        $this->guardEnabled();
        $this->guardPreferred($investor);

        $amount = $amount !== null
            ? $this->n($amount)
            : $this->computeReturn($investor, $months);

        if ($this->cmp($amount, '0') <= 0) {
            throw new InvestorException('Preferred return amount must be greater than zero.');
        }

        $date ??= CarbonImmutable::now();
        $cfg = (array) config('investors.accounts');
        $expenseAcct = $this->accounts->byCode($cfg['preferred_return_exp'])->id;
        $accruedAcct = $this->accounts->byCode($cfg['accrued_returns'])->id;

        return DB::transaction(function () use ($investor, $amount, $actor, $expenseAcct, $accruedAcct, $date, $notes) {
            $batch = $this->postingEngine->post(new JournalBatchDraft(
                batchType:      'auto_preferred_return_accrual',
                postingDate:    $date,
                journals:       [new JournalDraft(journalType: 'preferred_return_accrual', lines: [
                    JournalLineDraft::debit($expenseAcct, $amount, "Preferred return accrual — {$investor->name}"),
                    JournalLineDraft::credit($accruedAcct, $amount, "Preferred return accrual — {$investor->name}"),
                ])],
                description:    "Preferred share return accrual — {$investor->name}",
                sourceModule:   'finance.investors',
                sourceType:     'preferred_return_accrual',
                sourceId:       $investor->id,
                postingPurpose: 'preferred_return_accrual',
            ), $actor);

            return InvestorTransaction::create([
                'organization_id'    => $this->currentOrganization->id(),
                'investor_id'        => $investor->id,
                'txn_type'           => InvestorTransaction::TYPE_PREFERRED_ACCRUAL,
                'txn_date'           => $date->toDateString(),
                'amount'             => $amount,
                'journal_batch_id'   => $batch->id,
                'created_by_user_id' => $actor->id,
                'notes'              => $notes,
            ]);
        });
    }

    /**
     * Pay a preferred-share return (clears accrued returns).
     */
    public function payReturn(
        Investor $investor,
        string $amount,
        User $actor,
        ?int $bankAccountId = null,
        ?CarbonImmutable $date = null,
        ?string $notes = null,
    ): InvestorTransaction {
        $this->guardEnabled();
        $this->guardPreferred($investor);

        $amount = $this->n($amount);
        if ($this->cmp($amount, '0') <= 0) {
            throw new InvestorException('Payment amount must be greater than zero.');
        }

        $date ??= CarbonImmutable::now();
        $cfg = (array) config('investors.accounts');
        $accruedAcct = $this->accounts->byCode($cfg['accrued_returns'])->id;
        $cashAcctId = $this->cashAccountId($bankAccountId);

        return DB::transaction(function () use ($investor, $amount, $actor, $accruedAcct, $cashAcctId, $bankAccountId, $date, $notes) {
            $batch = $this->postingEngine->post(new JournalBatchDraft(
                batchType:      'auto_preferred_return_payment',
                postingDate:    $date,
                journals:       [new JournalDraft(journalType: 'preferred_return_payment', lines: [
                    JournalLineDraft::debit($accruedAcct, $amount, "Preferred return payment — {$investor->name}"),
                    JournalLineDraft::credit($cashAcctId, $amount, "Preferred return payment — {$investor->name}"),
                ])],
                description:    "Preferred share return payment — {$investor->name}",
                sourceModule:   'finance.investors',
                sourceType:     'preferred_return_payment',
                sourceId:       $investor->id,
                postingPurpose: 'preferred_return_payment',
            ), $actor);

            return InvestorTransaction::create([
                'organization_id'    => $this->currentOrganization->id(),
                'investor_id'        => $investor->id,
                'txn_type'           => InvestorTransaction::TYPE_PREFERRED_PAYMENT,
                'txn_date'           => $date->toDateString(),
                'amount'             => $amount,
                'journal_batch_id'   => $batch->id,
                'bank_account_id'    => $bankAccountId,
                'created_by_user_id' => $actor->id,
                'notes'              => $notes,
            ]);
        });
    }

    // ----------------------------------------------------------------

    protected function computeReturn(Investor $investor, int $months): string
    {
        $annual = bcmul((string) $investor->preferred_share_capital, (string) $investor->preferred_return_rate, 8);
        $periodic = bcdiv(bcmul($annual, (string) $months, 8), '12', 8);
        return bcadd($periodic, '0', 2);
    }

    protected function guardEnabled(): void
    {
        if (! config('investors.preferred_shares.enabled', false)) {
            throw new InvestorException('Preferred-share returns are disabled. Enable investors.preferred_shares.enabled to use this feature.');
        }
    }

    protected function guardPreferred(Investor $investor): void
    {
        if (! $investor->isPreferred()) {
            throw new InvestorException("Investor {$investor->name} is not a preferred-share investor.");
        }
    }

    protected function cashAccountId(?int $bankAccountId): int
    {
        if ($bankAccountId) {
            $bank = BankAccount::find($bankAccountId);
            if ($bank && $bank->account_id) {
                return (int) $bank->account_id;
            }
        }
        return (int) $this->accounts->byCode((string) config('investors.default_cash_account_code', '1030'))->id;
    }
}
