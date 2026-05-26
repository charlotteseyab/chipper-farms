<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('health_records', function (Blueprint $table) {
            if (!Schema::hasColumn('health_records', 'treatment_status')) {
                $table->string('treatment_status', 20)->nullable()->after('treatment_plan');
            }
            if (!Schema::hasColumn('health_records', 'outcome')) {
                $table->string('outcome', 20)->nullable()->after('treatment_status');
            }
            if (!Schema::hasColumn('health_records', 'resolved_at')) {
                $table->date('resolved_at')->nullable()->after('outcome');
            }
            if (!Schema::hasColumn('health_records', 'resolution_notes')) {
                $table->text('resolution_notes')->nullable()->after('resolved_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('health_records', function (Blueprint $table) {
            $drops = [];
            foreach (['treatment_status', 'outcome', 'resolved_at', 'resolution_notes'] as $column) {
                if (Schema::hasColumn('health_records', $column)) {
                    $drops[] = $column;
                }
            }
            if ($drops !== []) {
                $table->dropColumn($drops);
            }
        });
    }
};

