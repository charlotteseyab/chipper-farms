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
        Schema::create('weight_alerts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pig_id');
            $table->unsignedBigInteger('weight_record_id');
            $table->enum('alert_type', ['under_weight', 'over_weight', 'normal', 'no_standard']);
            $table->enum('severity', ['low', 'medium', 'high'])->default('low');
            $table->decimal('actual_weight', 8, 2); // The recorded weight
            $table->decimal('expected_min_weight', 8, 2)->nullable(); // Expected minimum
            $table->decimal('expected_max_weight', 8, 2)->nullable(); // Expected maximum
            $table->decimal('optimal_weight', 8, 2)->nullable(); // Expected optimal
            $table->decimal('deviation_percentage', 8, 2)->nullable(); // How far off from expected
            $table->integer('pig_age_weeks'); // Pig's age in weeks when weighed
            $table->text('alert_message'); // Human-readable alert description
            $table->boolean('acknowledged')->default(false); // Whether alert has been reviewed
            $table->unsignedBigInteger('acknowledged_by')->nullable(); // User who acknowledged
            $table->timestamp('acknowledged_at')->nullable(); // When acknowledged
            $table->text('acknowledgment_notes')->nullable(); // Notes when acknowledging
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('pig_id')->references('id')->on('pigs_management')->onDelete('cascade');
            $table->foreign('weight_record_id')->references('id')->on('pig_weights')->onDelete('cascade');
            $table->foreign('acknowledged_by')->references('id')->on('users')->onDelete('set null');

            // Indexes for better performance
            $table->index(['pig_id', 'created_at']);
            $table->index(['alert_type', 'severity']);
            $table->index('acknowledged');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weight_alerts');
    }
};
