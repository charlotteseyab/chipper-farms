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
        Schema::create('feed_purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->string('feed_type');
            $table->decimal('quantity', 8, 2);
            $table->string('unit')->default('kg');
            $table->decimal('cost_ghc', 10, 2);
            $table->foreignId('requested_by')->constrained('users')->onDelete('cascade');
            $table->date('needed_by')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feed_purchase_requests');
    }
};
