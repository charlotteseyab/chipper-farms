<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0.8 — finance-readiness columns on raw_material_stock_logs.
 *
 * Each stock log represents a receipt (GRN-like), a usage (consumption),
 * or an adjustment (count correction). Finance cares about:
 *   - receipts → posting Dr Inventory / Cr AP (or Cash)
 *   - usage    → posting Dr Feed WIP / Cr Inventory (at WAC from the
 *                valuation layer introduced in Phase 0.7)
 *   - adjustments → posting Dr/Cr Inventory Gain/Loss against the relevant
 *                   counter-account
 *
 * For each, Phase 1 needs:
 *   - organization_id        — tenant scope
 *   - document_number        — GRN/adj doc identity
 *   - is_posted / posted_at  — posting idempotency
 *   - supplier_id            — subledger-ready on receipts
 *   - cost_centre_id         — accounting dimension
 *   - legacy_source_id       — external reconciliation
 *
 * Safe and idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('raw_material_stock_logs')) {
            return;
        }

        Schema::table('raw_material_stock_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('raw_material_stock_logs', 'organization_id')) {
                $table->unsignedBigInteger('organization_id')->nullable()->after('id');
                $table->index('organization_id', 'rm_stock_logs_organization_id_idx');
            }

            if (! Schema::hasColumn('raw_material_stock_logs', 'document_number')) {
                $table->string('document_number', 50)->nullable();
                $table->index('document_number', 'rm_stock_logs_document_number_idx');
            }

            if (! Schema::hasColumn('raw_material_stock_logs', 'is_posted')) {
                $table->boolean('is_posted')->default(false);
            }

            if (! Schema::hasColumn('raw_material_stock_logs', 'posted_at')) {
                $table->timestamp('posted_at')->nullable();
                $table->index(['is_posted', 'posted_at'], 'rm_stock_logs_posting_status_idx');
            }

            if (! Schema::hasColumn('raw_material_stock_logs', 'supplier_id')) {
                $table->unsignedBigInteger('supplier_id')->nullable();
                $table->index('supplier_id', 'rm_stock_logs_supplier_id_idx');
            }

            if (! Schema::hasColumn('raw_material_stock_logs', 'cost_centre_id')) {
                $table->unsignedBigInteger('cost_centre_id')->nullable();
                $table->index('cost_centre_id', 'rm_stock_logs_cost_centre_id_idx');
            }

            if (! Schema::hasColumn('raw_material_stock_logs', 'legacy_source_id')) {
                $table->string('legacy_source_id', 100)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('raw_material_stock_logs')) {
            return;
        }

        Schema::table('raw_material_stock_logs', function (Blueprint $table) {
            foreach ([
                'rm_stock_logs_organization_id_idx',
                'rm_stock_logs_document_number_idx',
                'rm_stock_logs_posting_status_idx',
                'rm_stock_logs_supplier_id_idx',
                'rm_stock_logs_cost_centre_id_idx',
            ] as $idx) {
                try { $table->dropIndex($idx); } catch (\Throwable $e) { /* ignore */ }
            }

            foreach ([
                'organization_id',
                'document_number',
                'is_posted',
                'posted_at',
                'supplier_id',
                'cost_centre_id',
                'legacy_source_id',
            ] as $col) {
                if (Schema::hasColumn('raw_material_stock_logs', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};