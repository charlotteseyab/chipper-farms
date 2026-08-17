<?php

namespace App\Services\Finance\Receipts;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Posting\PostingEngine;
use App\Finance\Resolvers\FinanceAccountResolver;
use App\Models\Counterparty;
use App\Models\Finance\JournalPosting;
use App\Models\Finance\Receipt;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\Numbering\NumberingService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Creates and posts receipts.
 *
 * createPosted() — the standard flow: customer pays cash, bank transfer,
 * mobile money, or cheque. Allocates a receipt number, posts journals
 * Dr Cash / Cr AR control immediately, returns posted Receipt.
 *
 * createBackfilled() — the historical-data flow: a legacy pig sale was
 * marked payment_status='paid' under the Phase 1c direct-posting flow.
 * Cash and AR were already debited/credited on the same legacy journal.
 * The backfill needs a Receipt row in the new schema for ledger reporting,
 * but MUST NOT post new GL entries (that would double-count). The receipt
 * is created with is_backfilled=true, journal_batch_id=NULL, status=posted.
 *
 * Account resolution for posted receipts:
 *   Cash side: payment_method.gl_account_hint → resolver lookup
 *              fallback: SUSPENSE_RECEIPT
 *   AR side:   counterparty.category.ar_account_id (Phase 2a)
 *              fallback: '1110' AR control
 *
 * Idempotency:
 *   Receipt creation uses the standard PostingEngine + journal_postings
 *   UNIQUE on idempotency_key='receipt_posted:Receipt:{id}:CASH_RECEIPT'.
 *   Re-posting the same receipt → DuplicatePostingException.
 *
 *   Note: idempotency on numbering. NumberingService allocates the
 *   receipt_number atomically before the INSERT. If the surrounding
 *   transaction rolls back, the number is consumed — gap in sequence.
 *   Acceptable per Phase 2b convention.
 */
class ReceiptService
{
    public function __construct(
        protected NumberingService $numbering,
        protected PostingEngine $postingEngine,
        protected FinanceAccountResolver $accountResolver,
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    /**
     * Create + immediately post a receipt to GL.
     */
    public function createPosted(
        Counterparty $customer,
        PaymentMethod $paymentMethod,
        string $amount,
        CarbonImmutable $receiptDate,
        User $createdBy,
        array $extras = [],
    ): Receipt {
        if (! $customer->isCustomer()) {
            throw new \DomainException(
                "Counterparty #{$customer->id} is not a customer (type: {$customer->type})"
            );
        }
        if (bccomp($amount, '0', 2) <= 0) {
            throw new \DomainException("Receipt amount must be > 0; got {$amount}");
        }
        if (! ($paymentMethod->is_cash || $paymentMethod->is_bank || $paymentMethod->is_mobile_money)) {
            throw new \DomainException(
                "Payment method '{$paymentMethod->name}' is not a cash-equivalent instrument"
            );
        }

        $organizationId = $this->currentOrganization->resolve()?->id;

        $receiptNumber = $this->numbering->next(
            sequenceType:   'receipt',
            prefix:         'RCT',
            year:           (int) $receiptDate->format('Y'),
            organizationId: $organizationId,
        );

        return DB::transaction(function () use (
            $customer, $paymentMethod, $amount, $receiptDate, $createdBy, $extras, $organizationId, $receiptNumber
        ) {
            // Step 1: insert receipt as draft.
            $receipt = Receipt::create([
                'organization_id'    => $organizationId,
                'receipt_number'     => $receiptNumber,
                'receipt_date'       => $receiptDate->toDateString(),
                'counterparty_id'    => $customer->id,
                'currency_code'      => $extras['currency_code'] ?? $customer->currency_code ?? 'GHS',
                'exchange_rate'      => $extras['exchange_rate'] ?? '1',
                'amount'             => $amount,
                'allocated_amount'   => '0',
                'payment_method_id'  => $paymentMethod->id,
                'source_account_id'  => $extras['source_account_id'] ?? null,
                'external_reference' => $extras['external_reference'] ?? null,
                'reference_type'     => $extras['reference_type'] ?? $this->inferReferenceType($paymentMethod),
                'status'             => Receipt::STATUS_DRAFT,
                'is_backfilled'      => false,
                'created_by_user_id' => $createdBy->id,
                'description'        => $extras['description'] ?? null,
                'notes'              => $extras['notes'] ?? null,
                'internal_notes'     => $extras['internal_notes'] ?? null,
            ]);

            // Step 2: build + post the journal batch.
            $batchDraft = $this->buildBatchDraft($receipt, $paymentMethod, $customer);
            $batch = $this->postingEngine->post($batchDraft, $createdBy);

            // Step 3: link receipt to batch + flip status.
            $receipt->update([
                'status'           => Receipt::STATUS_POSTED,
                'journal_batch_id' => $batch->id,
            ]);

            return $receipt->fresh();
        });
    }

    /**
     * Create a backfilled receipt — no GL impact.
     *
     * Used by the BackfillPigSaleInvoicesCommand to materialise receipt
     * records for historical pig sales that were marked payment_status='paid'.
     * The cash and AR were already touched in legacy journal entries when
     * the original pig sale posted under Phase 1c.
     */
    public function createBackfilled(
        Counterparty $customer,
        ?PaymentMethod $paymentMethod,
        string $amount,
        CarbonImmutable $receiptDate,
        User $createdBy,
        array $extras = [],
    ): Receipt {
        if (bccomp($amount, '0', 2) <= 0) {
            throw new \DomainException("Backfilled receipt amount must be > 0; got {$amount}");
        }

        $organizationId = $this->currentOrganization->resolve()?->id;

        $receiptNumber = $this->numbering->next(
            sequenceType:   'receipt',
            prefix:         'RCT',
            year:           (int) $receiptDate->format('Y'),
            organizationId: $organizationId,
        );

        return Receipt::create([
            'organization_id'    => $organizationId,
            'receipt_number'     => $receiptNumber,
            'receipt_date'       => $receiptDate->toDateString(),
            'counterparty_id'    => $customer->id,
            'currency_code'      => $extras['currency_code'] ?? $customer->currency_code ?? 'GHS',
            'exchange_rate'      => $extras['exchange_rate'] ?? '1',
            'amount'             => $amount,
            'allocated_amount'   => '0',
            'payment_method_id'  => $paymentMethod?->id,
            'external_reference' => $extras['external_reference'] ?? null,
            'reference_type'     => 'backfill',
            'status'             => Receipt::STATUS_POSTED,  // Treated as posted for ledger
            'journal_batch_id'   => null,                    // No GL — legacy already posted
            'is_backfilled'      => true,
            'created_by_user_id' => $createdBy->id,
            'description'        => $extras['description'] ?? 'Backfilled from legacy pig sale',
            'internal_notes'     => $extras['internal_notes'] ?? null,
        ]);
    }

    /* internals */

    protected function buildBatchDraft(
        Receipt $receipt,
        PaymentMethod $paymentMethod,
        Counterparty $customer,
    ): JournalBatchDraft {
        $cashAccountId = $this->resolveCashAccountId($paymentMethod, $receipt);
        $arAccountId   = $this->resolveArAccountId($customer);

        $description = sprintf(
            'Receipt %s from %s (%s)',
            $receipt->receipt_number,
            $customer->name,
            $paymentMethod->name,
        );

        $journal = new JournalDraft(
            journalType: 'cash_receipt',
            lines: [
                JournalLineDraft::debit(
                    accountId: $cashAccountId,
                    amount:    (string) $receipt->amount,
                    description: $description,
                    dimensions: [
                        'counterparty_id'   => $customer->id,
                        'payment_method_id' => $paymentMethod->id,
                        'source_type'       => Receipt::class,
                        'source_id'         => $receipt->id,
                        'reference'         => $receipt->receipt_number,
                    ],
                ),
                JournalLineDraft::credit(
                    accountId: $arAccountId,
                    amount:    (string) $receipt->amount,
                    description: $description,
                    dimensions: [
                        'counterparty_id' => $customer->id,
                        'source_type'     => Receipt::class,
                        'source_id'       => $receipt->id,
                        'reference'       => $receipt->receipt_number,
                    ],
                ),
            ],
            reference:    $receipt->receipt_number,
            description:  $description,
            currencyCode: $receipt->currency_code,
            exchangeRate: (string) $receipt->exchange_rate,
            sourceType:   Receipt::class,
            sourceId:     $receipt->id,
        );

        return new JournalBatchDraft(
            batchType:        'auto_receipt',
            postingDate:      CarbonImmutable::instance($receipt->receipt_date),
            journals:         [$journal],
            description:      $description,
            sourceModule:     'receipts',
            sourceType:       Receipt::class,
            sourceId:         $receipt->id,
            idempotencyKey:   JournalPosting::buildIdempotencyKey(
                'receipt_posted',
                Receipt::class,
                $receipt->id,
                JournalPosting::PURPOSE_CASH_RECEIPT,
            ),
            postingPurpose:   JournalPosting::PURPOSE_CASH_RECEIPT,
            subscriberClass:  static::class,
        );
    }

    protected function resolveCashAccountId(PaymentMethod $paymentMethod, ?Receipt $receipt = null): int
    {
        if ($receipt?->source_account_id) {
            return (int) $receipt->source_account_id;
        }

        if ($paymentMethod->gl_account_hint) {
            try {
                return $this->accountResolver->byHint($paymentMethod->gl_account_hint)->id;
            } catch (\App\Finance\Resolvers\MissingAccountException) {
                // Fall through.
            }
        }
        return $this->accountResolver->byHint('SUSPENSE_RECEIPT')->id;
    }

    protected function resolveArAccountId(Counterparty $customer): int
    {
        $categoryAccountId = $customer->category?->ar_account_id;
        if ($categoryAccountId) {
            return (int) $categoryAccountId;
        }
        return $this->accountResolver->byCode('1110')->id;
    }

    protected function inferReferenceType(PaymentMethod $paymentMethod): string
    {
        if ($paymentMethod->is_mobile_money) return 'momo_transaction_id';
        if ($paymentMethod->is_bank)         return 'bank_transfer';
        if ($paymentMethod->is_cash)         return 'cash_receipt';
        return 'other';
    }
}
