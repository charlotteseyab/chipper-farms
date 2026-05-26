<?php

namespace App\Services\Finance\WithholdingCertificates;

use App\Models\Counterparty;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentAllocation;
use App\Models\Finance\VendorInvoice;
use App\Models\Finance\WhtCertificate;
use App\Models\User;
use App\Services\Numbering\NumberingService;
use App\Support\CurrentOrganization;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * WHT certificate lifecycle.
 *
 * Roles:
 *   generateDraft       Create a new draft certificate for (supplier, period).
 *                       Computes totals from live allocations. Idempotent —
 *                       returns existing draft for the same key.
 *
 *   refreshDraft        Recompute totals from live data on an existing draft.
 *                       Useful as more allocations land in the period.
 *
 *   issue               Snapshot lines, render PDF, allocate certificate
 *                       number, transition draft → issued. Locks the
 *                       contributing allocations from reversal.
 *
 *   void                Void an issued certificate. Releases the
 *                       allocation-reversal lock. Reason required.
 *
 * Source data:
 *   We aggregate `payment_allocations` rows where:
 *     - status = 'active'
 *     - withholding_amount > 0
 *     - the linked payment is posted, non-reversed, non-backfilled
 *     - payment.counterparty_id = certificate.counterparty_id
 *     - payment.payment_date BETWEEN period_from AND period_to (inclusive)
 *
 *   Backfilled payments are excluded — they have no journal trail and
 *   would inflate the cert without a corresponding GL credit to WHT
 *   Payable. If you need to include historical WHT, post a manual
 *   journal first so the period is consistent.
 *
 * Ghana GRA notes:
 *   - Certificate format mirrors Form WHT 1A (services) / 1B (goods).
 *     For the farm's typical mix (feed ingredients, vet services,
 *     transport, professional fees), one consolidated layout works.
 *     If GRA requires per-rate breakdown (different rates have
 *     different filing forms), the line snapshot already records each
 *     allocation individually — extend the PDF Blade to group by rate.
 *   - The `supplier_tin_snapshot` is required on the cert for filing.
 *     If a supplier doesn't have a TIN, generation fails loud.
 *   - The issuer TIN comes from `config('finance.statements.company_tin')`.
 */
class WhtCertificateService
{
    public function __construct(
        protected NumberingService $numbering,
        protected WhtPeriodCalculator $periodCalculator,
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    /**
     * Create a new draft certificate, or return the existing draft for
     * the same (org, supplier, period) key.
     *
     * @param string $kind  WhtCertificate::PERIOD_KIND_QUARTER etc.
     */
    public function generateDraft(
        Counterparty $supplier,
        string $kind,
        int $year,
        int $index,
        User $createdBy,
    ): WhtCertificate {
        if (! $supplier->isSupplier()) {
            throw new \DomainException(
                "Counterparty #{$supplier->id} is not a supplier (type: {$supplier->type})"
            );
        }

        $orgId = $this->currentOrganization->resolve()?->id;
        $boundaries = $this->periodCalculator->boundaries($kind, $year, $index);

        return DB::transaction(function () use (
            $supplier, $kind, $year, $index, $boundaries, $createdBy, $orgId
        ) {
            // Reuse existing draft, if any, for the same key.
            $existing = WhtCertificate::query()
                ->where('counterparty_id', $supplier->id)
                ->where('period_kind', $kind)
                ->where('period_year', $year)
                ->where('period_index', $index)
                ->where('status', WhtCertificate::STATUS_DRAFT)
                ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $this->refreshDraftInternal($existing);
            }

            // Block creation if an issued (non-void) cert already covers this slot.
            $blocked = WhtCertificate::query()
                ->where('counterparty_id', $supplier->id)
                ->where('period_kind', $kind)
                ->where('period_year', $year)
                ->where('period_index', $index)
                ->where('status', WhtCertificate::STATUS_ISSUED)
                ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
                ->exists();

            if ($blocked) {
                throw new \DomainException(
                    "An issued WHT certificate already exists for {$supplier->name} for "
                    . $boundaries['label'] . '. Void it first if you need to regenerate.'
                );
            }

            $cert = WhtCertificate::create([
                'organization_id'   => $orgId,
                'counterparty_id'   => $supplier->id,
                'certificate_number'=> null,  // assigned at issue time
                'period_kind'       => $kind,
                'period_year'       => $year,
                'period_index'      => $index,
                'period_from'       => $boundaries['from']->toDateString(),
                'period_to'         => $boundaries['to']->toDateString(),
                'currency_code'     => $supplier->currency_code ?? 'GHS',
                'total_amount_paid' => '0',
                'total_withheld'    => '0',
                'payment_count'     => 0,
                'allocation_count'  => 0,
                'status'            => WhtCertificate::STATUS_DRAFT,
                'lines_snapshot'    => null,
                'created_by_user_id'=> $createdBy->id,
            ]);

            return $this->refreshDraftInternal($cert);
        });
    }

    public function refreshDraft(WhtCertificate $cert): WhtCertificate
    {
        if (! $cert->canRefresh()) {
            throw new \DomainException(
                "Cannot refresh certificate {$cert->id} in status '{$cert->status}'"
            );
        }
        return DB::transaction(fn () => $this->refreshDraftInternal($cert));
    }

    public function issue(WhtCertificate $cert, User $issuedBy): WhtCertificate
    {
        return DB::transaction(function () use ($cert, $issuedBy) {
            $cert = WhtCertificate::lockForUpdate()->findOrFail($cert->id);

            if (! $cert->canIssue()) {
                throw new \DomainException(
                    "Certificate {$cert->id} cannot be issued. " .
                    "(status='{$cert->status}', total_withheld={$cert->total_withheld})"
                );
            }

            // Refresh data + snapshot one final time before locking.
            $this->refreshDraftInternal($cert);
            $cert->refresh();

            // Re-check post-refresh.
            if (bccomp((string) $cert->total_withheld, '0', 2) <= 0) {
                throw new \DomainException(
                    'Refresh produced zero withholding. Nothing to certify.'
                );
            }

            // Validate supplier TIN — required on the cert for GRA filing.
            $supplier = $cert->counterparty;
            if (! $supplier) {
                throw new \DomainException("Supplier #{$cert->counterparty_id} not found.");
            }
            $supplierTin = trim((string) $supplier->tax_id);
            if ($supplierTin === '') {
                throw new \DomainException(
                    "Supplier {$supplier->name} has no TIN on file. " .
                    'Add their tax_id to the supplier record before issuing the certificate.'
                );
            }

            $orgId = $cert->organization_id;
            $certNumber = $this->numbering->next(
                sequenceType:   'wht_certificate',
                prefix:         "WHT-Q{$cert->period_index}",
                year:           $cert->period_year,
                organizationId: $orgId,
                padding:        6,
            );

            // Snapshot supplier + issuer identity at issue time.
            $cert->update([
                'certificate_number'        => $certNumber,
                'supplier_name_snapshot'    => $supplier->name,
                'supplier_tin_snapshot'     => $supplierTin,
                'supplier_address_snapshot' => $this->buildSupplierAddress($supplier),
                'issuer_name_snapshot'      => config('finance.statements.company_name', config('app.name')),
                'issuer_tin_snapshot'       => config('finance.statements.company_tin'),
            ]);

            // Render and store PDF.
            [$disk, $path] = $this->renderAndStorePdf($cert->fresh());
            $cert->update([
                'pdf_disk'           => $disk,
                'pdf_path'           => $path,
                'status'             => WhtCertificate::STATUS_ISSUED,
                'issued_at'          => now(),
                'issued_by_user_id'  => $issuedBy->id,
            ]);

            return $cert->fresh();
        });
    }

    public function void(WhtCertificate $cert, User $user, string $reason): WhtCertificate
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('Void reason is required.');
        }

        return DB::transaction(function () use ($cert, $user, $reason) {
            $cert = WhtCertificate::lockForUpdate()->findOrFail($cert->id);

            if (! $cert->canVoid()) {
                throw new \DomainException(
                    "Certificate {$cert->id} cannot be voided (status: '{$cert->status}')"
                );
            }

            $cert->update([
                'status'            => WhtCertificate::STATUS_VOID,
                'voided_at'         => now(),
                'voided_by_user_id' => $user->id,
                'void_reason'       => $reason,
            ]);

            return $cert->fresh();
        });
    }

    /**
     * Used by PaymentAllocationService::reverse via guard hook.
     *
     * Returns the issued certificates, if any, that include this
     * allocation. Caller decides whether to refuse the reversal or
     * surface a clear error.
     *
     * @return array<\App\Models\Finance\WhtCertificate>
     */
    public function issuedCertificatesCovering(PaymentAllocation $allocation): array
    {
        $allocation->loadMissing('payment');
        $payment = $allocation->payment;
        if (! $payment) return [];

        return WhtCertificate::query()
            ->activeCovering(
                counterpartyId: (int) $payment->counterparty_id,
                date:           CarbonImmutable::parse((string) $payment->payment_date),
            )
            ->get()
            ->filter(function (WhtCertificate $cert) use ($allocation) {
                $snap = $cert->lines_snapshot ?? [];
                $allocs = $snap['allocations'] ?? [];
                foreach ($allocs as $row) {
                    if ((int) ($row['allocation_id'] ?? 0) === (int) $allocation->id) {
                        return true;
                    }
                }
                return false;
            })
            ->values()
            ->all();
    }

    /* internals */

    /**
     * Recompute totals + snapshot from live allocations.
     *
     * @return WhtCertificate
     */
    protected function refreshDraftInternal(WhtCertificate $cert): WhtCertificate
    {
        $allocations = $this->fetchContributingAllocations($cert);

        $totalAmountPaid = '0';
        $totalWithheld   = '0';
        $paymentIds = [];
        $rows = [];

        foreach ($allocations as $alloc) {
            $totalAmountPaid = bcadd($totalAmountPaid, (string) $alloc->amount, 2);
            $totalWithheld   = bcadd($totalWithheld,   (string) $alloc->withholding_amount, 2);
            $paymentIds[$alloc->payment_id] = true;

            $rows[] = [
                'allocation_id'           => $alloc->id,
                'payment_id'              => $alloc->payment_id,
                'vendor_invoice_id'       => $alloc->vendor_invoice_id,
                'payment_date'            => optional($alloc->payment)->payment_date?->toDateString(),
                'payment_number'          => optional($alloc->payment)->payment_number,
                'vendor_invoice_number'   => optional($alloc->vendorInvoice)->vendor_invoice_number,
                'supplier_invoice_number' => optional($alloc->vendorInvoice)->supplier_invoice_number,
                'amount'                  => (string) $alloc->amount,
                'withholding_amount'      => (string) $alloc->withholding_amount,
            ];
        }

        $cert->update([
            'total_amount_paid' => number_format((float) $totalAmountPaid, 2, '.', ''),
            'total_withheld'    => number_format((float) $totalWithheld,   2, '.', ''),
            'payment_count'     => count($paymentIds),
            'allocation_count'  => count($rows),
            'lines_snapshot'    => [
                'as_of'       => now()->toIso8601String(),
                'allocations' => $rows,
            ],
        ]);

        return $cert->fresh();
    }

    /**
     * @return \Illuminate\Support\Collection<int, PaymentAllocation>
     */
    protected function fetchContributingAllocations(WhtCertificate $cert)
    {
        return PaymentAllocation::query()
            ->where('status', PaymentAllocation::STATUS_ACTIVE)
            ->where('withholding_amount', '>', 0)
            ->when($cert->organization_id, fn ($q) => $q->where('organization_id', $cert->organization_id))
            ->whereHas('payment', function ($q) use ($cert) {
                $q->where('counterparty_id', $cert->counterparty_id)
                  ->where('status', Payment::STATUS_POSTED)
                  ->where('is_backfilled', false)
                  ->whereDate('payment_date', '>=', $cert->period_from)
                  ->whereDate('payment_date', '<=', $cert->period_to);
            })
            ->with([
                'payment:id,counterparty_id,payment_number,payment_date,status',
                'vendorInvoice:id,vendor_invoice_number,supplier_invoice_number',
            ])
            ->orderBy('id')
            ->get();
    }

    protected function buildSupplierAddress(Counterparty $supplier): ?string
    {
        // counterparty_addresses may not be installed on every deployment;
        // try the relation if it exists, otherwise fall back gracefully.
        $bits = [];
        if (method_exists($supplier, 'addresses')) {
            $primary = $supplier->addresses()
                ->where('is_primary', true)
                ->orWhere('address_type', 'billing')
                ->first();
            if ($primary) {
                foreach (['line1', 'line2', 'city', 'region', 'country'] as $f) {
                    if (! empty($primary->{$f})) $bits[] = $primary->{$f};
                }
            }
        }
        return empty($bits) ? null : implode(', ', $bits);
    }

    protected function renderAndStorePdf(WhtCertificate $cert): array
    {
        $disk = config('finance.attachments.disk', 'local');
        $orgId = $cert->organization_id ?? 'noorg';
        $year  = $cert->period_year;
        $filename = "{$cert->certificate_number}.pdf";
        $path = "wht/{$orgId}/{$year}/{$filename}";

        $view = view('finance.pdf.wht-certificate', [
            'cert' => $cert,
            'rows' => $cert->lines_snapshot['allocations'] ?? [],
        ]);

        $pdfBinary = Pdf::loadHTML($view->render())
            ->setPaper('a4', 'portrait')
            ->output();

        Storage::disk($disk)->put($path, $pdfBinary);

        return [$disk, $path];
    }
}
