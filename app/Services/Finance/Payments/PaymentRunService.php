<?php

namespace App\Services\Finance\Payments;

use App\Models\Finance\PaymentRun;
use App\Models\Finance\PaymentRunLine;
use App\Models\Finance\VendorInvoice;
use App\Models\User;
use App\Services\Numbering\NumberingService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Owns payment run lifecycle: create draft, add/remove lines, cancel,
 * and refresh aggregates. Execution is delegated to PaymentRunExecutor.
 */
class PaymentRunService
{
    public function __construct(
        protected NumberingService $numbering,
        protected WithholdingCalculator $withholdingCalculator,
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    public function createDraft(
        \DateTimeInterface $runDate,
        string $currencyCode,
        ?int $defaultPaymentMethodId,
        ?int $defaultSourceAccountId,
        User $createdBy,
        array $extra = [],
    ): PaymentRun {
        $organizationId = $this->currentOrganization->resolve()?->id;
        $runDate = CarbonImmutable::instance($runDate);

        $runNumber = $this->numbering->next(
            sequenceType:   'payment_run',
            prefix:         'PRUN',
            year:           (int) $runDate->format('Y'),
            organizationId: $organizationId,
            padding:        6,
        );

        return PaymentRun::create([
            'organization_id'             => $organizationId,
            'run_number'                  => $runNumber,
            'run_date'                    => $runDate->toDateString(),
            'currency_code'               => $currencyCode,
            'default_payment_method_id'   => $defaultPaymentMethodId,
            'default_source_account_id'   => $defaultSourceAccountId,
            'status'                      => PaymentRun::STATUS_DRAFT,
            'total_gross'                 => '0',
            'total_withholding'           => '0',
            'total_net'                   => '0',
            'payment_count'               => 0,
            'created_by_user_id'          => $createdBy->id,
            'description'                 => $extra['description'] ?? null,
            'notes'                       => $extra['notes'] ?? null,
        ]);
    }

    /**
     * Add a vendor invoice to a draft run as a line.
     * If amount is null, defaults to vi.balance_due.
     */
    public function addLine(
        PaymentRun $run,
        VendorInvoice $invoice,
        ?string $amount = null,
        ?int $paymentMethodOverrideId = null,
        ?int $sourceAccountOverrideId = null,
        ?string $notes = null,
    ): PaymentRunLine {
        if (! $run->isDraft()) {
            throw new \DomainException(
                "Cannot add lines to payment run in status '{$run->status}'"
            );
        }

        if (! $invoice->isPosted()) {
            throw new \DomainException(
                "Vendor invoice {$invoice->vendor_invoice_number} is not posted (status: {$invoice->status})"
            );
        }

        if ($run->organization_id !== $invoice->organization_id) {
            throw new \DomainException('Payment run and vendor invoice belong to different organisations');
        }

        if ($run->currency_code !== $invoice->currency_code) {
            throw new \DomainException(
                "Currency mismatch: run {$run->currency_code} vs invoice {$invoice->currency_code}"
            );
        }

        if (bccomp((string) $invoice->balance_due, '0', 2) <= 0) {
            throw new \DomainException(
                "Vendor invoice {$invoice->vendor_invoice_number} has zero balance due"
            );
        }

        // No duplicate lines for the same VI in the same run.
        $existing = $run->lines()->where('vendor_invoice_id', $invoice->id)->exists();
        if ($existing) {
            throw new \DomainException(
                "Vendor invoice {$invoice->vendor_invoice_number} is already a line on this run"
            );
        }

        // Default amount = balance_due.
        $amount = $amount ?? (string) $invoice->balance_due;
        if (bccomp($amount, '0', 2) <= 0) {
            throw new \InvalidArgumentException("Line amount must be > 0, got {$amount}");
        }
        if (bccomp($amount, (string) $invoice->balance_due, 2) > 0) {
            throw new \DomainException(
                "Line amount {$amount} exceeds vendor invoice balance due {$invoice->balance_due}"
            );
        }

        // Compute the WHT slice this line would contribute to. Same as the
        // allocation calculator — proportional or final-payment exact.
        $alreadyWithheld = (string) \App\Models\Finance\PaymentAllocation::query()
            ->where('vendor_invoice_id', $invoice->id)
            ->where('status', \App\Models\Finance\PaymentAllocation::STATUS_ACTIVE)
            ->sum('withholding_amount');

        $whtSlice = $this->withholdingCalculator->slice($invoice, $amount, $alreadyWithheld);

        $line = DB::transaction(function () use (
            $run, $invoice, $amount, $whtSlice,
            $paymentMethodOverrideId, $sourceAccountOverrideId, $notes
        ) {
            $line = PaymentRunLine::create([
                'organization_id'      => $run->organization_id,
                'payment_run_id'       => $run->id,
                'vendor_invoice_id'    => $invoice->id,
                'counterparty_id'      => $invoice->counterparty_id,
                'amount'               => $amount,
                'withholding_amount'   => $whtSlice,
                'payment_method_id'    => $paymentMethodOverrideId,
                'source_account_id'    => $sourceAccountOverrideId,
                'notes'                => $notes,
            ]);

            $this->refreshAggregates($run);

            return $line;
        });

        return $line->fresh();
    }

    public function removeLine(PaymentRunLine $line): void
    {
        $run = $line->paymentRun;
        if (! $run->isDraft()) {
            throw new \DomainException(
                "Cannot remove lines from payment run in status '{$run->status}'"
            );
        }
        if ($line->payment_id !== null) {
            throw new \DomainException(
                "Line is already executed (linked to payment #{$line->payment_id})"
            );
        }

        DB::transaction(function () use ($line, $run) {
            $line->delete();
            $this->refreshAggregates($run);
        });
    }

    public function cancel(PaymentRun $run, User $cancelledBy, string $reason): PaymentRun
    {
        if (! $run->canCancel()) {
            throw new \DomainException(
                "Payment run {$run->run_number} cannot be cancelled (status: {$run->status})"
            );
        }
        $run->update([
            'status'               => PaymentRun::STATUS_CANCELLED,
            'cancelled_by_user_id' => $cancelledBy->id,
            'cancelled_at'         => now(),
            'cancellation_reason'  => $reason,
        ]);
        return $run->fresh();
    }

    public function refreshAggregates(PaymentRun $run): void
    {
        $sums = $run->lines()
            ->selectRaw('
                COALESCE(SUM(amount), 0) AS gross,
                COALESCE(SUM(withholding_amount), 0) AS wht,
                COUNT(DISTINCT counterparty_id) AS distinct_counterparties
            ')
            ->first();

        $gross = number_format((float) ($sums?->gross ?? 0), 2, '.', '');
        $wht   = number_format((float) ($sums?->wht ?? 0), 2, '.', '');
        $net   = bcsub($gross, $wht, 2);
        $net   = number_format((float) $net, 2, '.', '');

        $run->update([
            'total_gross'        => $gross,
            'total_withholding'  => $wht,
            'total_net'          => $net,
            'payment_count'      => (int) ($sums?->distinct_counterparties ?? 0),
        ]);
    }
}
