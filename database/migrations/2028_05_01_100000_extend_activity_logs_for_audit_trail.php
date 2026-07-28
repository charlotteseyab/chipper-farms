<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('activity_logs', 'organization_id')) {
                $table->foreignId('organization_id')->nullable()->after('user_id')->constrained('organizations')->nullOnDelete();
            }
            if (! Schema::hasColumn('activity_logs', 'module')) {
                $table->string('module')->nullable()->after('action')->index();
            }
            if (! Schema::hasColumn('activity_logs', 'auditable_type')) {
                $table->string('auditable_type')->nullable()->after('module');
            }
            if (! Schema::hasColumn('activity_logs', 'auditable_id')) {
                $table->unsignedBigInteger('auditable_id')->nullable()->after('auditable_type');
            }
            if (! Schema::hasColumn('activity_logs', 'record_label')) {
                $table->string('record_label')->nullable()->after('auditable_id');
            }
            if (! Schema::hasColumn('activity_logs', 'description')) {
                $table->text('description')->nullable()->after('record_label');
            }
            if (! Schema::hasColumn('activity_logs', 'old_values')) {
                $table->json('old_values')->nullable()->after('description');
            }
            if (! Schema::hasColumn('activity_logs', 'new_values')) {
                $table->json('new_values')->nullable()->after('old_values');
            }

            $table->index(['auditable_type', 'auditable_id'], 'activity_logs_auditable_index');
            $table->index(['module', 'action', 'created_at'], 'activity_logs_module_action_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex('activity_logs_auditable_index');
            $table->dropIndex('activity_logs_module_action_created_index');

            foreach ([
                'new_values',
                'old_values',
                'description',
                'record_label',
                'auditable_id',
                'auditable_type',
                'module',
                'organization_id',
            ] as $column) {
                if (Schema::hasColumn('activity_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
