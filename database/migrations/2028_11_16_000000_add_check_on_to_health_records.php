<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A resolved case ("recovered, keep watching") gets a date to check the
 * animal again; the check appears on the health worklist until the case is
 * closed, reopened, or a follow-up is recorded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('health_records', function (Blueprint $table) {
            $table->date('check_on')->nullable()->after('resolved_at');
            $table->index(['case_status', 'check_on']);
        });
    }

    public function down(): void
    {
        Schema::table('health_records', function (Blueprint $table) {
            $table->dropIndex(['case_status', 'check_on']);
            $table->dropColumn('check_on');
        });
    }
};
