<?php

namespace App\Services\Finance\Payments;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Posting\PostingEngine;
use App\Finance\Resolvers\FinanceAccountResolver;
use App\Models\Counterparty;
use App\Models\Finance\JournalPosting;
use App\Models\Finance\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\Numbering\NumberingService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Owns the payment lifecycle: create posted, create backfilled, cancel.
 *
 * createPosted is the ONLY production path. It atomically:
 *   1. Allocates a payment_number from NumberingService
 *   2. Persists the payment row
 *   3. Builds the cash journal: Dr Suspense Payment / Cr Cash (for the
 *      NET cash amount that actually left the bank — matches statement)
 *   4. Posts via PostingEngine (idempotent via journal_postings)
 *   5. Links payment.journal_batch_id to the resulting batch
 *
 * Why suspense, not direct-to-AP?
 *
 *   `payment.amount` is the NET cash that LEFT the bank (matches bank
 *   statement). For a vendor invoice with withholding, the AP we owe is
 *   the GROSS, and the difference is the WHT slice payable to GRA.
 *
 *   Routing payment.amount directly to AP at payment time would either
 *   under-relieve AP (if we used net) or overstate the cash credit (if
 *   we used gross). Neither matches reality.
 *
 *   Suspense Payment (2910) acts as an intermediate holding bucket. The
 *   cash journal at payment creation: Dr Suspense / Cr Cash for the net
 *   amount. Each subsequent allocation posts a balanced AP-relief +
 *   WHT-recognition + suspense-clearance journal:
 *
 *     Dr AP control       allocation.amount       (full gross)
 *         Cr Suspense Payment (gross - wht)        (clears suspense by net)
 *         Cr WHT Payable      wht                  (recognises tax obligation)
 *
 *   When the payment is fully allocated, suspense nets to zero. The
 *   "payment in transit" report (Phase 3d) shows non-zero suspense
 *   balances by counterparty — those are payments cut but not yet
 *   attributed to specific bills.
 *
 *   For payments to non-WHT suppliers, allocations omit the WHT line —
 *   the journal is simply Dr AP / Cr Suspense for the same amount.
 *
 * No draft state.
 *   Treasury creating a payment IS the cash-out authorisation. Permission
 *   `create_payment` granted to admin/treasurer/superuser ONLY (NOT
 *   accountant) per Decision 33.
 */
class PaymentService
{
    public function __construct(
        protected NumberingService $numbering,
        protected PostingEngine $postingEngine,
        protected FinanceAccountResolver $accountResolver,
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    /**
     * Create a payment AND post the cash journal in one atomic transaction.
     *
     * @param  string  $amount  The NET cash that leaves the bank.
     */
    public function createPosted(
        Counterparty $supplier,
        PaymentMethod $method,
        string $amount,
        \DateTimeInterface $paymentDate,
        User $createdBy,
        array $extra = [],
    ): Payment {
        $this->validateInputs($supplier, $method, $amount);

        $organizationId = $this->currentOrganization->resolve()?->id;

        return DB::transaction(function () use (
            $supplier, $method, $amount, $paymentDate, $createdBy, $extra, $organizationId
        ) {
            $paymentDate = CarbonImmutable::instance($paymentDate);

            $paymentNumber = $this->numbering->next(
                sequenceType:   'payment',
                prefix:         'PMT',
                year:           (int) $paymentDate->format('Y'),
                organizationId: $organizationId,
                padding:        6,
            );

            $payment = Payment::create([
                'organization_id'       => $organizationId,
                'payment_number'        => $paymentNumber,
                'payment_date'          => $paymentDate->toDateString(),
                'currency_code'         => $extra['currency_code'] ?? $supplier->currency_code ?? 'GHS',
                'exchange_rate'         => $extra['exchange_rate'] ?? '1',
                'counterparty_id'       => $supplier->id,
                'amount'                => $amount,
                'allocated_amount'      => '0',
                'withholding_amount'    => '0',
                'net_amount'            => '0',
                'payment_method_id'     => $method->id,
                'source_account_id'     => $extra['source_account_id'] ?? null,
                'external_reference'    => $extra['external_reference'] ?? null,
                'reference_type'        => $extra['reference_type'] ?? $this->inferReferenceType($method),
                'payment_run_id'        => $extra['payment_run_id'] ?? null,
                'status'                => Payment::STATUS_POSTED,
                'is_backfilled'         => false,
                'created_by_user_id'    => $createdBy->id,
                'description'           => $extra['description'] ?? null,
                'notes'                 => $extra['notes'] ?? null,
                'internal_notes'        => $extra['internal_notes'] ?? null,
            ]);

            $batchDraft = $this->buildCashJournalDraft($payment, $supplier, $method);
            $batch = $this->postingEngine->post($batchDraft, $createdBy);

            $payment->update(['journal_batch_id' => $batch->id]);

            return $payment->fresh(['journalBatch', 'counterparty', 'paymentMethod']);
        });
    }

    /**
     * Create a backfilled payment — for historical reconciliation only.
     * Does NOT post a journal.
     */
    public function createBackfilled(
        Counterparty $supplier,
        PaymentMethod $method,
        string $amount,
        \DateTimeInterface $paymentDate,
        User $createdBy,
        array $extra = [],
    ): Payment {
        $this->validateInputs($supplier, $method, $amount);

        $organizationId = $this->currentOrganization->resolve()?->id;
        $paymentDate = CarbonImmutable::instance($paymentDate);

        $paymentNumber = $this->numbering->next(
            sequenceType:   'payment',
            prefix:         'PMT',
            year:           (int) $paymentDate->format('Y'),
            organizationId: $organizationId,
            padding:        6,
        );

        return Payment::create([
            'organization_id'    => $organizationId,
            'payment_number'     => $paymentNumber,
            'payment_date'       => $paymentDate->toDateString(),
            'currency_code'      => $extra['currency_code'] ?? $supplier->currency_code ?? 'GHS',
            'exchange_rate'      => '1',
            'counterparty_id'    => $supplier->id,
            'amount'             => $amount,
            'allocated_amount'   => '0',
            'withholding_amount' => '0',
            'net_amount'         => '0',
            'payment_method_id'  => $method->id,
            'source_account_id'  => $extra['source_account_id'] ?? null,
            'external_reference' => $extra['external_reference'] ?? null,
            'status'             => Payment::STATUS_POSTED,
            'is_backfilled'      => true,
            'created_by_user_id' => $createdBy->id,
            'description'        => $extra['description'] ?? 'Historical backfill — no GL impact',
            'notes'              => $extra['notes'] ?? null,
        ]);
    }

    public function cancel(Payment $payment, User $cancelledBy, string $reason): Payment
    {
        if (! $payment->canCancel()) {
            throw new \DomainException(
                "Payment {$payment->payment_number} cannot be cancelled (status: {$payment->status})"
            );
        }
        $payment->update([
            'status'               => Payment::STATUS_CANCELLED,
            'cancelled_by_user_id' => $cancelledBy->id,
            'cancelled_at'         => now(),
            'cancellation_reason'  => $reason,
        ]);
        return $payment->fresh();
    }

    /* internals */

    protected function validateInputs(Counterparty $supplier, PaymentMethod $method, string $amount): void
    {
        if (! $supplier->isSupplier()) {
            throw new \DomainException(
                "Counterparty #{$supplier->id} is not a supplier (type: {$supplier->type})"
            );
        }
        if (bccomp($amount, '0', 2) <= 0) {
            throw new \InvalidArgumentException("Payment amount must be > 0, got {$amount}");
        }
        if ($method->is_credit) {
            throw new \DomainException(
                "Payment method '{$method->code}' is credit terms, not a cash-out method. " .
                "Use a cash, bank, or mobile money method."
            );
        }
    }

    protected function buildCashJournalDraft(
        Payment $payment,
        Counterparty $supplier,
        PaymentMethod $method,
    ): JournalBatchDraft {
        $suspenseAccountId = $this->accountResolver->byHint('SUSPENSE_PAYMENT')->id;
        $cashAccountId     = $this->resolveCashAccountId($payment, $method);

        $description = sprintf(
            'Payment %s — %s — %s',
            $payment->payment_number,
            $supplier->name,
            $method->name,
        );
        if ($payment->external_reference) {
            $description .= " ({$payment->external_reference})";
        }

        $lines = [
            JournalLineDraft::debit(
                accountId:   $suspenseAccountId,
                amount:      (string) $payment->amount,
                description: $description,
                dimensions: [
                    'counterparty_id' => $supplier->id,
                    'source_type'     => Payment::class,
                    'source_id'       => $payment->id,
                    'reference'       => $payment->payment_number,
                ],
            ),
            JournalLineDraft::credit(
                accountId:   $cashAccountId,
                amount:      (string) $payment->amount,
                description: $description,
                dimensions: [
                    'counterparty_id' => $supplier->id,
                    'source_type'     => Payment::class,
                    'source_id'       => $payment->id,
                    'reference'       => $payment->payment_number,
                ],
            ),
        ];

        $journal = new JournalDraft(
            journalType:  'payment',
            lines:        $lines,
            reference:    $payment->payment_number,
            description:  $description,
            currencyCode: $payment->currency_code,
            exchangeRate: (string) $payment->exchange_rate,
            sourceType:   Payment::class,
            sourceId:     $payment->id,
        );

        return new JournalBatchDraft(
            batchType:        'auto_payment',
            postingDate:      CarbonImmutable::instance($payment->payment_date),
            journals:         [$journal],
            description:      $description,
            sourceModule:     'payments',
            sourceType:       Payment::class,
            sourceId:         $payment->id,
            idempotencyKey:   JournalPosting::buildIdempotencyKey(
                'payment_posted',
                Payment::class,
                $payment->id,
                JournalPosting::PURPOSE_CASH_PAYMENT,
            ),
            postingPurpose:   JournalPosting::PURPOSE_CASH_PAYMENT,
            subscriberClass:  static::class,
        );
    }

    protected function resolveCashAccountId(Payment $payment, PaymentMethod $method): int
    {
        if ($payment->source_account_id) {
            return (int) $payment->source_account_id;
        }
        if ($method->gl_account_hint) {
            try {
                return $this->accountResolver->byHint($method->gl_account_hint)->id;
            } catch (\Throwable) {
                // Fall through.
            }
        }
        return $this->accountResolver->byCode('1010')->id;
    }

    protected function inferReferenceType(PaymentMethod $method): ?string
    {
        return match (true) {
            $method->is_cash         => 'cash_voucher',
            $method->is_mobile_money => 'momo_txn',
            $method->code === 'CHEQUE' => 'cheque',
            $method->code === 'POS'    => 'card_auth',
            $method->is_bank         => 'wire',
            default                  => null,
        };
    }
}
