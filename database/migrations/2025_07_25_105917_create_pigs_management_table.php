<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void 
    {
        Schema::create('pigs_management', function (Blueprint $table) {
            $table->id();
            $table->string('ear_tag_number')->unique();
            $table->string('breed'); 
            $table->string('gender'); 
            $table->date('date_of_birth');
            $table->string('code')->unique();
            // Stored snapshot used by legacy paths; dynamic current age is computed from date_of_birth.
            // Default avoids NOT NULL constraint failures in tests / seeders.
            $table->integer('age_in_days')->default(0);

            // Foreign keys with nullOnDelete for safe parent deletion
            $table->foreignId('father_id')
                  ->nullable()
                  ->constrained('pigs_management', 'id')
                  ->nullOnDelete();

            $table->foreignId('mother_id')
                  ->nullable()
                  ->constrained('pigs_management', 'id')
                  ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void 
    {
        Schema::dropIfExists('pigs_management'); 
    }
};
