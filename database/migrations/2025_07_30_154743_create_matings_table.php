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
        Schema::create('matings', function (Blueprint $table) {
            $table->id();

            // Breeding pair information
            $table->unsignedBigInteger('male_id');
            $table->unsignedBigInteger('female_id');

            // Mating schedule and status
            $table->date('planned_date');
            $table->date('actual_date')->nullable();
            $table->enum('status', ['planned', 'completed', 'cancelled', 'failed'])->default('planned');

            // Additional information
            $table->text('notes')->nullable();
            $table->text('outcome_notes')->nullable(); // For recording results after mating

            // Breeding outcome tracking
            $table->boolean('successful')->nullable(); // null = unknown, true = successful, false = failed
            $table->date('expected_farrowing_date')->nullable(); // Calculated or manually set
            $table->integer('expected_piglets')->nullable(); // Expected litter size

            // User tracking
            $table->unsignedBigInteger('created_by')->nullable(); // User who planned the mating
            $table->unsignedBigInteger('completed_by')->nullable(); // User who marked it as completed

            // Timestamps
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('male_id')->references('id')->on('pigs_management')->onDelete('cascade');
            $table->foreign('female_id')->references('id')->on('pigs_management')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('completed_by')->references('id')->on('users')->onDelete('set null');

            // Indexes for better performance
            $table->index(['status', 'planned_date']);
            $table->index(['male_id', 'female_id']);
            $table->index('planned_date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matings');
    }
};
