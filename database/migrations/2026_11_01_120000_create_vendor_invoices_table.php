<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3b — vendor_invoices.
 *
 * Mirror of invoices (Phase 2b) on the AP side. A vendor invoice represents
 * a bill from a supplier that posts:
 *   Dr [Inventory|Expense|Asset] / Cr AP control
 *
 * State machine:
 *   draft → pending_approval → posted → reversed
 *   draft → cancelled
 *
 * Approval workflow (Decision 30):
 *   Vendor invoices require approval before posting by default. A junior
 *   accountant can DRAFT a bill (capture line items, attach the supplier's
 *   PDF, code GL accounts). Treasury or another authorised user with the
 *   approve_vendor_invoice permission must review and approve before any
 *   GL impact occurs. Auto-approval for clean three-way matches is gated
 *   by the finance.vendor_invoices.auto_approve_clean_match setting.
 *
 * Three-way matching (Decision 26):
 *   When purchase_request_id is set, Phase 3b's matching service compares
 *   each line's qty + price against:
 *     - the purchase_request_items it cites (PR side)
 *     - the raw_material_stock_logs received against the PR (receipt side)
 *   Tolerances are configurable (default: 5% qty, 2% price). Variances
 *   over tolerance are surfaced on the show page; posting still proceeds
 *   (soft matching) but the variance is recorded in the journal description.
 *
 * Source linkage (Decision 25):
 *   purchase_request_id is OPTIONAL. Two creation paths:
 *     - PR-linked: the bill traces to a specific PR. Most feed/raw material
 *       invoices fit here. Three-way matching applies. Lines pre-populate
 *       from purchase_request_items.
 *     - Standalone: the bill doesn't tie to a PR. Services, utilities, ad
 *       hoc expenses. Lines are entered manually. No three-way matching.
 *
 * Idempotency:
 *   When created via the PurchaseRequestInvoicingSubscriber (Phase 3b),
 *   the source_type+source_id pair plus a unique index prevents duplicate
 *   invoices from re-delivered events. When created manually, the duplicate
 *   guard is the supplier_invoice_number+supplier combination (a vendor
 *   shouldn't bill us under the same invoice number twice).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vendor_invoices')) {
            return;
        }

        Schema::create('vendor_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();

            $table->string('vendor_invoice_number', 30);
            // Our internal AP document reference. Format: VI-YYYY-NNNNNN.
            // Assigned by NumberingService.

            $table->string('supplier_invoice_number', 100)->nullable();
            // The vendor's own invoice number — what's printed on their bill.
            // Distinct from our internal reference. Used for duplicate prevention
            // and on the supplier statement.

            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->date('received_at')->nullable();
            // When operations actually got the bill (vs the date on the bill).
            // Useful for accruals — a January bill received in February
            // should still post in January's period.

            $table->unsignedBigInteger('counterparty_id');
            $table->foreign('counterparty_id')
                  ->references('id')->on('counterparties')->restrictOnDelete();

            $table->string('currency_code', 3)->default('GHS');
            $table->decimal('exchange_rate', 20, 10)->default('1');

            // Source linkage — Phase 3b uses this for purchase_request integration.
            $table->string('source_type', 100)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            // Optional direct PR reference (denormalised from source_type/source_id
            // when source is a PurchaseRequest). Lets reports filter "show me all
            // VIs against PR-2026-0042" without parsing source_type strings.
            $table->unsignedBigInteger('purchase_request_id')->nullable();
            $table->foreign('purchase_request_id')
                  ->references('id')->on('purchase_requests')->nullOnDelete();

            $table->string('status', 20)->default('draft');
            // 'draft' | 'pending_approval' | 'posted' | 'cancelled' | 'reversed'

            // Posting linkage (set when posted).
            $table->unsignedBigInteger('journal_batch_id')->nullable();
            $table->foreign('journal_batch_id')
                  ->references('id')->on('journal_batches')->nullOnDelete();

            $table->unsignedBigInteger('reversed_by_journal_batch_id')->nullable();
            $table->foreign('reversed_by_journal_batch_id')
                  ->references('id')->on('journal_batches')->nullOnDelete();

            // Totals — denormalised. Maintained by VendorInvoiceService.
            $table->decimal('subtotal',         15, 2)->default(0);
            $table->decimal('tax_total',        15, 2)->default(0);
            $table->decimal('withholding_total',15, 2)->default(0);
            // Withholding tax is a deduction we apply when paying — it does
            // NOT reduce the vendor's invoice total. It DOES reduce what we
            // pay them (Dr AP, Cr Cash + Cr WHT Payable). So withholding_total
            // is informational on the invoice; the AP control gets the FULL
            // total. Phase 3c's payment service uses this number to compute
            // what to actually disburse.
            $table->decimal('total',            15, 2)->default(0);

            // Payment tracking (for Phase 3c — denormalised aggregates).
            $table->decimal('amount_paid',  15, 2)->default(0);
            $table->decimal('balance_due',  15, 2)->default(0);
            $table->timestamp('fully_paid_at')->nullable();

            // Three-way matching state (Decision 26).
            $table->string('match_status', 20)->nullable();
            // NULL              : no PR linked, matching N/A
            // 'pending'         : PR linked, lines not yet validated
            // 'matched_clean'   : all lines within tolerance
            // 'matched_variance': variances exceed tolerance — recorded but allowed
            $table->json('match_variances')->nullable();
            // Per-line variance details captured at posting time. Read-only audit.

            // Approval workflow (Decision 30).
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->foreign('created_by_user_id')
                  ->references('id')->on('users')->nullOnDelete();

            $table->unsignedBigInteger('submitted_for_approval_by_user_id')->nullable();
            $table->foreign('submitted_for_approval_by_user_id')
                  ->references('id')->on('users')->nullOnDelete();
            $table->timestamp('submitted_for_approval_at')->nullable();

            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->foreign('approved_by_user_id')
                  ->references('id')->on('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->unsignedBigInteger('reversed_by_user_id')->nullable();
            $table->foreign('reversed_by_user_id')
                  ->references('id')->on('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();

            $table->unsignedBigInteger('cancelled_by_user_id')->nullable();
            $table->foreign('cancelled_by_user_id')
                  ->references('id')->on('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();

            $table->softDeletes();
            $table->timestamps();

            // Indexes
            $table->index(['organization_id', 'status', 'invoice_date'], 'vendor_invoices_status_date_idx');
            $table->index(['counterparty_id', 'status'], 'vendor_invoices_counterparty_status_idx');
            $table->index(['organization_id', 'balance_due'], 'vendor_invoices_org_balance_idx');
            $table->index(['source_type', 'source_id'], 'vendor_invoices_source_idx');
            $table->unique(['organization_id', 'vendor_invoice_number'], 'vendor_invoices_org_number_unique');

            // Duplicate-prevention: the same supplier shouldn't be billable for
            // the same supplier_invoice_number twice in the same org. NULL is
            // allowed (manual entries without a supplier ref).
            $table->unique(
                ['organization_id', 'counterparty_id', 'supplier_invoice_number'],
                'vendor_invoices_supplier_dedupe',
            );
        });

        try {
            $driver = Schema::getConnection()->getDriverName();
            if (in_array($driver, ['mysql', 'pgsql'], true)) {
                \DB::statement(
                    "ALTER TABLE vendor_invoices ADD CONSTRAINT vendor_invoices_status_check " .
                    "CHECK (status IN ('draft', 'pending_approval', 'posted', 'cancelled', 'reversed'))"
                );
                \DB::statement(
                    "ALTER TABLE vendor_invoices ADD CONSTRAINT vendor_invoices_match_status_check " .
                    "CHECK (match_status IS NULL OR match_status IN ('pending', 'matched_clean', 'matched_variance'))"
                );
            }
        } catch (\Throwable) {
            // CHECK already exists or unsupported
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_invoices');
    }
};
