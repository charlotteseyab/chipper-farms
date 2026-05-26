<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3d — Withholding Tax certificates.
 *
 * One row per (supplier × tax-period) certificate. Generation rolls up
 * `payment_allocations.withholding_amount` for ACTIVE allocations whose
 * payment_date falls inside the period, grouped by counterparty.
 *
 * Lifecycle:
 *
 *   draft     Generated but not yet finalised. Re-running generation
 *             for the same (supplier, period) updates the totals from
 *             the live data. Useful while the period is still open.
 *
 *   issued    Locked snapshot. The `lines_snapshot` JSON column captures
 *             every contributing allocation_id + payment_id at issue time.
 *             PDF is rendered and stored. The certificate_number is
 *             allocated from NumberingService.
 *
 *             Once issued:
 *               - The underlying payment_allocations cannot be reversed
 *                 without first voiding the certificate (Decision 40).
 *                 The PaymentAllocationService check is added by patch
 *                 file PATCH_PAYMENT_ALLOCATION_SERVICE_3D.md.
 *               - PaymentService::cancel can still cancel an
 *                 unallocated payment — it doesn't touch allocation
 *                 history.
 *
 *   void      Issued certificate later voided (e.g. wrong period, error
 *             discovered, supplier changed TIN). A new draft can be
 *             generated. The void event preserves the original PDF for
 *             audit. Reason required.
 *
 * Why snapshot the lines on issue:
 *   The certificate is a regulatory document. Its totals must be
 *   reproducible exactly at any future date. If we lazily re-aggregated
 *   from live data, a subsequent allocation reversal in the same period
 *   would silently change the historical certificate, which is a Bad Thing.
 *   The snapshot freezes what was filed.
 *
 * Period model:
 *   period_kind    'quarter' (Ghana GRA standard) — extensible to 'month'
 *                  or 'year' if requirements change.
 *   period_year    e.g. 2026
 *   period_index   for 'quarter': 1..4
 *   period_from    inclusive date
 *   period_to      inclusive date
 *
 * Certificate numbering:
 *   WHT-YYYY-Q{n}-NNNNNN per organisation. Padded to 6 digits.
 *
 * PDF storage:
 *   Rendered with barryvdh/laravel-dompdf to a Blade template under
 *   resources/views/finance/pdf/wht-certificate.blade.php. Stored on the
 *   finance.attachments.disk with path
 *   `wht/{org_id}/{year}/{certificate_number}.pdf`. Path lives in
 *   `pdf_disk` + `pdf_path` for re-download.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('wht_certificates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();

            $table->unsignedBigInteger('counterparty_id');
            $table->foreign('counterparty_id')
                ->references('id')->on('counterparties')
                ->restrictOnDelete();

            // Numbering: nullable until issued, then enforced unique.
            $table->string('certificate_number', 30)->nullable();

            // Period dimensions.
            $table->string('period_kind', 10)->default('quarter');
            $table->unsignedSmallInteger('period_year');
            $table->unsignedSmallInteger('period_index');  // 1-4 for quarters
            $table->date('period_from');
            $table->date('period_to');

            $table->string('currency_code', 3)->default('GHS');

            // Aggregates (refreshed from snapshot on issue, or from live
            // data while draft).
            $table->decimal('total_amount_paid',  15, 2)->default(0); // gross AP relief in period
            $table->decimal('total_withheld',     15, 2)->default(0); // tax withheld in period
            $table->unsignedInteger('payment_count')->default(0);     // distinct payments contributing
            $table->unsignedInteger('allocation_count')->default(0);  // allocations contributing

            // Lifecycle.
            $table->string('status', 20)->default('draft');

            // Snapshot of contributing rows captured at issue time.
            // Shape:
            //   {
            //     "as_of": "2027-01-31T08:23:11Z",
            //     "allocations": [
            //       { "allocation_id": 123, "payment_id": 45,
            //         "vendor_invoice_id": 67, "payment_date": "2026-12-04",
            //         "vendor_invoice_number": "VI-2026-000089",
            //         "supplier_invoice_number": "ACM-9001",
            //         "amount": "57500.00", "withholding_amount": "3750.00" }
            //       ...
            //     ]
            //   }
            $table->json('lines_snapshot')->nullable();

            // Renderer artifact.
            $table->string('pdf_disk', 50)->nullable();
            $table->string('pdf_path', 255)->nullable();

            // Recipient of the certificate (snapshot supplier identity at
            // issue time so future name/TIN changes don't rewrite history).
            $table->string('supplier_name_snapshot', 200)->nullable();
            $table->string('supplier_tin_snapshot', 50)->nullable();
            $table->string('supplier_address_snapshot', 500)->nullable();
            $table->string('issuer_name_snapshot', 200)->nullable();
            $table->string('issuer_tin_snapshot', 50)->nullable();

            $table->text('notes')->nullable();

            // Audit timeline.
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->foreign('created_by_user_id')
                ->references('id')->on('users')
                ->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->unsignedBigInteger('issued_by_user_id')->nullable();
            $table->foreign('issued_by_user_id')
                ->references('id')->on('users')
                ->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->unsignedBigInteger('voided_by_user_id')->nullable();
            $table->foreign('voided_by_user_id')
                ->references('id')->on('users')
                ->nullOnDelete();
            $table->text('void_reason')->nullable();

            $table->timestamps();

            // Lookup paths.
            $table->index(['organization_id', 'counterparty_id', 'period_year', 'period_index'],
                'wht_org_cp_period_idx');
            $table->index(['organization_id', 'status', 'period_year'], 'wht_org_status_year_idx');
            $table->index(['organization_id', 'period_from', 'period_to'], 'wht_org_period_range_idx');

            // Certificate number must be unique per organisation when present.
            try {
                $table->unique(['organization_id', 'certificate_number'],
                    'wht_org_cert_number_unique');
            } catch (\Throwable $e) {
                // Some MySQL versions don't enforce composite-unique with
                // NULL in either column the way you'd want. Defended at
                // application layer via NumberingService.
            }

            // One non-void cert per (org, supplier, period) — soft enforced
            // at application layer; service ensures we don't issue two
            // active certificates for the same period.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wht_certificates');
    }
};
