<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0.8 — finance-readiness columns on pig_sales.
 *
 * Adds nullable columns that future Phase 1 posting logic requires:
 *   - organization_id for multi-tenant scope (Phase 0.1 posture)
 *   - document_number for immutable document identity (invoice-like behaviour)
 *   - is_posted / posted_at for posting idempotency (prevents double-posting)
 *   - customer_id nullable FK to counterparties (subledger-ready)
 *   - payment_method_id nullable FK (replaces free-text payment_method over time)
 *   - cost_centre_id nullable FK (accounting dimension)
 *   - sales_channel_id nullable FK (accounting dimension)
 *   - tax_code_id nullable FK (VAT / NHIL / etc.)
 *   - legacy_source_id — historical id for external reconciliation
 *
 * All nullable. Existing rows unaffected. Existing reads unaffected. New
 * reads can opt in to the richer fields as Phase 1 adopts them.
 *
 * Safe and idempotent — each column is added only if not already present.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pig_sales')) {
            return;
        }

        Schema::table('pig_sales', function (Blueprint $table) {
            if (! Schema::hasColumn('pig_sales', 'organization_id')) {
                $table->unsignedBigInteger('organization_id')->nullable()->after('id');
                $table->index('organization_id', 'pig_sales_organization_id_idx');
            }

            if (! Schema::hasColumn('pig_sales', 'document_number')) {
                $table->string('document_number', 50)->nullable()->after('sale_date');
                $table->index('document_number', 'pig_sales_document_number_idx');
            }

            if (! Schema::hasColumn('pig_sales', 'is_posted')) {
                $table->boolean('is_posted')->default(false)->after('payment_status');
            }

            if (! Schema::hasColumn('pig_sales', 'posted_at')) {
                $table->timestamp('posted_at')->nullable()->after('is_posted');
                $table->index(['is_posted', 'posted_at'], 'pig_sales_posting_status_idx');
            }

            if (! Schema::hasColumn('pig_sales', 'customer_id')) {
                $table->unsignedBigInteger('customer_id')->nullable()->after('buyer_name');
                $table->index('customer_id', 'pig_sales_customer_id_idx');
            }

            if (! Schema::hasColumn('pig_sales', 'payment_method_id')) {
                $table->unsignedBigInteger('payment_method_id')->nullable()->after('payment_method');
                $table->index('payment_method_id', 'pig_sales_payment_method_id_idx');
            }

            if (! Schema::hasColumn('pig_sales', 'cost_centre_id')) {
                $table->unsignedBigInteger('cost_centre_id')->nullable();
                $table->index('cost_centre_id', 'pig_sales_cost_centre_id_idx');
            }

            if (! Schema::hasColumn('pig_sales', 'sales_channel_id')) {
                $table->unsignedBigInteger('sales_channel_id')->nullable();
                $table->index('sales_channel_id', 'pig_sales_channel_id_idx');
            }

            if (! Schema::hasColumn('pig_sales', 'tax_code_id')) {
                $table->unsignedBigInteger('tax_code_id')->nullable();
                $table->index('tax_code_id', 'pig_sales_tax_code_id_idx');
            }

            if (! Schema::hasColumn('pig_sales', 'legacy_source_id')) {
                $table->string('legacy_source_id', 100)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pig_sales')) {
            return;
        }

        Schema::table('pig_sales', function (Blueprint $table) {
            foreach ([
                'pig_sales_organization_id_idx',
                'pig_sales_document_number_idx',
                'pig_sales_posting_status_idx',
                'pig_sales_customer_id_idx',
                'pig_sales_payment_method_id_idx',
                'pig_sales_cost_centre_id_idx',
                'pig_sales_channel_id_idx',
                'pig_sales_tax_code_id_idx',
            ] as $idx) {
                try { $table->dropIndex($idx); } catch (\Throwable $e) { /* ignore */ }
            }

            foreach ([
                'organization_id',
                'document_number',
                'is_posted',
                'posted_at',
                'customer_id',
                'payment_method_id',
                'cost_centre_id',
                'sales_channel_id',
                'tax_code_id',
                'legacy_source_id',
            ] as $col) {
                if (Schema::hasColumn('pig_sales', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};