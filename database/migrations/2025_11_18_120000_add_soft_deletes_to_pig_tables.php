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
        Schema::table('pigs_management', function (Blueprint $table) {
            if (!Schema::hasColumn('pigs_management', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('pig_sales', function (Blueprint $table) {
            if (!Schema::hasColumn('pig_sales', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('pig_deaths', function (Blueprint $table) {
            if (!Schema::hasColumn('pig_deaths', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pigs_management', function (Blueprint $table) {
            if (Schema::hasColumn('pigs_management', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('pig_sales', function (Blueprint $table) {
            if (Schema::hasColumn('pig_sales', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('pig_deaths', function (Blueprint $table) {
            if (Schema::hasColumn('pig_deaths', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};

