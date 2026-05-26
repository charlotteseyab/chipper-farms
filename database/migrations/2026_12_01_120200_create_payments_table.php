<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3c — payments.
 *
 * Records cash leaving the business to a supplier. Posts:
 *
 *   Dr  Suspense Payment (2910)    payment.amount   (NET cash that left bank)
 *       Cr  Cash/Bank                payment.amount
 *
 * (THIS journal — handled by PaymentService.createPosted.)
 *
 * Each subsequent allocation posts its own balanced AP-relief journal:
 *
 *   Dr  AP control               allocation.amount   (GROSS — what we owed)
 *       Cr  Suspense Payment        amount - wht      (net cash drawn back)
 *       Cr  WHT Payable (2250)      wht               (only if slice > 0)
 *
 * (Handled by PaymentAllocationService.allocate.)
 *
 * Why suspense at payment time?
 *   payment.amount is the NET cash leaving the bank (matches bank
 *   statement). The AP being relieved is the GROSS — they differ by
 *   the WHT slice. Suspense Payment (2910) holds the cash temporarily;
 *   each allocation moves a chunk of it onto AP relief while
 *   recognising the WHT obligation. When fully allocated, suspense
 *   nets to zero per payment.
 *
 *   See PaymentService docblock for full rationale.
 *
 * No draft state.
 *   Treasury creating a payment IS the authorisation — there's no
 *   draft phase. Two-person rule lives at vendor invoice approval
 *   (Phase 3b). create_payment to admin/treasurer/superuser ONLY.
 *
 * Status:
 *   posted     : created with a journal_batch_id (the cash journal)
 *   cancelled  : a draft that was abandoned (rare — only used by tests
 *                or special workflows)
 *   reversed   : a posted payment that was reversed. Reversing also
 *                reverses any active allocation journals.
 *   draft      : reserved for backfill staging; not used by standard create
 *
 * Backfill:
 *   is_backfilled=true means the payment was entered without producing
 *   a journal (historical reconciliation only). Backfilled payments
 *   cannot be reversed via the normal flow.
 *
 * Reconciliation hooks (Decision 35):
 *   reconciled_at + reconciled_by_user_id + reconciliation_reference
 *   are columns the bank-rec UI in Phase 3d will populate.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payments')) {
            return;
        }

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();

            $table->string('payment_number', 30);
            // PMT-YYYY-NNNNNN

            $table->date('payment_date');
            $table->string('currency_code', 3)->default('GHS');
            $table->decimal('exchange_rate', 20, 10)->default('1');

            // Counterparty (the supplier we're paying).
            $table->unsignedBigInteger('counterparty_id');
            $table->foreign('counterparty_id')
                  ->references('id')->on('counterparties')->restrictOnDelete();

            // Money — semantics under the suspense model (Decision 31):
            //   amount             = the NET cash that LEFT THE BANK on this
            //                        payment (matches bank statement). The
            //                        cash journal at create time is:
            //                          Dr Suspense Payment / Cr Cash    amount
            //   allocated_amount   = sum of active allocation.amount.
            //                        That is, GROSS AP relief. Equals the
            //                        face value of the bills paid down.
            //   withholding_amount = sum of active allocation.withholding.
            //                        WHT slices accumulated to date.
            //   net_amount         = allocated_amount - withholding_amount.
            //                        Equals the cash drawn from suspense.
            //                        When the payment is fully allocated,
            //                        net_amount == amount and suspense
            //                        nets to zero.
            $table->decimal('amount',             15, 2);
            // NET cash leaving bank. Required > 0.

            $table->decimal('allocated_amount',   15, 2)->default(0);
            // GROSS AP relief = sum of active allocations' amount.
            $table->decimal('withholding_amount', 15, 2)->default(0);
            // Sum of active allocations' WHT slices.
            $table->decimal('net_amount',         15, 2)->default(0);
            // = allocated - withheld = cash drawn from suspense.

            // Method.
            $table->unsignedBigInteger('payment_method_id');
            $table->foreign('payment_method_id')
                  ->references('id')->on('payment_methods')->restrictOnDelete();

            // Source GL account (the cash/bank/momo account being debited).
            // Optional override — if NULL, resolver uses
            // payment_method.gl_account_hint.
            $table->unsignedBigInteger('source_account_id')->nullable();
            $table->foreign('source_account_id')
                  ->references('id')->on('chart_of_accounts')->nullOnDelete();

            // External payment reference (cheque #, transaction ID, MOMO TXN).
            $table->string('external_reference', 100)->nullable();
            $table->string('reference_type', 30)->nullable();
            // 'cheque' | 'wire' | 'momo_txn' | 'card_auth' | 'cash_voucher'
            // Inferred from payment_method when not set explicitly.

            // Optional payment_run linkage — set when this payment was
            // materialised by a payment run executor.
            $table->unsignedBigInteger('payment_run_id')->nullable();
            $table->foreign('payment_run_id')
                  ->references('id')->on('payment_runs')->nullOnDelete();

            // Status.
            $table->string('status', 20)->default('posted');
            // draft | posted | cancelled | reversed

            // GL linkage.
            $table->unsignedBigInteger('journal_batch_id')->nullable();
            $table->foreign('journal_batch_id')
                  ->references('id')->on('journal_batches')->nullOnDelete();

            $table->unsignedBigInteger('reversed_by_journal_batch_id')->nullable();
            $table->foreign('reversed_by_journal_batch_id')
                  ->references('id')->on('journal_batches')->nullOnDelete();

            $table->boolean('is_backfilled')->default(false);
            // True for historical entries that don't have a corresponding
            // journal_batch_id (legacy reconciliation only).

            // Bank reconciliation hooks (Decision 35; UI in 3d).
            $table->timestamp('reconciled_at')->nullable();
            $table->unsignedBigInteger('reconciled_by_user_id')->nullable();
            $table->foreign('reconciled_by_user_id')
                  ->references('id')->on('users')->nullOnDelete();
            $table->string('reconciliation_reference', 100)->nullable();
            // Bank-statement-line ref or batch ref from the bank rec session.

            // Audit.
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->foreign('created_by_user_id')
                  ->references('id')->on('users')->nullOnDelete();

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

            $table->index(['organization_id', 'status', 'payment_date'], 'payments_status_date_idx');
            $table->index(['counterparty_id', 'status'], 'payments_counterparty_status_idx');
            $table->index('payment_run_id', 'payments_run_idx');
            $table->index(['organization_id', 'reconciled_at'], 'payments_reconciliation_idx');
            $table->unique(['organization_id', 'payment_number'], 'payments_org_number_unique');
        });

        try {
            $driver = Schema::getConnection()->getDriverName();
            if (in_array($driver, ['mysql', 'pgsql'], true)) {
                \DB::statement(
                    "ALTER TABLE payments ADD CONSTRAINT payments_status_check " .
                    "CHECK (status IN ('draft', 'posted', 'cancelled', 'reversed'))"
                );
                \DB::statement(
                    "ALTER TABLE payments ADD CONSTRAINT payments_amount_positive_check " .
                    "CHECK (amount > 0)"
                );
            }
        } catch (\Throwable) {}
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
