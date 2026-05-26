<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1a — period_lock_dates.
 *
 * Per Decision 8 and the original prompt's "lock dates" requirement.
 *
 * fiscal_periods.status (open|closed|locked) controls whether a SPECIFIC
 * PERIOD accepts postings. period_lock_dates is a different concept: a
 * cutoff date BEFORE WHICH no postings are allowed regardless of fiscal
 * period status.
 *
 * Use cases:
 *   - "Lock everything before Dec 31, 2025" — protects historical data even
 *     if a fiscal period was reopened.
 *   - "Lock everything before [audit cutoff]" — protects audit-released data.
 *   - "Soft lock" before quarter close — preserves user ability to override
 *     in the period_lock_dates.is_hard column.
 *
 * Two-level locking model:
 *   1. Period status (fiscal_periods.status) — granular, per-period
 *   2. Period lock date (this table) — coarser, organisation-wide cutoff
 *
 * PostingEngine checks BOTH on every posting attempt. The stricter wins.
 *
 * Hard vs soft locks:
 *   is_hard = true  Cannot be bypassed even by superuser. Production-safety.
 *   is_hard = false Can be bypassed by user with `bypass_period_lock`
 *                   permission. For one-off late entries with explicit override.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('period_lock_dates')) {
            return;
        }

        Schema::create('period_lock_dates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();

            $table->date('lock_date');
            // No postings allowed with posting_date < lock_date.

            $table->boolean('is_hard')->default(false);
            // Hard locks cannot be bypassed.

            $table->boolean('is_active')->default(true);
            // Inactive lock dates are kept for audit history but don't enforce.

            $table->text('reason')->nullable();
            // Why was this lock placed? "Year-end close 2025", "Audit completed", etc.

            $table->unsignedBigInteger('locked_by_user_id');
            $table->foreign('locked_by_user_id')
                  ->references('id')
                  ->on('users')
                  ->restrictOnDelete();
            // User who placed the lock. Restrict on delete: cannot remove a
            // user who has placed locks (preserves audit chain).

            $table->timestamp('locked_at');

            $table->unsignedBigInteger('superseded_by_id')->nullable();
            $table->foreign('superseded_by_id')
                  ->references('id')
                  ->on('period_lock_dates')
                  ->nullOnDelete();
            // When a new lock date supersedes an old one, the old row keeps
            // its lock_date for audit but is_active=false and superseded_by_id
            // points at the new entry.

            $table->timestamps();

            $table->index(['organization_id', 'is_active', 'lock_date'], 'period_lock_dates_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('period_lock_dates');
    }
};
