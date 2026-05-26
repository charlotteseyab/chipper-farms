<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3e — Bank Accounts.
 *
 * Bank accounts are a thin overlay on a GL `accounts` row. The chart of
 * accounts already represents bank accounts as asset accounts (e.g.
 * 1020 Bank GCB Cedis, 1021 Bank GCB USD). This table adds the
 * bank-specific metadata that finance staff need but that doesn't
 * belong on the COA:
 *
 *   - bank name + account number for matching against statements
 *   - opening balance + as_of_date (the trustworthy starting point for
 *     reconciliation; subsequent ending balances are computed)
 *   - currency (usually matches the GL account but explicit is safer)
 *   - active/inactive toggle (a bank account can be retired without
 *     touching the COA row)
 *
 * Why one-to-one with accounts.id and not many-to-many:
 *   We need to be able to reconcile each bank GL line independently.
 *   If a bank gives you both a current and savings account, each gets
 *   its own GL account AND its own bank_account row. The two can be
 *   summarised together in reports, but reconciliation is per-line.
 *
 * Why account_number is required (when known):
 *   The CSV import tooling will use it to disambiguate which bank
 *   account a statement belongs to when one bank serves multiple
 *   accounts. If you genuinely don't have a number, leave it null
 *   and the system will require manual selection at import time.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();

            // The GL account that is THIS bank account in the books.
            $table->unsignedBigInteger('account_id');

            // Display + identification.
            $table->string('label', 120);                   // human label, e.g. "GCB - Operations"
            $table->string('bank_name', 100);
            $table->string('account_number', 64)->nullable();
            $table->string('swift_code', 20)->nullable();
            $table->string('branch', 100)->nullable();

            // Currency (sanity-must-match account.currency_code).
            $table->string('currency_code', 3)->default('GHS');

            // Opening balance — the trustworthy point from which we reconcile.
            // For a new bank account this is the balance on the day you
            // started using Chipper for it.
            $table->decimal('opening_balance', 18, 2)->default(0);
            $table->date('opening_balance_date');

            // Last reconciled balance — denormalised cache, updated when
            // a reconciliation closes. Used by the dashboard / quick view.
            // Distinct from opening_balance because it advances forward.
            $table->decimal('last_reconciled_balance', 18, 2)->nullable();
            $table->date('last_reconciled_date')->nullable();

            // Default CSV format hint — what parser to try first when
            // someone uploads a statement for this account. Optional;
            // user can override at upload time.
            $table->string('default_csv_format', 50)->nullable();

            // Lifecycle.
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            // Audit.
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // FKs.
            $table->foreign('organization_id')
                  ->references('id')->on('organizations')
                  ->cascadeOnUpdate()->nullOnDelete();
            $table->foreign('account_id')
                  ->references('id')->on('chart_of_accounts')
                  ->cascadeOnUpdate()->restrictOnDelete();
            $table->foreign('created_by_user_id')
                  ->references('id')->on('users')
                  ->cascadeOnUpdate()->nullOnDelete();

            // Indexes.
            $table->index(['organization_id', 'is_active'], 'bank_accounts_org_active_idx');
            $table->index('account_id', 'bank_accounts_account_idx');

            // One bank_accounts row per GL account, per org.
            // Wrapped in try/catch in case a deployment had ad-hoc
            // duplicates pre-existing — the index is idempotent.
            try {
                $table->unique(['organization_id', 'account_id'], 'bank_accounts_org_account_uq');
            } catch (\Throwable $e) {
                // Already exists or violation — log via the migration runner.
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
