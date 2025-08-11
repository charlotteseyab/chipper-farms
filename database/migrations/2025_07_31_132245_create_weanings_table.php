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
        Schema::create('weanings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pig_id')->constrained('pigs_management')->onDelete('cascade');
            $table->foreignId('mother_id')->nullable()->constrained('pigs_management')->onDelete('set null');
            $table->date('weaning_date');
            $table->integer('age_at_weaning_days'); // Age in days when weaned
            $table->decimal('weaning_weight', 8, 2)->nullable(); // Weight at weaning in kg
            $table->enum('weaning_method', ['natural', 'forced', 'gradual'])->default('natural');
            $table->enum('weaning_reason', ['age_appropriate', 'health_issue', 'mother_issue', 'management_decision'])->default('age_appropriate');
            $table->text('notes')->nullable();
            $table->enum('post_weaning_status', ['healthy', 'stressed', 'sick', 'monitoring'])->default('healthy');
            $table->decimal('weight_loss_percentage', 5, 2)->nullable(); // Weight loss after weaning
            $table->date('follow_up_date')->nullable(); // Date for follow-up check
            $table->foreignId('recorded_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            // Indexes for better performance
            $table->index(['pig_id', 'weaning_date']);
            $table->index(['mother_id', 'weaning_date']);
            $table->index('weaning_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weanings');
    }
};
