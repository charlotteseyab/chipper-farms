<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8 — loans master table.
 *
 * One row per loan facility. The amortisation schedule lives in
 * loan_schedule_lines; the posted cash/interest events live in
 * loan_transactions. This table holds the loan terms and lifecycle status.
 *
 * Lifecycle:
 *   draft       created, schedule generated, not yet disbursed (no GL impact)
 *   active      disbursed; principal on the balance sheet; accruing
 *   settled     fully repaid (principal + interest cleared)
 *   cancelled   draft cancelled, or written off (audit retained)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();

            $table->string('reference', 50)->index();      // human ref e.g. LN-2026-001
            $table->string('loan_type', 20)->index();       // bank | equipment | investor
            $table->string('lender_name', 200);             // free text
            $table->unsignedBigInteger('counterparty_id')->nullable(); // optional link

            // Terms.
            $table->decimal('principal_amount', 18, 2);
            $table->decimal('annual_interest_rate', 9, 6);  // e.g. 0.240000 = 24%
            $table->unsignedInteger('term_months');
            $table->string('amortisation_method', 30)->default('reducing_balance');
            $table->date('disbursement_date')->nullable();
            $table->date('first_payment_date')->nullable();

            // Optional one-off fee charged at disbursement (financed or paid).
            $table->decimal('origination_fee', 18, 2)->default(0);

            // status + GL links.
            $table->string('status', 20)->default('draft')->index();
            $table->unsignedBigInteger('bank_account_id')->nullable(); // disbursed into / repaid from
            $table->unsignedBigInteger('disbursement_batch_id')->nullable();

            // Denormalised running figures (authoritative source is loan_transactions).
            $table->decimal('principal_outstanding', 18, 2)->default(0);
            $table->decimal('interest_accrued_outstanding', 18, 2)->default(0);
            $table->decimal('total_interest_charged', 18, 2)->default(0);
            $table->decimal('total_principal_repaid', 18, 2)->default(0);

            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamp('disbursed_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->string('notes', 2000)->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'reference'], 'loans_org_ref_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
