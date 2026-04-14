<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feed_type_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feed_type_id')->constrained('feed_types')->cascadeOnDelete();
            $table->foreignId('raw_material_id')->constrained('raw_materials')->cascadeOnDelete();
            $table->decimal('quantity_per_100kg', 12, 2);
            $table->timestamps();

            $table->unique(['feed_type_id', 'raw_material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_type_recipes');
    }
};