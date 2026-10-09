<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A health record becomes a health case: one animal or a frozen list
        // of animals, symptoms kept apart from a confirmed or unconfirmed
        // diagnosis, and a case status separate from any treatment course.
        // Existing values are filled by `health:backfill`, not here, except
        // organization_id, which scoping depends on from the moment this runs.
        Schema::table('health_records', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->dateTime('observed_at')->nullable()->after('record_date');
            $table->string('scope', 20)->default('individual')->after('pig_id');       // individual | group
            $table->string('group_label_snapshot')->nullable()->after('scope');          // pen, room or group name at the time
            $table->string('diagnosis_status', 20)->nullable()->after('diagnosis');      // unconfirmed | confirmed
            $table->string('case_status', 20)->nullable()->after('treatment_status');    // open | under_treatment | resolved | closed
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('closed_at')->nullable();
            $table->text('closure_reason')->nullable();
            $table->foreignId('vet_examination_id')->nullable()->constrained('vet_examinations')->nullOnDelete();
            $table->softDeletes();
            $table->index(['organization_id', 'case_status']);
        });

        DB::statement('UPDATE health_records SET organization_id = (SELECT organization_id FROM pigs_management WHERE pigs_management.id = health_records.pig_id) WHERE organization_id IS NULL');

        Schema::table('vet_examinations', function (Blueprint $table) {
            $table->foreign('health_record_id')->references('id')->on('health_records')->nullOnDelete();
        });

        // The animals a case concerns, frozen when it is recorded. Later pen or
        // group changes never touch this list.
        Schema::create('health_record_animals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('health_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pig_id')->constrained('pigs_management')->restrictOnDelete();
            $table->string('tag_label_snapshot')->nullable();
            $table->timestamps();
            $table->unique(['health_record_id', 'pig_id']);
            $table->index(['organization_id', 'pig_id']);
        });

        Schema::create('health_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('health_record_id')->constrained()->restrictOnDelete();
            $table->dateTime('observed_at');
            $table->string('response', 20)->nullable();        // improving | unchanged | worse | recovered
            $table->text('complications')->nullable();
            $table->boolean('adverse_reaction')->default(false);
            $table->text('adverse_reaction_details')->nullable();
            $table->text('findings')->nullable();
            $table->boolean('is_closing')->default(false);     // the follow-up written when the case is closed
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();
            $table->timestamps();
            $table->index(['health_record_id', 'observed_at']);
        });
    }

    public function down(): void
    {
        if (DB::table('health_follow_ups')->exists() || DB::table('health_records')->whereNotNull('case_status')->exists()) {
            throw new RuntimeException('Health cases have been backfilled or followed up; rollback would lose that history.');
        }
        Schema::dropIfExists('health_follow_ups');
        Schema::dropIfExists('health_record_animals');
        Schema::table('vet_examinations', fn (Blueprint $table) => $table->dropForeign(['health_record_id']));
        Schema::table('health_records', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'case_status']);
            $table->dropConstrainedForeignId('vet_examination_id');
            $table->dropConstrainedForeignId('responsible_user_id');
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn(['observed_at', 'scope', 'group_label_snapshot', 'diagnosis_status', 'case_status', 'closed_at', 'closure_reason', 'deleted_at']);
        });
    }
};
