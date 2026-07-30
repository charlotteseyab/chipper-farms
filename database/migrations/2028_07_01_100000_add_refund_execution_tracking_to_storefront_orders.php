<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refund execution tracking for storefront orders (CF-P0-007, CF-P0-008).
 *
 * The order table recorded refunded_at, refunded_by_user_id and refund_reason —
 * everything about the DECISION to refund, and nothing about whether any money
 * actually moved. StorefrontOrderRefundService set status = 'refunded' and
 * payment_status = 'refunded' unconditionally, without ever contacting Paystack,
 * so those three columns were the complete record of a refund that may never
 * have happened.
 *
 * Notably StorefrontOrder already declares PAY_REFUND_PENDING = 'refund_pending'
 * and STATUS_REFUND_REQUESTED / STATUS_REFUND_APPROVED. The intermediate states
 * were anticipated; nothing used them. These columns supply the provider-side
 * facts those states need to be meaningful.
 *
 * Columns:
 *   refund_provider_reference  the gateway's own id for the refund, needed to
 *                              reconcile a webhook or poll back to this order.
 *   refund_provider_status     last status reported by the provider (pending /
 *                              processing / processed / failed). Distinct from
 *                              payment_status, which is OUR view: they disagree
 *                              precisely during the window that matters.
 *   refund_submitted_at        when the gateway accepted the request. With
 *                              refunded_at (settlement) this gives the latency
 *                              between submission and money actually returning.
 *   refund_failure_reason      customer-safe failure text for staff triage.
 *   refund_idempotency_key     stable key so a retried submission is recognised
 *                              by the provider rather than refunding twice.
 *   refund_amount_minor        what we asked to be refunded, in minor units.
 *
 * Additive and nullable throughout; existing rows read as "no refund submitted",
 * which is correct for every order that predates this.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('storefront_orders')) {
            return;
        }

        Schema::table('storefront_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('storefront_orders', 'refund_provider_reference')) {
                $table->string('refund_provider_reference', 191)->nullable()->after('refund_reason');
            }

            if (! Schema::hasColumn('storefront_orders', 'refund_provider_status')) {
                $table->string('refund_provider_status', 40)->nullable()->after('refund_provider_reference');
            }

            if (! Schema::hasColumn('storefront_orders', 'refund_submitted_at')) {
                $table->timestamp('refund_submitted_at')->nullable()->after('refund_provider_status');
            }

            if (! Schema::hasColumn('storefront_orders', 'refund_failure_reason')) {
                $table->text('refund_failure_reason')->nullable()->after('refund_submitted_at');
            }

            if (! Schema::hasColumn('storefront_orders', 'refund_idempotency_key')) {
                $table->string('refund_idempotency_key', 100)->nullable()->after('refund_failure_reason');
            }

            if (! Schema::hasColumn('storefront_orders', 'refund_amount_minor')) {
                $table->unsignedBigInteger('refund_amount_minor')->nullable()->after('refund_idempotency_key');
            }
        });

        Schema::table('storefront_orders', function (Blueprint $table) {
            $indexes = collect(Schema::getIndexes('storefront_orders'))
                ->pluck('name')
                ->map(fn ($n) => strtolower((string) $n))
                ->all();

            // Reconciliation entry point: a refund webhook arrives carrying the
            // provider's reference and nothing else.
            if (! in_array('storefront_orders_refund_provider_ref_idx', $indexes, true)) {
                $table->index(['refund_provider_reference'], 'storefront_orders_refund_provider_ref_idx');
            }

            // Supports the "submitted but never settled" sweep.
            if (! in_array('storefront_orders_refund_status_idx', $indexes, true)) {
                $table->index(['refund_provider_status'], 'storefront_orders_refund_status_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('storefront_orders')) {
            return;
        }

        Schema::table('storefront_orders', function (Blueprint $table) {
            foreach (['storefront_orders_refund_provider_ref_idx', 'storefront_orders_refund_status_idx'] as $index) {
                try {
                    $table->dropIndex($index);
                } catch (\Throwable) {
                    // Not present on a partially-applied schema.
                }
            }
        });

        Schema::table('storefront_orders', function (Blueprint $table) {
            foreach ([
                'refund_provider_reference',
                'refund_provider_status',
                'refund_submitted_at',
                'refund_failure_reason',
                'refund_idempotency_key',
                'refund_amount_minor',
            ] as $column) {
                if (Schema::hasColumn('storefront_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
