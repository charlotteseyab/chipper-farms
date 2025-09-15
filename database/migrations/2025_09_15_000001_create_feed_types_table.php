<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('feed_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->decimal('protein_percent', 5, 2)->nullable();
            $table->decimal('energy_mj_per_kg', 6, 2)->nullable();
            $table->decimal('fiber_percent', 5, 2)->nullable();
            $table->decimal('cost_per_unit', 10, 2)->nullable();
            $table->string('unit')->default('kg');
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('feed_types');
    }
};
