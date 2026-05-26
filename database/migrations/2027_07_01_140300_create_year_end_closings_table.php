<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6 — year-end closing records.
 *
 * Captures the year-end roll-forward: the closing journal that sweeps all
 * P&L accounts to zero into the Current Year Earnings clearing account
 * (3300) and then to Retained Earnings (3200), per the seeder's design
 * intent ("Closing entries land here; period-end roll-forward sweeps to
 * Retained Earnings").
 *
 * The actual double-entry lives in journal_batches/journal_lines (posted
 * via the normal PostingEngine, so it respects period locks and gets a
 * proper audit trail). This table is the operational record linking a
 * fiscal year to its closing batch and the computed net profit.
 *
 * Reversible: if a closed year is reopened, the closing batch is reversed
 * (via JournalReversalService) and this row is marked reversed.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('year_end_closings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->unsignedBigInteger('fiscal_year_id')->index();

            // in_progress | completed | reversed
            $table->string('status', 20)->default('in_progress')->index();

            $table->unsignedBigInteger('closing_journal_batch_id')->nullable();
            $table->unsignedBigInteger('reversal_journal_batch_id')->nullable();

            // Computed at close.
            $table->decimal('total_revenue', 18, 4)->default(0);
            $table->decimal('total_expense', 18, 4)->default(0);
            $table->decimal('net_profit', 18, 4)->default(0);

            $table->unsignedBigInteger('retained_earnings_account_id')->nullable();
            $table->unsignedBigInteger('current_year_earnings_account_id')->nullable();

            $table->unsignedBigInteger('performed_by_user_id')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamp('reversed_at')->nullable();
            $table->unsignedBigInteger('reversed_by_user_id')->nullable();
            $table->string('reversal_reason', 1000)->nullable();

            $table->string('notes', 2000)->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'fiscal_year_id'], 'yec_org_year_unique');
            $table->foreign('fiscal_year_id')->references('id')->on('fiscal_years')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('year_end_closings');
    }
};
