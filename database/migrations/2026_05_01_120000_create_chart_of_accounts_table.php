<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1a — chart_of_accounts.
 *
 * Decision 9: 4-digit numeric block scheme. Codes are stored as strings
 * (not integers) because the convention is to pad and group as text:
 * '1000' < '1100' < '1200' lexicographic order matches numeric order
 * within the same code length, AND we keep the door open for sub-accounts
 * with letter suffixes ('1100A') if ever needed.
 *
 * account_type drives the natural balance side and report classification:
 *   asset      Dr balance, balance sheet — current assets
 *   liability  Cr balance, balance sheet — current liabilities
 *   equity     Cr balance, balance sheet — equity section
 *   revenue    Cr balance, income statement — revenue section
 *   expense    Dr balance, income statement — expense section
 *
 * Plus two flags:
 *   is_control      True for AR/AP/Inventory/Biological control accounts
 *                   that are reconciled to subledgers. Manual journals
 *                   targeting control accounts go through stricter
 *                   validation in PostingEngine (Phase 1b).
 *
 *   is_active       Soft-disable mechanism. Inactive accounts cannot
 *                   accept new postings but historical balances remain.
 *
 * The parent_id supports report grouping (e.g. "Cash and Bank" rolls up
 * "Cash on Hand" + "Bank Main" + "Bank Foreign"). Self-FK with nullable.
 *
 * Multi-currency-ready: each account has a `currency_code` (defaults GHS).
 * Per Decision 3, all accounts ship in GHS for now. When a foreign-
 * currency bank account is needed (Phase 5), a new account is created
 * with currency_code = 'USD' (etc.) and the FX revaluation logic activates.
 *
 * Tenant scope: organization_id present, nullable for the seed phase
 * (the default Chipper Farms org ID is set during seeding via
 * CurrentOrganization). FK constraint deferred to a follow-up tightening
 * migration once all rows are populated, per Phase 0 pattern.
 *
 * Idempotent and additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chart_of_accounts')) {
            return;
        }

        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();

            $table->string('code', 20);          // '1000', '1100A', etc.
            $table->string('name', 200);         // 'Cash on Hand'
            $table->text('description')->nullable();

            $table->string('account_type', 20);  // asset|liability|equity|revenue|expense
            $table->string('account_subtype', 50)->nullable();
            // Optional finer classification, e.g. 'current_asset', 'long_term_liability'.
            // Used in balance sheet sub-grouping.

            $table->unsignedBigInteger('parent_id')->nullable();
            $table->foreign('parent_id')
                  ->references('id')
                  ->on('chart_of_accounts')
                  ->nullOnDelete();

            $table->string('currency_code', 3)->default('GHS');
            // Multi-currency-ready (Decision 3). All seeded accounts are GHS.

            $table->boolean('is_control')->default(false);
            // True for AR/AP/Inventory/Biological/Loan control accounts.
            // PostingEngine restricts manual journal access to control accounts.

            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            // System accounts (e.g. 'Retained Earnings', 'Suspense') cannot be
            // deactivated by users — only by superuser via direct DB access.

            $table->boolean('allow_manual_journal')->default(true);
            // Some accounts should never accept manual journal entries
            // (e.g. AR Control, AP Control). Set false to force entries to
            // go through subledger flows.

            $table->unsignedSmallInteger('display_order')->default(0);
            // Within the same parent + same account_type, controls sort order
            // for trial balance and balance sheet rendering.

            $table->softDeletes();
            $table->timestamps();

            $table->unique(['organization_id', 'code'], 'coa_org_code_unique');
            $table->index(['organization_id', 'account_type', 'is_active'], 'coa_org_type_active_idx');
            $table->index(['organization_id', 'parent_id'], 'coa_org_parent_idx');
            $table->index(['account_type', 'display_order'], 'coa_type_order_idx');
        });

        // CHECK constraint on account_type. Portable across MySQL 8+, Postgres, SQLite.
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'sqlite', 'pgsql'], true)) {
            try {
                \DB::statement(
                    "ALTER TABLE chart_of_accounts ADD CONSTRAINT chart_of_accounts_account_type_check "
                    . "CHECK (account_type IN ('asset', 'liability', 'equity', 'revenue', 'expense'))"
                );
            } catch (\Throwable $e) {
                // Older MySQL parses but doesn't enforce — application validates anyway.
            }
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'sqlite', 'pgsql'], true)) {
            try {
                \DB::statement('ALTER TABLE chart_of_accounts DROP CONSTRAINT chart_of_accounts_account_type_check');
            } catch (\Throwable $e) { /* ignore */ }
        }

        Schema::dropIfExists('chart_of_accounts');
    }
};
