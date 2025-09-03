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
        Schema::create('pig_deaths', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pig_id')->constrained('pigs_management')->onDelete('cascade');
            $table->date('death_date');
            $table->enum('death_cause', ['disease', 'accident', 'natural', 'unknown', 'other'])->default('unknown');
            $table->integer('age_at_death')->nullable(); // Calculated from birth date
            $table->decimal('final_weight', 8, 2)->nullable(); // Last recorded weight
            $table->string('death_location')->nullable(); // Where death occurred
            $table->text('veterinary_notes')->nullable(); // Vet observations
            $table->enum('disposal_method', ['burial', 'incineration', 'rendering', 'other'])->default('burial');
            $table->date('disposal_date')->nullable();
            $table->text('death_notes')->nullable(); // Additional information
            $table->foreignId('recorded_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            // Indexes for better performance
            $table->index(['pig_id', 'death_date']);
            $table->index('death_cause');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pig_deaths');
    }
};