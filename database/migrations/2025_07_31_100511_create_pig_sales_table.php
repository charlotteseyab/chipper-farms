<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pig_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pig_id')->constrained('pigs_management')->onDelete('cascade');
            $table->date('sale_date');
            $table->decimal('sale_price', 10, 2); // Price in local currency
            $table->decimal('sale_weight', 8, 2)->nullable(); // Weight at sale in kg
            $table->string('buyer_name');
            $table->string('buyer_contact')->nullable();
            $table->text('buyer_address')->nullable();
            $table->string('sale_purpose')->default('market'); // market, breeding, slaughter, etc.
            $table->string('payment_method')->nullable(); // cash, check, bank_transfer, etc.
            $table->string('payment_status')->default('pending'); // pending, paid, partial
            $table->text('notes')->nullable();
            $table->string('sold_by')->nullable(); // Who handled the sale
            $table->decimal('transport_cost', 8, 2)->nullable();
            $table->string('transport_method')->nullable(); // pickup, delivery, etc.
            $table->json('sale_documents')->nullable(); // Store document paths/references
            $table->timestamps();

            // Indexes for better performance
            $table->index(['pig_id']);
            $table->index(['sale_date']);
            $table->index(['buyer_name']);
            $table->index(['sale_purpose']);
            $table->index(['payment_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pig_sales');
    }
};
