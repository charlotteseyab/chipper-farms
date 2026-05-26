<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 9 — investor transaction ledger + profit-allocation runs.
 *
 * investor_transactions:
 *   Authoritative event log per investor capital account: contribution,
 *   profit allocation, distribution, preferred-return accrual/payment. Each
 *   links to its GL journal batch for traceability and reconciliation.
 *
 * investor_profit_allocation_runs:
 *   Header for a profit-allocation run over a fiscal year/period. Records the
 *   profit pool allocated, the method, and links to the per-investor
 *   transactions it produced. One completed run per (year, scope) to prevent
 *   double allocation.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('investor_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->unsignedBigInteger('investor_id')->index();
            $table->unsignedBigInteger('capital_account_id')->nullable()->index();

            // contribution | allocation | distribution | preferred_accrual | preferred_payment
            $table->string('txn_type', 30)->index();
            $table->date('txn_date');
            $table->decimal('amount', 18, 2);

            $table->unsignedBigInteger('journal_batch_id')->nullable();
            $table->unsignedBigInteger('allocation_run_id')->nullable()->index();
            $table->unsignedBigInteger('bank_account_id')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();

            $table->string('idempotency_key', 191)->nullable();
            $table->string('notes', 1000)->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'idempotency_key'], 'itx_org_idem_unique');
            $table->index(['investor_id', 'txn_type'], 'itx_investor_type_idx');
        });

        Schema::create('investor_profit_allocation_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->unsignedBigInteger('fiscal_year_id')->nullable()->index();

            $table->string('method', 30);                 // ownership_pct | fixed_schedule
            $table->decimal('profit_pool', 18, 2);         // total amount allocated
            $table->date('allocation_date');
            $table->string('status', 20)->default('completed'); // completed | reversed
            $table->unsignedBigInteger('reversed_by_run_id')->nullable();

            $table->unsignedBigInteger('performed_by_user_id')->nullable();
            $table->string('notes', 2000)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'fiscal_year_id', 'status'], 'ipar_org_year_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investor_profit_allocation_runs');
        Schema::dropIfExists('investor_transactions');
    }
};
