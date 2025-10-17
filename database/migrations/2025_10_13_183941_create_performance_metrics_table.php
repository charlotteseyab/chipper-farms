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
        Schema::create('performance_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('measure')->unique()->comment('The name of the performance measure, e.g., "Response Time"');
            $table->string('unit')->comment('The unit of measurement, e.g., "milliseconds", "percent", "count"');
        
            // Define the allowed performance levels
            $table->enum('level', ['Excellent', 'Average', 'Poor'])->default('Average')->comment('The current performance level');
        
            // Add columns for storing actual metrics/goals if needed later
            // $table->decimal('target_value', 8, 2)->nullable();
            // $table->decimal('actual_value', 8, 2)->nullable();
        
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_metrics');
    }
};
