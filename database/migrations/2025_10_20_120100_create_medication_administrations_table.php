<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medication_administrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pig_id');
            $table->date('administration_date');
            $table->string('medication_name');
            $table->string('dosage');
            $table->string('route')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('administered_by')->nullable();
            $table->timestamps();

            $table->foreign('pig_id')->references('id')->on('pigs_management')->onDelete('cascade');
            $table->foreign('administered_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['pig_id', 'administration_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medication_administrations');
    }
};


