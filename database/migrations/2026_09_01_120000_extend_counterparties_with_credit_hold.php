<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2d — extend counterparties for credit control.
 *
 * Adds:
 *   credit_hold_mode  : 'none' | 'warn' | 'block' — Decision 24
 *                       Default 'warn' for backward compat: existing customers
 *                       continue silently, new flows get a soft warning.
 *   credit_hold_reason: optional manual note from credit controller
 *
 * Why on counterparties (not a separate credit_holds table):
 *   The hold mode is a static per-customer policy, not a transactional record.
 *   It changes when treasury re-evaluates a customer's standing — a few times
 *   a year per customer, not per transaction. Keeping it on the master row
 *   means every reference to the customer can immediately read their hold
 *   policy without a join.
 *
 * Idempotent + additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('counterparties')) {
            return;
        }

        Schema::table('counterparties', function (Blueprint $table) {
            if (! Schema::hasColumn('counterparties', 'credit_hold_mode')) {
                $table->string('credit_hold_mode', 10)->default('warn')->after('credit_limit');
            }
            if (! Schema::hasColumn('counterparties', 'credit_hold_reason')) {
                $table->text('credit_hold_reason')->nullable()->after('credit_hold_mode');
            }
            if (! Schema::hasColumn('counterparties', 'credit_hold_set_at')) {
                $table->timestamp('credit_hold_set_at')->nullable()->after('credit_hold_reason');
            }
            if (! Schema::hasColumn('counterparties', 'credit_hold_set_by_user_id')) {
                $table->unsignedBigInteger('credit_hold_set_by_user_id')->nullable()->after('credit_hold_set_at');
                try {
                    $table->foreign('credit_hold_set_by_user_id')
                          ->references('id')->on('users')->nullOnDelete();
                } catch (\Throwable) {
                    // FK may already exist or DB doesn't support it
                }
            }
        });

        try {
            $driver = Schema::getConnection()->getDriverName();
            if (in_array($driver, ['mysql', 'pgsql'], true)) {
                \DB::statement("ALTER TABLE counterparties ADD CONSTRAINT counterparties_credit_hold_mode_check CHECK (credit_hold_mode IN ('none', 'warn', 'block'))");
            }
        } catch (\Throwable) {
            // CHECK already exists or unsupported
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('counterparties')) {
            return;
        }

        Schema::table('counterparties', function (Blueprint $table) {
            try { $table->dropForeign(['credit_hold_set_by_user_id']); } catch (\Throwable) {}

            $cols = array_filter([
                Schema::hasColumn('counterparties', 'credit_hold_mode')         ? 'credit_hold_mode'         : null,
                Schema::hasColumn('counterparties', 'credit_hold_reason')       ? 'credit_hold_reason'       : null,
                Schema::hasColumn('counterparties', 'credit_hold_set_at')       ? 'credit_hold_set_at'       : null,
                Schema::hasColumn('counterparties', 'credit_hold_set_by_user_id') ? 'credit_hold_set_by_user_id' : null,
            ]);
            if ($cols) {
                $table->dropColumn($cols);
            }
        });
    }
};
