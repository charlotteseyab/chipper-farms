<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8 — amortisation schedule lines.
 *
 * One row per scheduled instalment, generated at disbursement (or draft).
 * This is the planned schedule; actual postings are in loan_transactions.
 * The `status` tracks how far each instalment has been serviced.
 *
 * opening_balance / closing_balance are the principal balance before/after
 * the period's principal component, so the schedule is self-checking.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('loan_schedule_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->unsignedBigInteger('loan_id')->index();

            $table->unsignedInteger('period_number');       // 1..term_months
            $table->date('due_date');

            $table->decimal('opening_balance', 18, 2);
            $table->decimal('interest_due', 18, 2);
            $table->decimal('principal_due', 18, 2);
            $table->decimal('total_due', 18, 2);
            $table->decimal('closing_balance', 18, 2);

            // Servicing.
            $table->decimal('interest_paid', 18, 2)->default(0);
            $table->decimal('principal_paid', 18, 2)->default(0);
            $table->boolean('interest_accrued')->default(false);
            // pending | partially_paid | paid
            $table->string('status', 20)->default('pending')->index();
            $table->date('paid_date')->nullable();

            $table->timestamps();

            $table->unique(['loan_id', 'period_number'], 'lsl_loan_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_schedule_lines');
    }
};
