<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Brief details typed in by staff, so everyone can see what a medicine
        // is for and how to keep it. Nothing is filled in automatically.
        Schema::table('medicines', function (Blueprint $table) {
            $table->text('description')->nullable()->after('concentration');      // what it is / what it does
            $table->text('used_for')->nullable()->after('description');          // conditions or purposes
            $table->text('storage_instructions')->nullable()->after('used_for');
            $table->text('precautions')->nullable()->after('storage_instructions'); // precautions, side effects
        });
    }

    public function down(): void
    {
        Schema::table('medicines', fn (Blueprint $table) => $table->dropColumn(['description', 'used_for', 'storage_instructions', 'precautions']));
    }
};
