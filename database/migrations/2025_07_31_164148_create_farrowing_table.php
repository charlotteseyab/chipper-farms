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
        Schema::create('farrowing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sow_id')->constrained('pigs_management')->onDelete('cascade');
            $table->foreignId('boar_id')->nullable()->constrained('pigs_management')->onDelete('set null');
            $table->date('farrowing_date');
            $table->time('farrowing_time')->nullable();
            $table->integer('total_piglets_born')->default(0);
            $table->integer('live_births')->default(0);
            $table->integer('stillbirths')->default(0);
            $table->integer('number_of_males')->default(0);
            $table->integer('number_of_females')->default(0);
            $table->decimal('average_birth_weight', 5, 2)->nullable();
            $table->decimal('total_litter_weight', 6, 2)->nullable();
            $table->enum('farrowing_type', ['natural', 'assisted', 'cesarean'])->default('natural');
            $table->enum('farrowing_difficulty', ['easy', 'moderate', 'difficult', 'complicated'])->default('easy');
            $table->integer('gestation_length_days')->nullable();
            $table->text('complications')->nullable();
            $table->text('notes')->nullable();
            $table->enum('sow_condition', ['excellent', 'good', 'fair', 'poor'])->default('good');
            $table->boolean('colostrum_available')->default(true);
            $table->integer('functional_teats')->nullable();
            $table->decimal('sow_weight_after_farrowing', 6, 2)->nullable();
            $table->foreignId('recorded_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('follow_up_date')->nullable();
            $table->enum('status', ['active', 'completed', 'archived'])->default('active');
            $table->timestamps();

            // Indexes for better performance
            $table->index(['sow_id', 'farrowing_date']);
            $table->index('farrowing_date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('farrowing');
    }
};
