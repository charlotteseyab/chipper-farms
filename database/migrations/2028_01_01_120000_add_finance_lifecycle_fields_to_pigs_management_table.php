<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pigs_management')) {
            return;
        }

        Schema::table('pigs_management', function (Blueprint $table) {
            if (! Schema::hasColumn('pigs_management', 'organization_id')) {
                $table->unsignedBigInteger('organization_id')->nullable()->after('id');
                $table->index('organization_id', 'pigs_management_org_idx');
            }

            if (! Schema::hasColumn('pigs_management', 'acquisition_cost')) {
                $table->decimal('acquisition_cost', 18, 4)->nullable()->after('group_id');
            }

            if (! Schema::hasColumn('pigs_management', 'acquisition_date')) {
                $table->date('acquisition_date')->nullable()->after('acquisition_cost');
            }

            if (! Schema::hasColumn('pigs_management', 'acquisition_source')) {
                $table->string('acquisition_source', 50)->nullable()->after('acquisition_date');
            }

            if (! Schema::hasColumn('pigs_management', 'acquisition_journal_batch_id')) {
                $table->unsignedBigInteger('acquisition_journal_batch_id')->nullable()->after('acquisition_source');
                $table->index('acquisition_journal_batch_id', 'pigs_management_acq_batch_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pigs_management')) {
            return;
        }

        Schema::table('pigs_management', function (Blueprint $table) {
            if (Schema::hasColumn('pigs_management', 'acquisition_journal_batch_id')) {
                $table->dropIndex('pigs_management_acq_batch_idx');
                $table->dropColumn('acquisition_journal_batch_id');
            }

            foreach (['acquisition_source', 'acquisition_date', 'acquisition_cost'] as $column) {
                if (Schema::hasColumn('pigs_management', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('pigs_management', 'organization_id')) {
                $table->dropIndex('pigs_management_org_idx');
                $table->dropColumn('organization_id');
            }
        });
    }
};
