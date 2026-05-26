<?php

namespace App\Services\Finance\Refunds;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Posting\PostingEngine;
use App\Finance\Resolvers\FinanceAccountResolver;
use App\Models\Counterparty;
use App\Models\Finance\JournalPosting;
use App\Models\Finance\Receipt;
use App\Models\Finance\Refund;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\Finance\JournalReversalService;
use App\Services\Numbering\NumberingService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * RefundService — money paid back to a customer.
 *
 * Lifecycle: draft → posted → reversed
 *
 * Posting:
 *   Posts Dr AR control / Cr Cash. The reverse of a receipt posting.
 *
 *   Why Dr AR control?
 *     If the refund traces to a specific over-receipt that hasn't been
 *     allocated, the original receipt credited AR control (Cr AR with
 *     no offsetting allocation = customer credit balance). The refund
 *     pays that credit out by debiting AR control back to zero on
 *     that customer's sub-ledger.
 *
 *     If the refund is standalone (no source_receipt_id), the customer
 *     is being given a goodwill payment — but it still flows through AR
 *     control. The customer's sub-ledger then shows a debit balance which
 *     a subsequent invoice can clear.
 *
 *   Why not Dr Refund Expense?
 *     Some accounting traditions book refunds as expenses. We don't —
 *     a refund of customer money is not an expense, it's a return of
 *     liability/credit. Expense recognition would understate revenue and
 *     overstate expenses simultaneously, distorting the P&L.
 *
 *     For "goodwill compensation" cases that ARE genuinely an expense
 *     (we're giving the customer money we don't owe them, not refunding
 *     theirs), the right flow is: post a manual journal Dr Marketing or
 *     Dr Customer Goodwill / Cr Cash. Don't use the refund flow.
 *
 * Validation:
 *   - amount > 0
 *   - currency matches customer
 *   - if source_receipt_id is set:
 *       - source must belong to the same customer
 *       - source must be posted (not reversed)
 *       - amount <= source.unallocatedAmount  ← critical: prevents
 *         refunding cash that's already been applied to invoices
 *   - source receipt must NOT be backfilled (backfilled receipts have
 *     no journal_batch_id; refunding them would create asymmetric GL
 *     entries — Phase 6+ may revisit if needed)
 *
 * Approval audit:
 *   approved_by_user_id + approved_at captured at the post() step.
 *   The patch enforces approval permission separately from creation —
 *   a junior accountant can DRAFT a refund; treasury must POST it.
 */
class RefundService
{
    public function __construct(
        protected NumberingService $numbering,
        protected PostingEngine $postingEngine,
        protected FinanceAccountResolver $accountResolver,
        protected JournalReversalService $reversalService,
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    /**
     * Create a draft refund. Validation runs but no GL impact.
     */
    public function createDraft(
        Counterparty $customer,
        PaymentMethod $paymentMethod,
        string $amount,
        CarbonImmutable $refundDate,
        User $createdBy,
        array $extras = [],
    ): Refund {
        if (! $customer->isCustomer()) {
            throw new \DomainException("Counterparty #{$customer->id} is not a customer");
        }
        if (bccomp($amount, '0', 2) <= 0) {
            throw new \DomainException("Refund amount must be > 0");
        }
        if (! ($paymentMethod->is_cash || $paymentMethod->is_bank || $paymentMethod->is_mobile_money)) {
            throw new \DomainException(
                "Payment method '{$paymentMethod->name}' is not a cash-equivalent instrument"
            );
        }

        // Validate source receipt linkage if provided.
        $sourceReceiptId = $extras['source_receipt_id'] ?? null;
        if ($sourceReceiptId) {
            $source = Receipt::findOrFail($sourceReceiptId);
            if ((int) $source->counterparty_id !== (int) $customer->id) {
                throw new \DomainException(
                    "Source receipt belongs to customer #{$source->counterparty_id}, refund is for #{$customer->id}"
                );
            }
            if (! $source->isPosted()) {
                throw new \DomainException("Source receipt #{$source->id} is not posted (status: {$source->status})");
            }
            if ($source->is_backfilled) {
                throw new \DomainException(
                    "Cannot refund from backfilled receipt #{$source->id} — backfilled receipts have no journal batch."
                );
            }
            $unalloc = $source->unallocatedAmount();
            if (bccomp($amount, $unalloc, 2) > 0) {
                throw new \DomainException(
                    "Refund amount {$amount} exceeds receipt's unallocated balance {$unalloc}. " .
                    "Cash already allocated to invoices cannot be refunded — reverse the allocations first."
                );
            }
            if ($source->currency_code !== ($extras['currency_code'] ?? $customer->currency_code)) {
                throw new \DomainException("Currency mismatch with source receipt");
            }
        }

        $organizationId = $this->currentOrganization->resolve()?->id;
        $refundNumber = $this->numbering->next(
            sequenceType:   'refund',
            prefix:         'REF',
            year:           (int) $refundDate->format('Y'),
            organizationId: $organizationId,
        );

        return Refund::create([
            'organization_id'    => $organizationId,
            'refund_number'      => $refundNumber,
            'refund_date'        => $refundDate->toDateString(),
            'counterparty_id'    => $customer->id,
            'currency_code'      => $extras['currency_code'] ?? $customer->currency_code ?? 'GHS',
            'exchange_rate'      => $extras['exchange_rate'] ?? '1',
            'amount'             => $amount,
            'payment_method_id'  => $paymentMethod->id,
            'source_receipt_id'  => $sourceReceiptId,
            'external_reference' => $extras['external_reference'] ?? null,
            'reference_type'     => $extras['reference_type'] ?? null,
            'status'             => Refund::STATUS_DRAFT,
            'created_by_user_id' => $createdBy->id,
            'reason'             => $extras['reason'] ?? null,
            'description'        => $extras['description'] ?? null,
            'notes'              => $extras['notes'] ?? null,
            'internal_notes'     => $extras['internal_notes'] ?? null,
        ]);
    }

    /**
     * Post a draft refund. Captures approver in approved_by_user_id.
     * Posts the GL via PostingEngine.
     *
     * Re-validates the source-receipt unallocated check INSIDE the
     * transaction, in case allocations changed between draft and post.
     */
    public function post(Refund $refund, User $approver): Refund
    {
        if (! $refund->canPost()) {
            throw new \DomainException("Refund #{$refund->id} cannot be posted (status: {$refund->status})");
        }

        return DB::transaction(function () use ($refund, $approver) {
            $refund = Refund::lockForUpdate()->findOrFail($refund->id);

            // Re-validate source receipt if linked — caller may have allocated
            // funds between draft and post.
            if ($refund->source_receipt_id) {
                $source = Receipt::lockForUpdate()->findOrFail($refund->source_receipt_id);
                $unalloc = $source->unallocatedAmount();
                if (bccomp((string) $refund->amount, $unalloc, 2) > 0) {
                    throw new \DomainException(
                        "Refund {$refund->amount} now exceeds source receipt's unallocated balance {$unalloc}. " .
                        "Allocations made since draft. Reverse allocations or reduce refund amount."
                    );
                }
            }

            $customer = Counterparty::findOrFail($refund->counterparty_id);
            $paymentMethod = PaymentMethod::findOrFail($refund->payment_method_id);

            $batchDraft = $this->buildBatchDraft($refund, $customer, $paymentMethod);
            $batch = $this->postingEngine->post($batchDraft, $approver);

            $refund->update([
                'status'              => Refund::STATUS_POSTED,
                'journal_batch_id'    => $batch->id,
                'approved_by_user_id' => $approver->id,
                'approved_at'         => now(),
            ]);

            return $refund->fresh();
        });
    }

    /**
     * Reverse a posted refund. Posts Dr Cash / Cr AR control (reverse of
     * the original refund posting).
     */
    public function reverse(Refund $refund, User $reverser, string $reason): Refund
    {
        if (! $refund->canReverse()) {
            throw new \DomainException("Refund #{$refund->id} cannot be reversed (status: {$refund->status})");
        }

        return DB::transaction(function () use ($refund, $reverser, $reason) {
            $refund = Refund::lockForUpdate()->findOrFail($refund->id);

            $reversalBatch = $this->reversalService->reverse(
                original: $refund->journalBatch,
                reverser: $reverser,
                reason:   "Refund {$refund->refund_number}: {$reason}",
            );

            $refund->update([
                'status'                       => Refund::STATUS_REVERSED,
                'reversed_by_journal_batch_id' => $reversalBatch->id,
                'reversed_by_user_id'          => $reverser->id,
                'reversed_at'                  => now(),
                'reversal_reason'              => $reason,
            ]);

            return $refund->fresh();
        });
    }

    /**
     * Cancel a draft refund. No GL impact — never posted.
     */
    public function cancel(Refund $refund, User $user): Refund
    {
        if (! $refund->canCancel()) {
            throw new \DomainException("Only draft refunds can be cancelled");
        }
        $refund->delete();
        return $refund;
    }

    /* internals */

    protected function buildBatchDraft(
        Refund $refund,
        Counterparty $customer,
        PaymentMethod $paymentMethod,
    ): JournalBatchDraft {
        $cashAccountId = $this->resolveCashAccountId($paymentMethod);
        $arAccountId   = $this->resolveArAccountId($customer);

        $description = sprintf(
            'Refund %s to %s (%s)%s',
            $refund->refund_number,
            $customer->name,
            $paymentMethod->name,
            $refund->reason ? " — {$refund->reason}" : '',
        );

        $journal = new JournalDraft(
            journalType: 'refund',
            lines: [
                JournalLineDraft::debit(
                    accountId: $arAccountId,
                    amount:    (string) $refund->amount,
                    description: $description,
                    dimensions: [
                        'counterparty_id' => $customer->id,
                        'source_type'     => Refund::class,
                        'source_id'       => $refund->id,
                        'reference'       => $refund->refund_number,
                    ],
                ),
                JournalLineDraft::credit(
                    accountId: $cashAccountId,
                    amount:    (string) $refund->amount,
                    description: $description,
                    dimensions: [
                        'counterparty_id'   => $customer->id,
                        'payment_method_id' => $paymentMethod->id,
                        'source_type'       => Refund::class,
                        'source_id'         => $refund->id,
                        'reference'         => $refund->refund_number,
                    ],
                ),
            ],
            reference:    $refund->refund_number,
            description:  $description,
            currencyCode: $refund->currency_code,
            exchangeRate: (string) $refund->exchange_rate,
            sourceType:   Refund::class,
            sourceId:     $refund->id,
        );

        return new JournalBatchDraft(
            batchType:        'auto_refund',
            postingDate:      CarbonImmutable::instance($refund->refund_date),
            journals:         [$journal],
            description:      $description,
            sourceModule:     'refunds',
            sourceType:       Refund::class,
            sourceId:         $refund->id,
            idempotencyKey:   JournalPosting::buildIdempotencyKey(
                'refund_posted',
                Refund::class,
                $refund->id,
                JournalPosting::PURPOSE_REFUND,
            ),
            postingPurpose:   JournalPosting::PURPOSE_REFUND,
            subscriberClass:  static::class,
        );
    }

    protected function resolveCashAccountId(PaymentMethod $paymentMethod): int
    {
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
}
