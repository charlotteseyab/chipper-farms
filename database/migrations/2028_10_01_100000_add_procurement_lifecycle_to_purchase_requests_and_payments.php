<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->string('payment_arrangement', 30)->default('after_delivery')->after('status');
            $table->decimal('deposit_percent', 5, 2)->nullable()->after('payment_arrangement');
            $table->string('payment_progress', 30)->default('not_paid')->after('deposit_percent');
            $table->timestamp('completed_at')->nullable()->after('payment_progress');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('purchase_request_id')->nullable()->after('payment_run_id')
                ->constrained('purchase_requests')->nullOnDelete();
            $table->string('purchase_payment_stage', 20)->nullable()->after('purchase_request_id');
            $table->index(['purchase_request_id', 'status'], 'payments_purchase_request_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_purchase_request_status_idx');
            $table->dropConstrainedForeignId('purchase_request_id');
            $table->dropColumn('purchase_payment_stage');
        });

        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropColumn(['payment_arrangement', 'deposit_percent', 'payment_progress', 'completed_at']);
        });
    }
};
