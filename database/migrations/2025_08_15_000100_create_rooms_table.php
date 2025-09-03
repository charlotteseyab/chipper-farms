<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained('structures')->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->unsignedInteger('capacity')->nullable();
            $table->enum('type', ['maternity', 'gestation', 'weaning', 'finisher', 'quarantine', 'general'])->default('general');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['structure_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};


