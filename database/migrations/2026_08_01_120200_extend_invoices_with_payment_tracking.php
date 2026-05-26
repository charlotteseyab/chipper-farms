<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2c — extend invoices with denormalised payment tracking.
 *
 * Adds five columns to the invoices table created in Phase 2b:
 *   - amount_paid       — sum of active allocations against this invoice
 *   - balance_due       — total - amount_paid (kept in sync atomically)
 *   - first_payment_at  — earliest allocation_date across active allocations
 *   - last_payment_at   — latest allocation_date across active allocations
 *   - fully_paid_at     — set when balance_due hits zero
 *
 * Why denormalised:
 *   AR ageing reports query thousands of invoices and sum allocations
 *   per invoice. With 50,000 invoices and an average 1.3 allocations
 *   each, computing balance_due on every page load is too slow. The
 *   columns are written atomically inside the same transaction that
 *   creates/reverses the allocation, with row-level locks on the invoice.
 *
 * Drift protection:
 *   - DB-level: receipt_allocations FK with cascadeOnDelete on invoice_id
 *     ensures we never have orphan allocations referring to deleted invoices.
 *   - App-level: AllocationService is the only writer of these columns.
 *     The service holds lockForUpdate on the invoice during allocation.
 *   - Audit-level: nightly `php artisan finance:reconcile-invoice-aggregates`
 *     command compares amount_paid to SUM(active allocations) across all
 *     posted invoices, reports drift. Run as cron.
 *
 * Backfill at deploy:
 *   - amount_paid defaults to 0 (correct: no allocations exist yet)
 *   - balance_due is set = total for posted invoices (only invoice without
 *     allocations have full balance due)
 *   - The other date columns stay null until payments arrive
 *
 * Idempotent + additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'amount_paid')) {
                $table->decimal('amount_paid', 15, 2)->default(0)->after('total');
            }
            if (! Schema::hasColumn('invoices', 'balance_due')) {
                $table->decimal('balance_due', 15, 2)->default(0)->after('amount_paid');
            }
            if (! Schema::hasColumn('invoices', 'first_payment_at')) {
                $table->timestamp('first_payment_at')->nullable()->after('balance_due');
            }
            if (! Schema::hasColumn('invoices', 'last_payment_at')) {
                $table->timestamp('last_payment_at')->nullable()->after('first_payment_at');
            }
            if (! Schema::hasColumn('invoices', 'fully_paid_at')) {
                $table->timestamp('fully_paid_at')->nullable()->after('last_payment_at');
            }
        });

        // Backfill balance_due = total for posted invoices that haven't
        // had Phase 2c apply yet (so balance_due currently is 0 from default).
        // Cash invoices get balance_due = 0 + fully_paid_at = posted_at to
        // mark them as already settled (they hit Cash, not AR).
        \DB::statement("
            UPDATE invoices
            SET balance_due = total
            WHERE balance_due = 0
              AND total > 0
              AND status = 'posted'
              AND is_cash_invoice = 0
              AND amount_paid = 0
        ");

        \DB::statement("
            UPDATE invoices
            SET balance_due = 0,
                amount_paid = total,
                fully_paid_at = posted_at
            WHERE status = 'posted'
              AND is_cash_invoice = 1
              AND fully_paid_at IS NULL
        ");

        // Index for AR ageing reports (Phase 2d will use this heavily).
        Schema::table('invoices', function (Blueprint $table) {
            try {
                $table->index(['organization_id', 'balance_due'], 'invoices_org_balance_idx');
            } catch (\Throwable) {
                // Index may already exist
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            try { $table->dropIndex('invoices_org_balance_idx'); } catch (\Throwable) {}

            $cols = array_filter([
                Schema::hasColumn('invoices', 'amount_paid')      ? 'amount_paid'      : null,
                Schema::hasColumn('invoices', 'balance_due')      ? 'balance_due'      : null,
                Schema::hasColumn('invoices', 'first_payment_at') ? 'first_payment_at' : null,
                Schema::hasColumn('invoices', 'last_payment_at')  ? 'last_payment_at'  : null,
                Schema::hasColumn('invoices', 'fully_paid_at')    ? 'fully_paid_at'    : null,
            ]);
            if ($cols) {
                $table->dropColumn($cols);
            }
        });
    }
};
