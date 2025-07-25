<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('breeding_stocks', function (Blueprint $table) {
            $table->id();
            $table->string('animal_id');
            $table->string('ear_tag_number');
            $table->string('breed');
            $table->enum('gender', ['male', 'female']);
            $table->date('date_of_birth');
            $table->string('age');
            $table->string('status');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('breeding_stocks');
    }
};
