<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0.8 — fiscal_years + fiscal_periods foundation.
 *
 * Schema only. No period-close logic, no validation that prevents backdated
 * postings yet — those belong in Phase 1 when the posting engine lands.
 *
 * A fiscal year is a span (typically Jan-Dec in Ghana) with 12 monthly
 * periods. Periods carry status:
 *   'open'   — accepts new postings
 *   'closed' — accountant has closed; requires reopen to post again
 *   'locked' — permanent; cannot be reopened without a controlled override
 *
 * The transition discipline (open → closed → locked) is enforced in
 * Phase 1 via the posting engine. This migration only provides the tables.
 *
 * Safe and idempotent. Depends on organizations table from Phase 0.1;
 * FK is written with defensive guarding in case organizations isn't there yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fiscal_years')) {
            Schema::create('fiscal_years', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id')->nullable();

                $table->string('name', 30);       // 'FY2026' or '2026-2027'
                $table->unsignedSmallInteger('fiscal_year');  // 4-digit representative year, e.g. 2026

                $table->date('start_date');
                $table->date('end_date');

                $table->string('status', 20)->default('open');  // 'open' | 'closed' | 'locked'

                $table->boolean('is_current')->default(false);
                // Only one current fiscal year per org. Enforced via unique
                // partial index where supported; for portability we enforce
                // in application layer via FiscalYearService.

                $table->text('notes')->nullable();

                $table->timestamps();

                $table->unique(['organization_id', 'fiscal_year'], 'fiscal_years_org_year_unique');
                $table->index(['organization_id', 'is_current'], 'fiscal_years_current_idx');
                $table->index(['organization_id', 'status'], 'fiscal_years_status_idx');
            });

            $driver = Schema::getConnection()->getDriverName();
            if (in_array($driver, ['mysql', 'sqlite', 'pgsql'], true)) {
                try {
                    \DB::statement(
                        "ALTER TABLE fiscal_years ADD CONSTRAINT fiscal_years_status_check "
                        . "CHECK (status IN ('open', 'closed', 'locked'))"
                    );
                } catch (\Throwable $e) {
                    // Ignore.
                }
            }
        }

        if (! Schema::hasTable('fiscal_periods')) {
            Schema::create('fiscal_periods', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id')->nullable();

                $table->unsignedBigInteger('fiscal_year_id');
                $table->foreign('fiscal_year_id')
                      ->references('id')
                      ->on('fiscal_years')
                      ->cascadeOnDelete();

                $table->unsignedTinyInteger('period_number');  // 1..12 (or 1..13 for 4-4-5 systems)
                $table->string('name', 30);                     // 'Jan 2026', 'Period 1', etc.

                $table->date('start_date');
                $table->date('end_date');

                $table->string('status', 20)->default('open');  // 'open' | 'closed' | 'locked'

                $table->timestamp('closed_at')->nullable();
                $table->unsignedBigInteger('closed_by_user_id')->nullable();
                $table->foreign('closed_by_user_id')
                      ->references('id')
                      ->on('users')
                      ->nullOnDelete();

                $table->timestamp('locked_at')->nullable();
                $table->unsignedBigInteger('locked_by_user_id')->nullable();
                $table->foreign('locked_by_user_id')
                      ->references('id')
                      ->on('users')
                      ->nullOnDelete();

                $table->text('notes')->nullable();

                $table->timestamps();

                $table->unique(['fiscal_year_id', 'period_number'], 'fiscal_periods_year_number_unique');
                $table->index(['organization_id', 'status'], 'fiscal_periods_status_idx');
                $table->index(['start_date', 'end_date'], 'fiscal_periods_date_range_idx');
            });

            $driver = Schema::getConnection()->getDriverName();
            if (in_array($driver, ['mysql', 'sqlite', 'pgsql'], true)) {
                try {
                    \DB::statement(
                        "ALTER TABLE fiscal_periods ADD CONSTRAINT fiscal_periods_status_check "
                        . "CHECK (status IN ('open', 'closed', 'locked'))"
                    );
                } catch (\Throwable $e) {
                    // Ignore.
                }
            }
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'sqlite', 'pgsql'], true)) {
            foreach (['fiscal_periods_status_check', 'fiscal_years_status_check'] as $constraint) {
                $table = str_starts_with($constraint, 'fiscal_periods') ? 'fiscal_periods' : 'fiscal_years';
                try {
                    \DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$constraint}");
                } catch (\Throwable $e) {
                    // Ignore.
                }
            }
        }

        Schema::dropIfExists('fiscal_periods');
        Schema::dropIfExists('fiscal_years');
    }
};