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
        Schema::create('litters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sow_id'); // Reference to sow
            $table->date('farrowing_date');
            $table->unsignedSmallInteger('live_births')->default(0);
            $table->unsignedSmallInteger('stillbirths')->default(0);
            $table->date('date_weaned')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('sow_id')->references('id')->on('pigs_management')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('litters');
    }
};