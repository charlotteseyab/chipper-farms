<?php

namespace App\Services\Finance\Cash;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Posting\PostingEngine;
use App\Models\Finance\Account;
use App\Models\Finance\CashTransfer;
use App\Models\Finance\JournalPosting;
use App\Models\User;
use App\Services\Numbering\NumberingService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Moves money between the farm's own accounts (Phase 5, prompt §41).
 *
 * THE FIVE PROPERTIES A TRANSFER MUST HAVE
 * ----------------------------------------
 * Prompt §41 lists them, and each is enforced here rather than assumed:
 *
 *   reduce one account   — Cr source
 *   increase another     — Dr destination
 *   never count as income  \  guaranteed structurally: both legs must be
 *   never count as expense /  configured MONEY accounts, so no revenue or
 *                             expense account can appear in the journal
 *   balance              — one amount, two legs, built together
 *   be idempotent        — unique key, checked before posting
 *   be auditable         — numbered, attributed, journal-linked
 *
 * The income/expense guarantee is the one worth dwelling on. Moving GHS 2,000
 * from MoMo to the bank is the most commonly mis-recorded farm transaction:
 * entered as a payment it becomes an expense, understating the month's profit by
 * the full amount while the bank balance rises with no matching income. Making
 * both legs come from the money-account list means that mistake is not
 * expressible through this workflow.
 *
 * POSTS THROUGH PostingEngine
 * ---------------------------
 * Like Quick Expense, this builds a draft and hands it over. It does not write
 * journal rows.
 */
class RecordCashTransferService
{
    public function __construct(
        protected PostingEngine $postingEngine,
        protected NumberingService $numbering,
        protected MoneyAccountService $moneyAccounts,
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    /**
     * @param array{
     *   transfer_date: string,
     *   from_account_id: int,
     *   to_account_id: int,
     *   amount: string,
     *   external_reference?: ?string,
     *   description?: ?string,
     *   notes?: ?string,
     *   idempotency_key?: ?string,
     * } $input
     */
    public function record(array $input, User $actor): CashTransfer
    {
        $organizationId = $this->currentOrganization->get()?->id;

        if ($organizationId === null) {
            throw new \RuntimeException('Cannot record a transfer without an organisation context.');
        }

        $this->assertAmount((string) ($input['amount'] ?? ''));

        $idempotencyKey = $input['idempotency_key'] ?? $this->deriveKey($input, $actor);

        $existing = CashTransfer::query()
            ->forOrganization($organizationId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $from = $this->resolveMoneyAccount((int) $input['from_account_id'], $organizationId, 'from');
        $to   = $this->resolveMoneyAccount((int) $input['to_account_id'], $organizationId, 'to');

        if ($from->id === $to->id) {
            throw new \InvalidArgumentException(
                'Choose two different accounts — money cannot be transferred to where it already is.'
            );
        }

        $date   = CarbonImmutable::parse($input['transfer_date']);
        $amount = bcadd((string) $input['amount'], '0', 2);

        return DB::transaction(function () use (
            $input, $actor, $organizationId, $from, $to, $date, $amount, $idempotencyKey
        ): CashTransfer {
            $transfer = CashTransfer::create([
                'organization_id'     => $organizationId,
                'transfer_number'     => $this->numbering->next(
                    sequenceType:   'cash_transfer',
                    prefix:         'TRF',
                    year:           (int) $date->format('Y'),
                    organizationId: $organizationId,
                    padding:        5,
                ),
                'transfer_date'       => $date->toDateString(),
                'from_account_id'     => $from->id,
                'to_account_id'       => $to->id,
                'amount'              => $amount,
                'currency_code'       => 'GHS',
                'external_reference'  => $input['external_reference'] ?? null,
                'description'         => $input['description']
                    ?? "Transfer from {$from->name} to {$to->name}",
                'notes'               => $input['notes'] ?? null,
                'status'              => CashTransfer::STATUS_DRAFT,
                'recorded_by_user_id' => $actor->id,
                'idempotency_key'     => $idempotencyKey,
            ]);

            $batch = $this->postingEngine->post(
                $this->buildDraft($transfer, $from, $to, $date, $amount),
                $actor,
            );

            $transfer->update([
                'journal_batch_id' => $batch->id,
                'status'           => CashTransfer::STATUS_POSTED,
            ]);

            return $transfer->fresh();
        });
    }

    /* ------------------------------------------------------------------ */

    protected function buildDraft(
        CashTransfer $transfer,
        Account $from,
        Account $to,
        CarbonImmutable $date,
        string $amount,
    ): JournalBatchDraft {
        return new JournalBatchDraft(
            batchType:      'auto_cash_transfer',
            postingDate:    $date,
            journals:       [new JournalDraft(
                journalType: 'cash_transfer',
                lines:       [
                    new JournalLineDraft(
                        accountId:   $to->id,
                        debit:       $amount,
                        credit:      '0',
                        description: $transfer->description,
                    ),
                    new JournalLineDraft(
                        accountId:   $from->id,
                        debit:       '0',
                        credit:      $amount,
                        description: $transfer->description,
                    ),
                ],
                reference:   $transfer->transfer_number,
                description: $transfer->description,
                sourceType:  CashTransfer::class,
                sourceId:    $transfer->id,
            )],
            description:    'Cash transfer ' . $transfer->transfer_number,
            sourceModule:   'finance.cash',
            sourceType:     CashTransfer::class,
            sourceId:       $transfer->id,
            idempotencyKey: 'cash_transfer:' . $transfer->id,
            postingPurpose: JournalPosting::PURPOSE_CASH_PAYMENT,
        );
    }

    /**
     * Both legs must be configured money accounts.
     *
     * This is the structural guarantee that a transfer can never touch revenue
     * or expense. Without it the workflow is a general-purpose journal entry
     * form with a friendly label.
     */
    protected function resolveMoneyAccount(int $accountId, int $organizationId, string $side): Account
    {
        $account = Account::query()
            ->where('id', $accountId)
            ->where(function ($q) use ($organizationId) {
                $q->where('organization_id', $organizationId)->orWhereNull('organization_id');
            })
            ->first();

        if ($account === null) {
            throw new \InvalidArgumentException(
                ucfirst($side) . ' account not found for this organisation.'
            );
        }

        if (! $this->moneyAccounts->isMoneyAccount($account)) {
            throw new \InvalidArgumentException(
                "[{$account->name}] is not one of the farm's money accounts. Transfers move cash "
                . 'between tills, bank accounts and mobile money wallets — anything else is an '
                . 'expense or a receipt, not a transfer.'
            );
        }

        return $account;
    }

    protected function assertAmount(string $amount): void
    {
        if (! is_numeric($amount)) {
            throw new \InvalidArgumentException("Transfer amount must be numeric, got [{$amount}].");
        }

        if (bccomp($amount, '0', 4) <= 0) {
            throw new \InvalidArgumentException('Transfer amount must be greater than zero.');
        }
    }

    protected function deriveKey(array $input, User $actor): string
    {
        return 'ct:' . hash('sha256', implode('|', [
            $actor->id,
            $input['transfer_date'] ?? '',
            $input['from_account_id'] ?? '',
            $input['to_account_id'] ?? '',
            $input['amount'] ?? '',
            $input['external_reference'] ?? '',
        ]));
    }
}
