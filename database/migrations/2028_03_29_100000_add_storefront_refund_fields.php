<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Storefront Phase 6 — refund audit fields (additive).
 *
 * refunded_at / refunded_by_user_id / refund_reason record the refund action.
 * finance_reversal_note records precisely which GL legs were reversed
 * automatically and which were handed to the finance team's sales-return
 * process (COGS/inventory restoration), so the boundary is always auditable.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('storefront_orders', function (Blueprint $table) {
            $table->timestamp('refunded_at')->nullable()->after('completed_by_user_id');
            $table->unsignedBigInteger('refunded_by_user_id')->nullable()->after('refunded_at');
            $table->string('refund_reason', 500)->nullable()->after('refunded_by_user_id');
            $table->text('finance_reversal_note')->nullable()->after('refund_reason');
        });
    }

    public function down(): void
    {
        Schema::table('storefront_orders', function (Blueprint $table) {
            $table->dropColumn(['refunded_at', 'refunded_by_user_id', 'refund_reason', 'finance_reversal_note']);
        });
    }
};
