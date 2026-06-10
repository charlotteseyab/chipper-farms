<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pig_sales')) {
            return;
        }

        Schema::table('pig_sales', function (Blueprint $table) {
            if (! Schema::hasColumn('pig_sales', 'payment_status_updated_at')) {
                $table->timestamp('payment_status_updated_at')->nullable()->after('payment_status');
            }

            if (! Schema::hasColumn('pig_sales', 'payment_status_updated_by_user_id')) {
                $table->unsignedBigInteger('payment_status_updated_by_user_id')->nullable()->after('payment_status_updated_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pig_sales')) {
            return;
        }

        Schema::table('pig_sales', function (Blueprint $table) {
            if (Schema::hasColumn('pig_sales', 'payment_status_updated_by_user_id')) {
                $table->dropColumn('payment_status_updated_by_user_id');
            }

            if (Schema::hasColumn('pig_sales', 'payment_status_updated_at')) {
                $table->dropColumn('payment_status_updated_at');
            }
        });
    }
};
