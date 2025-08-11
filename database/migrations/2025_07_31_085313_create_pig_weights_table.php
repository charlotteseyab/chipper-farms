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
        Schema::create('pig_weights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pig_id')->constrained('pigs_management')->onDelete('cascade');
            $table->decimal('weight', 8, 2); // Weight in kg (up to 999,999.99 kg)
            $table->date('weigh_date');
            $table->string('weight_unit', 10)->default('kg'); // kg or lbs
            $table->text('notes')->nullable();
            $table->string('weighed_by')->nullable(); // Who recorded the weight
            $table->string('purpose')->nullable(); // breeding_check, health_check, routine, sale_prep, etc.
            $table->boolean('is_estimated')->default(false); // If weight was estimated vs actual
            $table->decimal('body_condition_score', 3, 1)->nullable(); // 1.0 to 5.0 scale
            $table->timestamps();

            // Indexes for better performance
            $table->index(['pig_id', 'weigh_date']);
            $table->index(['weigh_date']);
            $table->index(['purpose']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pig_weights');
    }
};
