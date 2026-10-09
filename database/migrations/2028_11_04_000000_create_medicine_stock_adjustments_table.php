<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per adjustment or count of a medicine batch. It is the
        // ledger movement's source, so each adjustment moves stock exactly once.
        Schema::create('medicine_stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('medicine_batch_id')->constrained()->restrictOnDelete();
            $table->string('type', 10);                       // add | deduct | count
            $table->decimal('quantity', 14, 4);               // magnitude, or the counted on-hand for a count
            $table->decimal('quantity_before', 14, 4);
            $table->decimal('quantity_after', 14, 4);
            $table->text('reason');
            $table->dateTime('adjusted_at');
            $table->foreignId('adjusted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('inventory_movement_id')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'medicine_batch_id'], 'medicine_adjustments_org_batch_idx');
        });
    }

    public function down(): void
    {
        if (DB::table('medicine_stock_adjustments')->exists()) {
            throw new RuntimeException('Medicine stock adjustments exist; rollback would orphan their stock movements.');
        }
        Schema::dropIfExists('medicine_stock_adjustments');
    }
};
