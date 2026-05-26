<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1a — journal_lines.
 *
 * The atomic unit of double-entry posting. Each line is either a debit
 * (debit > 0, credit = 0) or a credit (credit > 0, debit = 0), never both.
 * The DB CHECK constraint enforces this; PostingEngine also validates.
 *
 * Multi-currency-ready (Decision 3):
 *   debit / credit            — base currency (GHS) amounts. The numbers
 *                               that hit reports.
 *   debit_tc / credit_tc      — transaction currency amounts. For GHS-only
 *                               journals these equal debit/credit.
 *   currency_code, exchange_rate — captured at line level (not just journal
 *                               level) because some advanced scenarios
 *                               post mixed-currency lines in one journal.
 *                               For Phase 1, line currency always equals
 *                               journal currency; the redundancy is intentional
 *                               for forward compatibility.
 *
 * Accounting dimensions are tracked via dedicated FKs on the most common
 * dimensions (cost_centre_id, sales_channel_id) PLUS via the polymorphic
 * accounting_dimension_links table (Phase 0.8) for arbitrary dimensions.
 * The dedicated columns enable fast indexed reports; the polymorphic table
 * enables flexibility.
 *
 * Subledger linkage:
 *   counterparty_id  — for AR (customer) or AP (supplier) lines
 *   tax_code_id      — for tax-bearing lines, identifies which tax code
 *                      this line represents
 *   payment_method_id — for cash/bank lines, which method this is
 *
 * These pull straight from the Phase 0.8 reference tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('journal_lines')) {
            return;
        }

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();

            $table->unsignedBigInteger('journal_id');
            $table->foreign('journal_id')
                  ->references('id')
                  ->on('journals')
                  ->cascadeOnDelete();

            $table->unsignedSmallInteger('line_number');
            // 1-based line ordering within the journal. Display order on
            // the journal voucher / report.

            $table->unsignedBigInteger('account_id');
            $table->foreign('account_id')
                  ->references('id')
                  ->on('chart_of_accounts')
                  ->restrictOnDelete();
            // Restrict on delete: cannot delete a COA account that has
            // historical journal lines.

            // Base currency amounts (GHS).
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);

            // Transaction currency amounts. For GHS journals = debit/credit.
            $table->decimal('debit_tc', 18, 2)->default(0);
            $table->decimal('credit_tc', 18, 2)->default(0);

            $table->string('currency_code', 3)->default('GHS');
            $table->decimal('exchange_rate', 18, 8)->default(1);

            $table->string('description', 500)->nullable();

            // Direct dimension FKs for fast indexed reporting.
            $table->unsignedBigInteger('cost_centre_id')->nullable();
            $table->foreign('cost_centre_id')
                  ->references('id')
                  ->on('cost_centres')
                  ->nullOnDelete();

            $table->unsignedBigInteger('sales_channel_id')->nullable();
            $table->foreign('sales_channel_id')
                  ->references('id')
                  ->on('sales_channels')
                  ->nullOnDelete();

            // Subledger linkage.
            $table->unsignedBigInteger('counterparty_id')->nullable();
            $table->foreign('counterparty_id')
                  ->references('id')
                  ->on('counterparties')
                  ->nullOnDelete();

            $table->unsignedBigInteger('tax_code_id')->nullable();
            $table->foreign('tax_code_id')
                  ->references('id')
                  ->on('tax_codes')
                  ->nullOnDelete();

            $table->unsignedBigInteger('payment_method_id')->nullable();
            $table->foreign('payment_method_id')
                  ->references('id')
                  ->on('payment_methods')
                  ->nullOnDelete();

            // Operational dimension FKs (for direct reports on existing entities).
            $table->unsignedBigInteger('structure_id')->nullable();
            // Optional: if set, reports can attribute the cost/revenue to a structure.
            $table->unsignedBigInteger('room_id')->nullable();
            $table->unsignedBigInteger('group_id')->nullable();
            // We don't add FK constraints to structures/rooms/groups because
            // those are existing operational tables we don't want to couple
            // to a finance migration. The values are set by subscribers and
            // verified at posting time by PostingEngine.

            // Source linkage at line level.
            $table->string('source_type', 120)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            // Memo / reference text on the line.
            $table->string('reference', 100)->nullable();

            $table->timestamps();

            $table->unique(['journal_id', 'line_number'], 'journal_lines_journal_line_unique');
            $table->index(['organization_id', 'account_id'], 'journal_lines_org_account_idx');
            $table->index(['account_id', 'created_at'], 'journal_lines_account_chrono_idx');
            $table->index('counterparty_id', 'journal_lines_counterparty_idx');
            $table->index('cost_centre_id', 'journal_lines_cost_centre_idx');
            $table->index('sales_channel_id', 'journal_lines_sales_channel_idx');
            $table->index('tax_code_id', 'journal_lines_tax_code_idx');
            $table->index(['source_type', 'source_id'], 'journal_lines_source_idx');
        });

        // CHECK constraints — exactly-one-side enforcement.
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'sqlite', 'pgsql'], true)) {
            try {
                // Either debit > 0 with credit = 0, OR credit > 0 with debit = 0.
                // Lines with both 0 are not allowed (no zero-value lines).
                \DB::statement(
                    "ALTER TABLE journal_lines ADD CONSTRAINT journal_lines_one_sided_check "
                    . "CHECK ((debit > 0 AND credit = 0) OR (debit = 0 AND credit > 0))"
                );
            } catch (\Throwable $e) { /* older MySQL */ }

            try {
                \DB::statement(
                    "ALTER TABLE journal_lines ADD CONSTRAINT journal_lines_amounts_non_negative_check "
                    . "CHECK (debit >= 0 AND credit >= 0 AND debit_tc >= 0 AND credit_tc >= 0)"
                );
            } catch (\Throwable $e) { /* ignore */ }

            try {
                \DB::statement(
                    "ALTER TABLE journal_lines ADD CONSTRAINT journal_lines_tc_one_sided_check "
                    . "CHECK ((debit_tc > 0 AND credit_tc = 0) OR (debit_tc = 0 AND credit_tc > 0))"
                );
            } catch (\Throwable $e) { /* ignore */ }
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'sqlite', 'pgsql'], true)) {
            foreach ([
                'journal_lines_one_sided_check',
                'journal_lines_amounts_non_negative_check',
                'journal_lines_tc_one_sided_check',
            ] as $constraint) {
                try {
                    \DB::statement("ALTER TABLE journal_lines DROP CONSTRAINT {$constraint}");
                } catch (\Throwable $e) { /* ignore */ }
            }
        }

        Schema::dropIfExists('journal_lines');
    }
};
