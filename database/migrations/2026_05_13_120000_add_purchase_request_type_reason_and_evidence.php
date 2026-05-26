<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_requests')) {
            return;
        }

        Schema::table('purchase_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_requests', 'purchase_type')) {
                $table->string('purchase_type', 30)->default('other')->after('request_no');
                $table->index('purchase_type', 'purchase_requests_purchase_type_idx');
            }

            if (! Schema::hasColumn('purchase_requests', 'reason')) {
                $table->text('reason')->nullable()->after('purpose');
            }

            if (! Schema::hasColumn('purchase_requests', 'evidence_image_path')) {
                $table->string('evidence_image_path')->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('purchase_requests')) {
            return;
        }

        Schema::table('purchase_requests', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_requests', 'purchase_type')) {
                try {
                    $table->dropIndex('purchase_requests_purchase_type_idx');
                } catch (Throwable $e) {
                    // Ignore missing index during rollback on databases that skipped it.
                }

                $table->dropColumn('purchase_type');
            }

            if (Schema::hasColumn('purchase_requests', 'reason')) {
                $table->dropColumn('reason');
            }

            if (Schema::hasColumn('purchase_requests', 'evidence_image_path')) {
                $table->dropColumn('evidence_image_path');
            }
        });
    }
};
