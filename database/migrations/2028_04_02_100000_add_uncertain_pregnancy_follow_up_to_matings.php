<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matings', function (Blueprint $table) {
            if (! Schema::hasColumn('matings', 'pregnancy_follow_up_date')) {
                $table->date('pregnancy_follow_up_date')->nullable()->after('pregnancy_status_notes');
            }

            if (! Schema::hasColumn('matings', 'pregnancy_follow_up_status')) {
                $table->string('pregnancy_follow_up_status', 40)->nullable()->after('pregnancy_follow_up_date');
            }

            if (! Schema::hasColumn('matings', 'pregnancy_follow_up_notes')) {
                $table->text('pregnancy_follow_up_notes')->nullable()->after('pregnancy_follow_up_status');
            }

            if (! Schema::hasColumn('matings', 'last_uncertain_check_at')) {
                $table->timestamp('last_uncertain_check_at')->nullable()->after('pregnancy_follow_up_notes');
            }

            if (! Schema::hasColumn('matings', 'pregnancy_follow_up_completed_at')) {
                $table->timestamp('pregnancy_follow_up_completed_at')->nullable()->after('last_uncertain_check_at');
            }

            if (! Schema::hasColumn('matings', 'pregnancy_follow_up_completed_by')) {
                $table->foreignId('pregnancy_follow_up_completed_by')
                    ->nullable()
                    ->after('pregnancy_follow_up_completed_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            $table->index(['pregnancy_status', 'pregnancy_follow_up_date'], 'matings_preg_followup_idx');
        });
    }

    public function down(): void
    {
        Schema::table('matings', function (Blueprint $table) {
            $table->dropIndex('matings_preg_followup_idx');

            if (Schema::hasColumn('matings', 'pregnancy_follow_up_completed_by')) {
                $table->dropConstrainedForeignId('pregnancy_follow_up_completed_by');
            }

            $table->dropColumn([
                'pregnancy_follow_up_date',
                'pregnancy_follow_up_status',
                'pregnancy_follow_up_notes',
                'last_uncertain_check_at',
                'pregnancy_follow_up_completed_at',
            ]);
        });
    }
};
