<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The medicine catalogue. Withdrawal days are entered by authorised
        // staff from the product label and are never inferred or defaulted.
        // A dose may only use the medicine's dose unit; the single factor
        // converts doses to stock units (decision D5).
        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('name');
            // Empty text, not null, so the identity rule below is enforced.
            $table->string('formulation')->default('');          // e.g. injectable solution, oral powder
            $table->string('concentration')->default('');        // e.g. 200 mg/ml
            $table->string('dose_unit', 20);                     // e.g. ml, mg, dose
            $table->string('stock_unit', 20);                    // e.g. ml, vial, tablet
            $table->decimal('dose_units_per_stock_unit', 14, 4); // 1 for ml/ml, 50 for a 50-dose vial
            $table->json('allowed_routes')->nullable();
            $table->unsignedSmallInteger('meat_withdrawal_days')->nullable();
            $table->text('withdrawal_source_note')->nullable();  // where the figure came from (label, vet)
            $table->decimal('reorder_level', 14, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'name', 'formulation', 'concentration'], 'medicines_org_identity_unique');
        });

        // A received batch. Its stock lives in the inventory ledger as item
        // kind medicine_batch (Phase 3); this row holds identity and expiry.
        Schema::create('medicine_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->string('batch_number', 100);
            $table->date('expiry_date');
            $table->date('received_at');
            $table->decimal('quantity_received', 14, 4);          // in the medicine's stock unit
            $table->decimal('unit_cost', 18, 6)->default(0);      // per stock unit
            $table->string('supplier')->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained('counterparties')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['medicine_id', 'batch_number']);
            $table->index(['organization_id', 'expiry_date']);
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('medicine_batches')->exists()) {
            throw new RuntimeException('Medicine batches exist; rollback would orphan their stock movements.');
        }
        Schema::dropIfExists('medicine_batches');
        Schema::dropIfExists('medicines');
    }
};
