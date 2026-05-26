<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6 — period close run + check tables.
 *
 * period_close_runs:    one row per attempt to close a fiscal period.
 *                       Holds the run status, the actor, the close-pack
 *                       trial-balance snapshot, and a checks summary.
 *
 * period_close_checks:  one row per validation check within a run
 *                       (tb_balanced, outbox_drained, no_draft_journals,
 *                       ar/ap/inventory/grir/pig_pool/bank reconciliation).
 *                       Each can pass, fail, be overridden (with reason),
 *                       or be skipped.
 *
 * Design notes:
 *   - A period can have multiple runs over time (a failed run, then a
 *     successful one after fixes). Only one run may be `completed` and it
 *     is the authoritative close for that period.
 *   - The trial-balance snapshot is stored as JSON on the run so the
 *     close pack is immutable even if later backdated entries change the
 *     live numbers. Statements (P&L / balance sheet / cash flow) live in
 *     a separate table (next migration) because they can be large.
 *   - override_count is denormalised onto the run for quick "was this a
 *     clean close?" reporting.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('period_close_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->unsignedBigInteger('fiscal_period_id')->index();

            // in_progress | completed | failed | reopened
            $table->string('status', 20)->default('in_progress')->index();

            // What close level this run targets / achieved.
            //   soft  -> period.status = closed   (adjusting entries allowed)
            //   hard  -> period.status = locked   (no postings)
            $table->string('close_level', 10)->default('soft');

            $table->unsignedBigInteger('initiated_by_user_id')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('completed_by_user_id')->nullable();

            // Reopen audit (set when this run is later reversed).
            $table->timestamp('reopened_at')->nullable();
            $table->unsignedBigInteger('reopened_by_user_id')->nullable();
            $table->string('reopen_reason', 1000)->nullable();

            // Immutable trial-balance snapshot at close (JSON).
            $table->longText('trial_balance_snapshot')->nullable();

            // Summary: {total: n, passed: n, failed: n, overridden: n, skipped: n}
            $table->json('checks_summary')->nullable();

            $table->unsignedInteger('override_count')->default(0);
            $table->string('notes', 2000)->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'fiscal_period_id', 'status'], 'pcr_org_period_status_idx');

            $table->foreign('fiscal_period_id')->references('id')->on('fiscal_periods')->cascadeOnDelete();
        });

        Schema::create('period_close_checks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('period_close_run_id')->index();

            // Stable machine key — see PeriodCloseCheck::KEY_* constants.
            $table->string('check_key', 50)->index();
            $table->string('label', 150);

            // pass | fail | overridden | skipped
            $table->string('status', 20)->default('fail');

            // Whether a failure of this check is allowed to be overridden.
            // Some checks (e.g. tb_balanced) are HARD and must never be
            // overridden — the close cannot proceed if the GL itself is
            // unbalanced.
            $table->boolean('is_overridable')->default(true);

            // The numbers behind the check (expected vs actual, variance).
            $table->json('detail')->nullable();

            // Override audit.
            $table->unsignedBigInteger('overridden_by_user_id')->nullable();
            $table->string('override_reason', 1000)->nullable();
            $table->timestamp('overridden_at')->nullable();

            $table->timestamps();

            $table->unique(['period_close_run_id', 'check_key'], 'pcc_run_key_unique');
            $table->foreign('period_close_run_id')->references('id')->on('period_close_runs')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('period_close_checks');
        Schema::dropIfExists('period_close_runs');
    }
};
