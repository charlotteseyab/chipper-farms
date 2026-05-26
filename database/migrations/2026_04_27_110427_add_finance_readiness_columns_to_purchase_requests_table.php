<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0.8 — finance-readiness columns on purchase_requests.
 *
 * Adds AP-relevant fields. The existing table has `request_no` already
 * (that's the PR reference visible to operations). For finance we add a
 * separate `document_number` which is the AP document number once the PR
 * becomes an invoiced transaction.
 *
 *   - organization_id       — multi-tenant scope
 *   - document_number       — AP document identity
 *   - is_posted / posted_at — posting idempotency
 *   - supplier_id           — subledger-ready (FK to counterparties, nullable)
 *   - invoice_number        — supplier's own invoice number (separate from PR)
 *   - invoice_date          — date on the supplier's invoice
 *   - tax_code_id           — VAT code applied
 *   - withholding_rate      — WHT % for this supplier payment
 *   - legacy_source_id      — external reconciliation key
 *
 * All nullable, additive. Existing `approved_by` and `approved_at` columns
 * are preserved (Batch A's work added them; this migration does not duplicate).
 *
 * Safe and idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_requests')) {
            return;
        }

        Schema::table('purchase_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_requests', 'organization_id')) {
                $table->unsignedBigInteger('organization_id')->nullable()->after('id');
                $table->index('organization_id', 'purchase_requests_organization_id_idx');
            }

            if (! Schema::hasColumn('purchase_requests', 'document_number')) {
                $table->string('document_number', 50)->nullable()->after('request_no');
                $table->index('document_number', 'purchase_requests_document_number_idx');
            }

            if (! Schema::hasColumn('purchase_requests', 'is_posted')) {
                $table->boolean('is_posted')->default(false);
            }

            if (! Schema::hasColumn('purchase_requests', 'posted_at')) {
                $table->timestamp('posted_at')->nullable();
                $table->index(['is_posted', 'posted_at'], 'purchase_requests_posting_status_idx');
            }

            if (! Schema::hasColumn('purchase_requests', 'supplier_id')) {
                $table->unsignedBigInteger('supplier_id')->nullable();
                $table->index('supplier_id', 'purchase_requests_supplier_id_idx');
            }

            if (! Schema::hasColumn('purchase_requests', 'invoice_number')) {
                $table->string('invoice_number', 100)->nullable();
                $table->index('invoice_number', 'purchase_requests_invoice_number_idx');
            }

            if (! Schema::hasColumn('purchase_requests', 'invoice_date')) {
                $table->date('invoice_date')->nullable();
            }

            if (! Schema::hasColumn('purchase_requests', 'tax_code_id')) {
                $table->unsignedBigInteger('tax_code_id')->nullable();
                $table->index('tax_code_id', 'purchase_requests_tax_code_id_idx');
            }

            if (! Schema::hasColumn('purchase_requests', 'withholding_rate')) {
                $table->decimal('withholding_rate', 8, 4)->nullable();
            }

            if (! Schema::hasColumn('purchase_requests', 'legacy_source_id')) {
                $table->string('legacy_source_id', 100)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('purchase_requests')) {
            return;
        }

        Schema::table('purchase_requests', function (Blueprint $table) {
            foreach ([
                'purchase_requests_organization_id_idx',
                'purchase_requests_document_number_idx',
                'purchase_requests_posting_status_idx',
                'purchase_requests_supplier_id_idx',
                'purchase_requests_invoice_number_idx',
                'purchase_requests_tax_code_id_idx',
            ] as $idx) {
                try { $table->dropIndex($idx); } catch (\Throwable $e) { /* ignore */ }
            }

            foreach ([
                'organization_id',
                'document_number',
                'is_posted',
                'posted_at',
                'supplier_id',
                'invoice_number',
                'invoice_date',
                'tax_code_id',
                'withholding_rate',
                'legacy_source_id',
            ] as $col) {
                if (Schema::hasColumn('purchase_requests', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};