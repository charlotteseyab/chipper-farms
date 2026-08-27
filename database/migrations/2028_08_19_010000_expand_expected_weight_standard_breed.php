<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expected_weight_standards', function (Blueprint $table) {
            $table->string('breed', 20)->change();
        });
    }

    public function down(): void
    {
        // Do not restore the legacy enum: farms may already have custom breeds.
    }
};
