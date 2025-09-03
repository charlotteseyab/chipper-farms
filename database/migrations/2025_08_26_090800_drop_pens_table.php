<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('pens');
    }

    public function down(): void
    {
        Schema::create('pens', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->integer('capacity')->nullable();
            $table->enum('type', ['general', 'maternity', 'gestation', 'weaning', 'finisher', 'quarantine'])->default('general');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
};
