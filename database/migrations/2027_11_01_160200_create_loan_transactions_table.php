<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8 — loan transaction ledger.
 *
 * The authoritative event log for a loan: disbursement, interest accrual,
 * and repayment (split into principal / interest / fees / penalty). Each row
 * links to the GL journal batch that posted it, for full traceability and
 * reconciliation against the loan principal/accrual control accounts.
 *
 * txn_type:
 *   disbursement  Dr bank / Cr principal (and Dr fee expense if origination)
 *   accrual       Dr interest expense / Cr accrued interest
 *   repayment     Dr principal + Dr accrued interest (+fees/penalty) / Cr bank
 *   adjustment    manual correction (audited)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('loan_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->unsignedBigInteger('loan_id')->index();
            $table->unsignedBigInteger('schedule_line_id')->nullable()->index();

            $table->string('txn_type', 20)->index();   // disbursement|accrual|repayment|adjustment
            $table->date('txn_date');

            // Component breakdown (all >= 0; meaning depends on txn_type).
            $table->decimal('principal_amount', 18, 2)->default(0);
            $table->decimal('interest_amount', 18, 2)->default(0);
            $table->decimal('fee_amount', 18, 2)->default(0);
            $table->decimal('penalty_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);

            $table->unsignedBigInteger('journal_batch_id')->nullable();
            $table->unsignedBigInteger('bank_account_id')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();

            // Idempotency for scheduled accruals (one per loan/period/month).
            $table->string('idempotency_key', 191)->nullable();

            $table->string('notes', 1000)->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'idempotency_key'], 'lt_org_idem_unique');
            $table->index(['loan_id', 'txn_type'], 'lt_loan_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_transactions');
    }
};
