<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('group_feedings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->onDelete('cascade');
            $table->foreignId('feed_type_id')->constrained('feed_types')->onDelete('cascade');
            $table->decimal('amount', 8, 2);
            $table->string('unit')->default('kg');
            $table->date('date');
            $table->decimal('wastage', 8, 2)->nullable();
            $table->foreignId('recorded_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('group_feedings');
    }
};
