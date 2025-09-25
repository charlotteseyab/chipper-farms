<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredient_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained('ingredients')->onDelete('cascade');
            $table->decimal('quantity', 12, 3)->default(0); // in kg by default
            $table->string('unit')->default('kg');
            $table->timestamp('last_updated')->nullable();
            $table->timestamps();
            $table->unique(['ingredient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredient_inventories');
    }
};
