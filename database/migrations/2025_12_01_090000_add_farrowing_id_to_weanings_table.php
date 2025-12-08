<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weanings', function (Blueprint $table) {
            $table->foreignId('farrowing_id')
                ->nullable()
                ->after('id')
                ->constrained('farrowing')
                ->onDelete('set null');
        });
    }
    public function down(): void
    {
        Schema::table('weanings', function (Blueprint $table) {
            $table->dropForeign(['farrowing_id']);
            $table->dropColumn('farrowing_id');
        });
    }
};
