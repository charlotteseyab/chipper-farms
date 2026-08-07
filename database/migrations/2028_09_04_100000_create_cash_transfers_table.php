<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Internal money transfers (Phase 5, blueprint §25, prompt §41).
 *
 * WHY A TRANSFER IS NOT AN EXPENSE
 * --------------------------------
 * Moving GHS 2,000 from the MTN wallet to the bank does not make the farm
 * poorer. It is the single most commonly mis-recorded farm transaction: entered
 * as a payment out of MoMo it becomes an expense, and the month's profit is
 * understated by the whole amount while the bank balance quietly rises with no
 * matching income.
 *
 * The accounting is two asset accounts and nothing else — Dr destination, Cr
 * source. No revenue account, no expense account, ever. That constraint is
 * asserted directly by test rather than left to convention.
 *
 * WHY A TABLE RATHER THAN JUST A JOURNAL
 * --------------------------------------
 * Same reasoning as quick_expenses. The journal is the ledger's record; this is
 * the record a treasurer recognises, with the provider reference needed to match
 * against a MoMo or bank statement later.
 *
 * It also carries the idempotency key. A transfer submitted twice would move the
 * money twice, and unlike an expense there is no external invoice to notice the
 * discrepancy against — the books would simply be wrong in a way that only shows
 * up at reconciliation.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cash_transfers')) {
            return;
        }

        Schema::create('cash_transfers', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('organization_id');

            $table->string('transfer_number', 40)->nullable();
            $table->date('transfer_date');

            $table->unsignedBigInteger('from_account_id');
            $table->unsignedBigInteger('to_account_id');

            $table->decimal('amount', 18, 2);
            $table->string('currency_code', 3)->default('GHS');

            $table->string('external_reference', 120)->nullable();
            $table->string('description', 500)->nullable();
            $table->text('notes')->nullable();

            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('journal_batch_id')->nullable();

            $table->unsignedBigInteger('recorded_by_user_id')->nullable();
            $table->string('idempotency_key', 120);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'idempotency_key'], 'cash_transfers_idem_unique');
            $table->index(['organization_id', 'transfer_date'], 'cash_transfers_date_idx');

            $table->foreign('organization_id')
                ->references('id')->on('organizations')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_transfers');
    }
};
