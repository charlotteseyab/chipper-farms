<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feed_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feed_type_id')->constrained('feed_types')->onDelete('cascade');
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('feed_recipe_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feed_recipe_id')->constrained('feed_recipes')->onDelete('cascade');
            $table->foreignId('ingredient_id')->constrained('ingredients')->onDelete('restrict');
            $table->decimal('percentage', 6, 3); // percentage of total mix
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_recipe_items');
        Schema::dropIfExists('feed_recipes');
    }
};
