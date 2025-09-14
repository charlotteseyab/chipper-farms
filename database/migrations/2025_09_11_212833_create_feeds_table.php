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
        Schema::create('feeds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pig_id')->constrained('pigs_management')->onDelete('cascade');
            $table->enum('feed_type', ['starter', 'grower', 'finisher', 'breeding', 'lactating', 'maintenance', 'supplement', 'medicated']);
            $table->decimal('feed_amount', 8, 2);
            $table->enum('feed_unit', ['kg', 'lbs', 'grams', 'pounds']);
            $table->date('feed_date');
            $table->time('feed_time')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('feed_cost', 10, 2)->nullable();
            $table->string('supplier')->nullable();
            $table->string('batch_number')->nullable();
            $table->date('expiry_date')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            // Indexes for better performance
            $table->index(['pig_id', 'feed_date']);
            $table->index('feed_date');
            $table->index('feed_type');
            $table->index('recorded_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feeds');
    }
};