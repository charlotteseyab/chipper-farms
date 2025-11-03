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
        Schema::create('piglet_deaths', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farrowing_id')->constrained('farrowing')->cascadeOnDelete();
            $table->date('death_date');
            $table->unsignedInteger('count')->default(1);
            $table->string('cause')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('piglet_deaths');
    }
};
