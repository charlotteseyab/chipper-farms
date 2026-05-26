<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2b — link pig_sales.invoice_id.
 *
 * Adds a nullable FK from pig_sales to invoices. When the new invoice
 * flow is enabled (settings flag finance.use_invoices_for_pig_sales),
 * the subscriber writes the new invoice id back to the pig_sale row.
 *
 * Backward compatible: existing pig sales have invoice_id=null, which is
 * fine. Future reports that need "the invoice for this pig" left-join.
 *
 * Idempotent and additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pig_sales') || Schema::hasColumn('pig_sales', 'invoice_id')) {
            return;
        }

        Schema::table('pig_sales', function (Blueprint $table) {
            $table->unsignedBigInteger('invoice_id')->nullable()->after('customer_id');
            $table->foreign('invoice_id', 'pig_sales_invoice_fk')
                  ->references('id')
                  ->on('invoices')
                  ->nullOnDelete();
            $table->index('invoice_id', 'pig_sales_invoice_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pig_sales') || ! Schema::hasColumn('pig_sales', 'invoice_id')) {
            return;
        }

        Schema::table('pig_sales', function (Blueprint $table) {
            try { $table->dropForeign('pig_sales_invoice_fk'); } catch (\Throwable) {}
            try { $table->dropIndex('pig_sales_invoice_idx'); } catch (\Throwable) {}
            $table->dropColumn('invoice_id');
        });
    }
};
