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
        Schema::create('expected_weight_standards', function (Blueprint $table) {
            $table->id();
            $table->enum('breed', ['LW', 'DR', 'LWDR']);
            $table->enum('gender', ['M', 'F']);
            $table->enum('stock_type', ['standard', 'breeding']);
            $table->integer('week_number'); // Age in weeks
            $table->decimal('expected_min_weight', 8, 2); // Minimum healthy weight in kg
            $table->decimal('expected_max_weight', 8, 2); // Maximum healthy weight in kg
            $table->decimal('optimal_weight', 8, 2); // Target optimal weight in kg
            $table->text('notes')->nullable(); // Additional notes
            $table->unsignedBigInteger('created_by'); // User who created this standard
            $table->boolean('is_active')->default(true); // Whether this standard is currently active
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');

            // Unique constraint to prevent duplicate standards
            $table->unique(['breed', 'gender', 'stock_type', 'week_number'], 'unique_weight_standard');

            // Indexes for better performance
            $table->index(['breed', 'gender', 'stock_type']);
            $table->index('week_number');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expected_weight_standards');
    }
};
