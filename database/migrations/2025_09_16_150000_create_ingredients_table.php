<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->decimal('protein_percentage', 5, 2)->nullable(); // %
            $table->decimal('energy_kcal_per_kg', 8, 2)->nullable();
            $table->decimal('fat_percentage', 5, 2)->nullable();
            $table->decimal('fiber_percentage', 5, 2)->nullable();
            $table->string('unit')->default('kg');
            $table->decimal('cost_per_unit', 10, 2)->default(0); // GHS per unit
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredients');
    }
};
