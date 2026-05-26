<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3c — payment_runs.
 *
 * A payment run is the parent record for a batch of supplier payments.
 * Friday "pay everything due" workflow: treasury creates a run, adds
 * lines (one per VI), executes the run, and the executor materialises
 * one Payment per (counterparty, payment_method, source_account) group.
 *
 * State machine:
 *   draft → executed
 *   draft → cancelled
 *   executed → reversed   (reverses all child payments)
 *
 * Aggregate columns (total_gross, total_withholding, total_net,
 * payment_count) are maintained by PaymentRunService.refreshAggregates()
 * whenever lines are added/removed. They're also recomputed at execute
 * time to ensure accuracy.
 *
 * Defaults:
 *   default_payment_method_id and default_source_account_id are used by
 *   the executor to fill NULL line-level values BEFORE grouping. That
 *   means a line with explicit method/source overrides the run default
 *   (separate group); a line that leaves them NULL inherits the run
 *   default (joins the default group).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_runs')) {
            return;
        }

        Schema::create('payment_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();

            $table->string('run_number', 30);
            // PRUN-YYYY-NNNNNN

            $table->date('run_date');
            $table->string('currency_code', 3)->default('GHS');

            // Run-level defaults — fill NULL line-level values before grouping.
            $table->unsignedBigInteger('default_payment_method_id')->nullable();
            $table->foreign('default_payment_method_id')
                  ->references('id')->on('payment_methods')->nullOnDelete();

            $table->unsignedBigInteger('default_source_account_id')->nullable();
            $table->foreign('default_source_account_id')
                  ->references('id')->on('chart_of_accounts')->nullOnDelete();

            $table->string('status', 20)->default('draft');
            // draft | executed | cancelled | reversed

            // Aggregates (maintained by PaymentRunService::refreshAggregates).
            $table->decimal('total_gross',       15, 2)->default(0);
            $table->decimal('total_withholding', 15, 2)->default(0);
            $table->decimal('total_net',         15, 2)->default(0);
            $table->integer('payment_count')->default(0);
            // payment_count = distinct counterparty count (post-grouping size)

            // Audit
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->foreign('created_by_user_id')
                  ->references('id')->on('users')->nullOnDelete();

            $table->unsignedBigInteger('executed_by_user_id')->nullable();
            $table->foreign('executed_by_user_id')
                  ->references('id')->on('users')->nullOnDelete();
            $table->timestamp('executed_at')->nullable();

            $table->unsignedBigInteger('cancelled_by_user_id')->nullable();
            $table->foreign('cancelled_by_user_id')
                  ->references('id')->on('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->unsignedBigInteger('reversed_by_user_id')->nullable();
            $table->foreign('reversed_by_user_id')
                  ->references('id')->on('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();

            $table->text('description')->nullable();
            $table->text('notes')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['organization_id', 'status', 'run_date'], 'payment_runs_status_date_idx');
            $table->unique(['organization_id', 'run_number'], 'payment_runs_org_number_unique');
        });

        try {
            $driver = Schema::getConnection()->getDriverName();
            if (in_array($driver, ['mysql', 'pgsql'], true)) {
                \DB::statement(
                    "ALTER TABLE payment_runs ADD CONSTRAINT payment_runs_status_check " .
                    "CHECK (status IN ('draft', 'executed', 'cancelled', 'reversed'))"
                );
            }
        } catch (\Throwable) {
            // already exists or unsupported
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_runs');
    }
};
