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
        Schema::table('weanings', function (Blueprint $table) {
            $table->boolean('maturity_completed')->default(false);
            $table->date('maturity_completion_date')->nullable();
            $table->integer('age_at_maturity_days')->nullable();
            $table->decimal('final_weight', 6, 2)->nullable();
            $table->enum('health_status_at_maturity', ['excellent', 'good', 'fair', 'poor'])->nullable();
            $table->integer('body_condition_score')->nullable();
            $table->enum('next_destination', ['breeding', 'market', 'monitoring', 'other'])->nullable();
            $table->enum('growth_performance', ['excellent', 'good', 'average', 'poor'])->nullable();
            $table->text('maturity_notes')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->onDelete('set null');

            // Add index for performance
            $table->index('maturity_completed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('weanings', function (Blueprint $table) {
            $table->dropForeign(['completed_by']);
            $table->dropIndex(['maturity_completed']);
            $table->dropColumn([
                'maturity_completed',
                'maturity_completion_date',
                'age_at_maturity_days',
                'final_weight',
                'health_status_at_maturity',
                'body_condition_score',
                'next_destination',
                'growth_performance',
                'maturity_notes',
                'completed_by'
            ]);
        });
    }
};
