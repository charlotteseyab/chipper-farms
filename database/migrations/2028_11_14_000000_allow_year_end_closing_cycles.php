<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('year_end_closings', function (Blueprint $table) {
            $table->index(['organization_id', 'fiscal_year_id'], 'yec_org_year_index');
        });
        Schema::table('year_end_closings', function (Blueprint $table) {
            $table->dropUnique('yec_org_year_unique');
        });
    }

    public function down(): void
    {
        if (DB::table('year_end_closings')->select('organization_id', 'fiscal_year_id')
            ->groupBy('organization_id', 'fiscal_year_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Multiple year-end closing cycles exist; rollback would discard accounting history.');
        }
        Schema::table('year_end_closings', function (Blueprint $table) {
            $table->unique(['organization_id', 'fiscal_year_id'], 'yec_org_year_unique');
        });
        Schema::table('year_end_closings', function (Blueprint $table) {
            $table->dropIndex('yec_org_year_index');
        });
    }
};
