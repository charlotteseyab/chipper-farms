<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feed_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feed_type_id')->constrained('feed_types')->cascadeOnDelete();
            $table->date('batch_date');
            $table->decimal('batch_size_kg', 12, 2);
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('mixed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_batches');
    }
};