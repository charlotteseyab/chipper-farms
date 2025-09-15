<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('feeding_standards', function (Blueprint $table) {
            $table->id();
            $table->string('pig_category');
            $table->foreignId('feed_type_id')->constrained('feed_types')->onDelete('cascade');
            $table->decimal('daily_amount', 8, 2);
            $table->string('unit')->default('kg');
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('feeding_standards');
    }
};
