<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0.8 — tax_codes reference.
 *
 * Represents each tax/levy/withholding code applicable to a transaction.
 * In Ghana the stack typically includes:
 *   - VAT (15% standard)
 *   - NHIL (National Health Insurance Levy, 2.5%)
 *   - GETFund (2.5%)
 *   - COVID-19 Health Recovery Levy (1%)
 *   - Withholding tax on supplier payments (5% or 7.5% depending on type)
 *
 * Each tax has:
 *   - rate (percentage)
 *   - rate_type: 'percent' or 'fixed' (for future per-unit duties)
 *   - applies_to: 'sale', 'purchase', 'both'
 *   - is_recoverable: whether input tax can be offset against output tax
 *     (relevant for VAT — NHIL/GETFund/COVID are not recoverable)
 *   - is_withholding: true for supplier withholding taxes
 *
 * The "levy group" pattern — where VAT/NHIL/GETFund/COVID all apply
 * simultaneously on a single sale line — is handled in Phase 1 by allowing
 * multiple tax_code_ids per source line, or by creating a composite code
 * (e.g., 'VAT_NHIL_GETFUND_COVID' at combined rate). For Phase 0 readiness
 * we just need the table and seed data.
 *
 * Safe and idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tax_codes')) {
            return;
        }

        Schema::create('tax_codes', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('organization_id')->nullable();

            $table->string('code', 30);   // 'VAT_15', 'NHIL_25', 'WHT_5', 'EXEMPT', 'ZERO'
            $table->string('name');

            $table->string('rate_type', 20)->default('percent');  // 'percent' | 'fixed'
            $table->decimal('rate', 8, 4)->default(0);            // 15.0000 for 15%, 0 for exempt
            // Using decimal(8,4) to support rates like 7.5% (7.5000) and future fractional rates.

            $table->string('applies_to', 20)->default('both');    // 'sale' | 'purchase' | 'both'

            $table->boolean('is_recoverable')->default(false);    // VAT: true; NHIL/GETFund/COVID: false
            $table->boolean('is_withholding')->default(false);    // Supplier withholding taxes
            $table->boolean('is_compound')->default(false);       // Applied on top of another tax's base

            // Hint for Phase 1 posting engine — which GL account holds
            // output tax (for sales) or input tax (for purchases).
            $table->string('gl_account_hint', 50)->nullable();

            $table->unsignedSmallInteger('display_order')->default(100);
            $table->boolean('is_active')->default(true);

            // Effective dating — lets you close a tax rate without deleting
            // historical records that used it.
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();

            $table->timestamps();

            $table->unique(['organization_id', 'code'], 'tax_codes_org_code_unique');
            $table->index(['organization_id', 'applies_to', 'is_active'], 'tax_codes_applies_active_idx');
        });

        // CHECK constraints on rate_type and applies_to.
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'sqlite', 'pgsql'], true)) {
            try {
                \DB::statement(
                    "ALTER TABLE tax_codes ADD CONSTRAINT tax_codes_rate_type_check "
                    . "CHECK (rate_type IN ('percent', 'fixed'))"
                );
            } catch (\Throwable $e) {
                // Ignore on older versions.
            }
            try {
                \DB::statement(
                    "ALTER TABLE tax_codes ADD CONSTRAINT tax_codes_applies_to_check "
                    . "CHECK (applies_to IN ('sale', 'purchase', 'both'))"
                );
            } catch (\Throwable $e) {
                // Ignore on older versions.
            }
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'sqlite', 'pgsql'], true)) {
            foreach (['tax_codes_rate_type_check', 'tax_codes_applies_to_check'] as $constraint) {
                try {
                    \DB::statement("ALTER TABLE tax_codes DROP CONSTRAINT {$constraint}");
                } catch (\Throwable $e) {
                    // Ignore.
                }
            }
        }

        Schema::dropIfExists('tax_codes');
    }
};