<?php

namespace App\Services\Finance\Investors;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Posting\PostingEngine;
use App\Finance\Resolvers\FinanceAccountResolver;
use App\Models\Finance\BankAccount;
use App\Models\Finance\Investor;
use App\Models\Finance\InvestorCapitalAccount;
use App\Models\Finance\InvestorTransaction;
use App\Models\User;
use App\Services\Finance\Investors\Concerns\HandlesInvestorDecimals;
use App\Services\Finance\Investors\Exceptions\InvestorException;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Investor lifecycle + capital movements with GL integration.
 *
 *   createInvestor()  persist an investor + its capital account (no GL).
 *
 *   contribute()      capital in: Dr cash/asset / Cr 3110 Contributions.
 *                     Raises the investor's capital balance + 3100 control
 *                     (3110 rolls up under the 3100 block on the balance
 *                     sheet). Idempotent via the posting engine.
 *
 *   distribute()      capital/return out: Dr 3120 Distributions / Cr cash.
 *                     Reduces the investor's capital balance. Guards against
 *                     distributing more than the current balance.
 *
 * Profit allocation lives in InvestorProfitAllocationService; preferred-share
 * returns live in PreferredShareReturnService.
 *
 * All GL goes through PostingEngine (period locks, idempotency, audit). All
 * writes atomic.
 */
class InvestorService
{
    use HandlesInvestorDecimals;

    public function __construct(
        protected CurrentOrganization $currentOrganization,
        protected PostingEngine $postingEngine,
        protected FinanceAccountResolver $accounts,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createInvestor(array $data, User $actor): Investor
    {
        return DB::transaction(function () use ($data, $actor) {
            $investor = Investor::create([
                'organization_id'         => $this->currentOrganization->id(),
                'name'                    => $data['name'],
                'reference'               => $data['reference'] ?? null,
                'investor_class'          => $data['investor_class'] ?? Investor::CLASS_EQUITY,
                'counterparty_id'         => $data['counterparty_id'] ?? null,
                'linked_loan_id'          => $data['linked_loan_id'] ?? null,
                'ownership_percentage'    => $this->n((string) ($data['ownership_percentage'] ?? '0')),
                'preferred_share_capital' => $this->n((string) ($data['preferred_share_capital'] ?? '0')),
                'preferred_return_rate'   => $this->n((string) ($data['preferred_return_rate'] ?? '0')),
                'status'                  => Investor::STATUS_ACTIVE,
                'contact_email'           => $data['contact_email'] ?? null,
                'contact_phone'           => $data['contact_phone'] ?? null,
                'notes'                   => $data['notes'] ?? null,
                'created_by_user_id'      => $actor->id,
            ]);

            InvestorCapitalAccount::create([
                'organization_id' => $this->currentOrganization->id(),
                'investor_id'     => $investor->id,
                'label'           => 'Capital Account',
            ]);

            return $investor->fresh('capitalAccount');
        });
    }

    /**
     * Record a capital contribution.
     */
    public function contribute(
        Investor $investor,
        string $amount,
        User $actor,
        ?int $bankAccountId = null,
        ?CarbonImmutable $date = null,
        ?string $notes = null,
    ): InvestorTransaction {
        $amount = $this->n($amount);
        if ($this->cmp($amount, '0') <= 0) {
            throw new InvestorException('Contribution amount must be greater than zero.');
        }

        $date ??= CarbonImmutable::now();
        $cfg = (array) config('investors.accounts');
        $contributionsAcct = $this->accounts->byCode($cfg['contributions'])->id;
        $cashAcctId = $this->cashAccountId($bankAccountId);
        $account = $this->capitalAccount($investor);

        return DB::transaction(function () use ($investor, $account, $amount, $actor, $bankAccountId, $cashAcctId, $contributionsAcct, $date, $notes) {
            $batch = $this->postingEngine->post(new JournalBatchDraft(
                batchType:      'auto_investor_contribution',
                postingDate:    $date,
                journals:       [new JournalDraft(journalType: 'investor_contribution', lines: [
                    JournalLineDraft::debit($cashAcctId, $amount, "Investor contribution — {$investor->name}"),
                    JournalLineDraft::credit($contributionsAcct, $amount, "Investor contribution — {$investor->name}"),
                ])],
                description:    "Investor capital contribution — {$investor->name}",
                sourceModule:   'finance.investors',
                sourceType:     'investor_contribution',
                sourceId:       $investor->id,
                postingPurpose: 'investor_contribution',
            ), $actor);

            $txn = InvestorTransaction::create([
                'organization_id'    => $this->currentOrganization->id(),
                'investor_id'        => $investor->id,
                'capital_account_id' => $account->id,
                'txn_type'           => InvestorTransaction::TYPE_CONTRIBUTION,
                'txn_date'           => $date->toDateString(),
                'amount'             => $amount,
                'journal_batch_id'   => $batch->id,
                'bank_account_id'    => $bankAccountId,
                'created_by_user_id' => $actor->id,
                'notes'              => $notes,
            ]);

            $account->update([
                'total_contributed' => $this->add((string) $account->total_contributed, $amount),
                'current_balance'   => $this->add((string) $account->current_balance, $amount),
                'last_movement_at'  => now(),
            ]);

            return $txn;
        });
    }

    /**
     * Record a distribution (capital/return out to the investor).
     */
    public function distribute(
        Investor $investor,
        string $amount,
        User $actor,
        ?int $bankAccountId = null,
        ?CarbonImmutable $date = null,
        ?string $notes = null,
    ): InvestorTransaction {
        $amount = $this->n($amount);
        if ($this->cmp($amount, '0') <= 0) {
            throw new InvestorException('Distribution amount must be greater than zero.');
        }

        $account = $this->capitalAccount($investor);
        if ($this->cmp($amount, (string) $account->current_balance) > 0) {
            throw new InvestorException(sprintf(
                'Distribution (%s) exceeds the investor capital balance (%s).',
                $amount, (string) $account->current_balance
            ));
        }

        $date ??= CarbonImmutable::now();
        $cfg = (array) config('investors.accounts');
        $distributionsAcct = $this->accounts->byCode($cfg['distributions'])->id;
        $cashAcctId = $this->cashAccountId($bankAccountId);

        return DB::transaction(function () use ($investor, $account, $amount, $actor, $bankAccountId, $cashAcctId, $distributionsAcct, $date, $notes) {
            $batch = $this->postingEngine->post(new JournalBatchDraft(
                batchType:      'auto_investor_distribution',
                postingDate:    $date,
                journals:       [new JournalDraft(journalType: 'investor_distribution', lines: [
                    JournalLineDraft::debit($distributionsAcct, $amount, "Investor distribution — {$investor->name}"),
                    JournalLineDraft::credit($cashAcctId, $amount, "Investor distribution — {$investor->name}"),
                ])],
                description:    "Investor distribution — {$investor->name}",
                sourceModule:   'finance.investors',
                sourceType:     'investor_distribution',
                sourceId:       $investor->id,
                postingPurpose: 'investor_distribution',
            ), $actor);

            $txn = InvestorTransaction::create([
                'organization_id'    => $this->currentOrganization->id(),
                'investor_id'        => $investor->id,
                'capital_account_id' => $account->id,
                'txn_type'           => InvestorTransaction::TYPE_DISTRIBUTION,
                'txn_date'           => $date->toDateString(),
                'amount'             => $amount,
                'journal_batch_id'   => $batch->id,
                'bank_account_id'    => $bankAccountId,
                'created_by_user_id' => $actor->id,
                'notes'              => $notes,
            ]);

            $account->update([
                'total_distributed' => $this->add((string) $account->total_distributed, $amount),
                'current_balance'   => $this->sub((string) $account->current_balance, $amount),
                'last_movement_at'  => now(),
            ]);

            return $txn;
        });
    }

    // ----------------------------------------------------------------

    public function capitalAccount(Investor $investor): InvestorCapitalAccount
    {
        $account = $investor->capitalAccount()->first();
        if (! $account) {
            $account = InvestorCapitalAccount::create([
                'organization_id' => $this->currentOrganization->id(),
                'investor_id'     => $investor->id,
                'label'           => 'Capital Account',
            ]);
        }
        return $account;
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
