<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6 — period close statement snapshots.
 *
 * When a period closes, the rendered financial statements are frozen here
 * so the close pack answers "what did this period look like when we closed
 * it?" regardless of any later backdated activity.
 *
 * One row per (run, statement_type). statement_type:
 *   trial_balance | income_statement | balance_sheet | cash_flow
 *
 * payload holds the fully-rendered statement as JSON (the same structure
 * the live report services return), so the close-pack viewer can render it
 * without recomputation.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('period_close_statements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('period_close_run_id')->index();

            $table->string('statement_type', 30)->index();
            $table->longText('payload'); // JSON; longText for large statements

            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['period_close_run_id', 'statement_type'], 'pcs_run_type_unique');
            $table->foreign('period_close_run_id')->references('id')->on('period_close_runs')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('period_close_statements');
    }
};
