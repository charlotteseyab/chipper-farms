<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quick expenses (Phase 4, blueprint §23).
 *
 * WHY A RECORD AND NOT JUST A JOURNAL
 * -----------------------------------
 * The accounting effect of a quick expense is a journal batch, and that batch is
 * the source of truth for the ledger. This table is not a second ledger — it is
 * the *farm-facing* record of the same event.
 *
 * It exists because a journal batch cannot answer the questions a farm operator
 * asks: which friendly category was chosen, which receipt photo belongs to it,
 * who entered it from their phone, was it paid now or put on the supplier's
 * account. Reconstructing "Electricity, paid from MTN MoMo" from account 6310
 * and 1060 is possible but lossy, and it makes the expense list a reporting
 * query rather than a record.
 *
 * IDEMPOTENCY IS THE REASON THIS IS A TABLE AND NOT A CONTROLLER ACTION
 * ---------------------------------------------------------------------
 * Prompt §36 requires that a quick expense must not duplicate on a double
 * click, a retry, a network hiccup or a browser resubmit. A farm manager
 * entering an expense on a phone with poor signal is the normal case, not the
 * edge case.
 *
 * `idempotency_key` carries a unique index per organisation. The second
 * submission of the same key finds the first row and returns it rather than
 * posting again. This is the same pattern `journal_postings.idempotency_key`
 * already uses for automatic journals; doing it at this layer as well means the
 * duplicate is stopped before it reaches the posting engine, so the user sees
 * their original expense rather than a duplicate-posting error.
 *
 * ORGANIZATION_ID IS NOT NULL
 * ---------------------------
 * Consistent with every new table in this programme.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('quick_expenses')) {
            return;
        }

        Schema::create('quick_expenses', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('organization_id');

            // Human reference, allocated through NumberingService so it is
            // concurrency-safe rather than derived from a count.
            $table->string('expense_number', 40)->nullable();

            $table->date('expense_date');

            // Friendly category key from config/expense_categories.php.
            $table->string('category_key', 60);

            // The account the category resolved to AT THE TIME OF POSTING.
            // Stored rather than re-derived: if the mapping is later changed,
            // this expense must keep explaining the journal it actually
            // produced.
            $table->string('expense_account_code', 20)->nullable();

            $table->string('description', 500);

            // Decimal, never float.
            $table->decimal('amount', 18, 2);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2);

            $table->string('currency_code', 3)->default('GHS');

            // paid_now | pay_later
            $table->string('settlement', 20)->default('paid_now');

            // Key from config('expense_categories.payment_accounts'), null for
            // pay-later.
            $table->string('payment_method_key', 40)->nullable();
            $table->string('payment_account_code', 20)->nullable();
            $table->string('external_reference', 120)->nullable();

            $table->unsignedBigInteger('supplier_id')->nullable();

            // Optional allocation — whole farm, a pig, a group, a facility.
            $table->string('allocation_type', 40)->nullable();
            $table->unsignedBigInteger('allocation_id')->nullable();

            // Tax profile snapshot, per Phase 2. Stored so a later profile
            // change cannot restate what this expense was charged.
            $table->unsignedBigInteger('tax_profile_id')->nullable();
            $table->json('tax_snapshot')->nullable();

            // draft | posted | failed
            $table->string('status', 20)->default('draft');
            $table->string('posting_error', 1000)->nullable();

            $table->unsignedBigInteger('journal_batch_id')->nullable();
            $table->unsignedBigInteger('vendor_invoice_id')->nullable();

            // True when the category could not be resolved and the amount went
            // to suspense. Drives a needs-attention item.
            $table->boolean('needs_reclassification')->default(false);

            $table->unsignedBigInteger('recorded_by_user_id')->nullable();

            $table->string('idempotency_key', 120);

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'idempotency_key'], 'quick_expenses_idem_unique');
            $table->index(['organization_id', 'expense_date'], 'quick_expenses_date_idx');
            $table->index(['organization_id', 'status'], 'quick_expenses_status_idx');
            $table->index(['organization_id', 'category_key'], 'quick_expenses_category_idx');

            $table->foreign('organization_id')
                ->references('id')->on('organizations')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quick_expenses');
    }
};
