<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0.8 — payment_methods reference.
 *
 * Currently pig_sales.payment_method is free-text ('cash', 'bank_transfer', etc.).
 * This migration introduces a proper reference table so:
 *   - Phase 1 can map each method to a specific GL account (gl_account_hint)
 *   - Mobile money providers (MTN, AirtelTigo, Vodafone — dominant in Ghana)
 *     can be represented distinctly from generic "bank transfer"
 *   - The is_cash / is_bank flags drive cash-book vs. bank-book reconciliation
 *
 * pig_sales gets a nullable payment_method_id column (separate migration).
 * The existing string column is preserved for backward compatibility until
 * all read paths have been migrated, at which point a future batch can drop it.
 *
 * Safe and idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_methods')) {
            return;
        }

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('organization_id')->nullable();

            $table->string('code', 30);            // 'CASH', 'BANK_TRANSFER', 'MOMO_MTN', 'CHEQUE', 'POS', ...
            $table->string('name');                // Display name
            $table->text('description')->nullable();

            // Classification flags — drive accounting treatment downstream.
            $table->boolean('is_cash')->default(false);
            $table->boolean('is_bank')->default(false);
            $table->boolean('is_mobile_money')->default(false);
            $table->boolean('is_credit')->default(false);     // Deferred payment
            $table->boolean('requires_reference')->default(false);  // Bank transfer / cheque need ref no.

            // Hint for Phase 1 posting engine — which GL account does this
            // payment usually credit (for inflows) or debit (for outflows)?
            // Stored as a code string that Phase 1 maps to the COA.
            $table->string('gl_account_hint', 50)->nullable();

            $table->unsignedSmallInteger('display_order')->default(100);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['organization_id', 'code'], 'payment_methods_org_code_unique');
            $table->index(['organization_id', 'is_active', 'display_order'], 'payment_methods_listing_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};