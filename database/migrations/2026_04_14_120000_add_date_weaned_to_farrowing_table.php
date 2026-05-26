
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('farrowing', 'date_weaned')) {
            Schema::table('farrowing', function (Blueprint $table) {
                $table->date('date_weaned')->nullable()->after('farrowing_date');
                $table->index('date_weaned');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('farrowing', 'date_weaned')) {
            Schema::table('farrowing', function (Blueprint $table) {
                $table->dropIndex(['date_weaned']);
                $table->dropColumn('date_weaned');
            });
        }
    }
};