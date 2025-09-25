<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grouping_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('conditions'); // e.g., {"pen_id":1, "stage":"weaner", "weight_between":[20,40]}
            $table->foreignId('feeding_standard_id')->nullable()->constrained('feeding_standards')->onDelete('set null');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grouping_rules');
    }
};
