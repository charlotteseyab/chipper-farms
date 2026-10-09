<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A physical count of raw materials and finished feed. Each line
        // keeps what the system expected, what was counted, and the ledger
        // adjustment that brought the two together.
        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->timestamp('counted_at');
            $table->string('reference', 200)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('counted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('submission_key');
            $table->timestamps();
            $table->unique(['organization_id', 'submission_key'], 'stock_counts_submission_unique');
            $table->index(['organization_id', 'counted_at']);
        });

        Schema::create('stock_count_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained()->restrictOnDelete();
            $table->string('item_kind', 30);
            $table->unsignedBigInteger('item_id');
            $table->string('item_name');
            $table->string('unit', 50);
            $table->decimal('system_qty', 14, 4);
            $table->decimal('counted_qty', 14, 4);
            $table->decimal('difference_qty', 14, 4);
            $table->decimal('value_delta', 18, 4)->default(0);
            $table->foreignId('raw_material_stock_log_id')->nullable()->constrained('raw_material_stock_logs')->restrictOnDelete();
            $table->unsignedBigInteger('inventory_movement_id')->nullable();
            $table->string('posting_status', 20)->default('not_required');
            $table->unsignedBigInteger('journal_batch_id')->nullable();
            $table->timestamps();
            $table->unique(['stock_count_id', 'item_kind', 'item_id'], 'stock_count_lines_item_unique');
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('stock_counts')->exists()) {
            throw new RuntimeException('Stock counts exist; rollback would lose count history and its adjustments.');
        }
        Schema::dropIfExists('stock_count_lines');
        Schema::dropIfExists('stock_counts');
    }
};
