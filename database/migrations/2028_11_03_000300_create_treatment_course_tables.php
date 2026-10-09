<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A treatment plan or preventive course (vaccination, deworming), with
        // or without a health case. Amending creates a new version linked to
        // the old one, so earlier instructions stay readable. Prescribing never
        // records a dose or touches stock.
        Schema::create('treatment_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('health_record_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('vet_examination_id')->nullable()->constrained('vet_examinations')->nullOnDelete();
            $table->string('purpose', 20);                       // treatment | vaccination | deworming | other
            $table->string('prescriber_name')->nullable();
            $table->foreignId('prescriber_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('follow_up_on')->nullable();
            $table->string('status', 20)->default('active');     // active | completed | stopped
            $table->text('status_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('amended_from_course_id')->nullable()->constrained('treatment_courses')->nullOnDelete();
            $table->text('amendment_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
        });

        // The animals a course is for, frozen when it is saved.
        Schema::create('treatment_course_animals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('treatment_course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pig_id')->constrained('pigs_management')->restrictOnDelete();
            $table->string('tag_label_snapshot')->nullable();
            $table->timestamps();
            $table->unique(['treatment_course_id', 'pig_id']);
        });

        // One row per medicine in the course, so several medicines never share a text field.
        Schema::create('treatment_course_medicines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('treatment_course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->decimal('dose_amount', 14, 4);
            $table->string('dose_unit', 20);
            $table->string('route', 50)->nullable();
            $table->string('frequency', 20);                     // once | every_n_hours | daily | every_n_days
            $table->unsignedSmallInteger('interval')->nullable(); // n for every_n_hours / every_n_days
            $table->unsignedSmallInteger('number_of_doses');
            $table->dateTime('start_at');
            $table->dateTime('end_at')->nullable();
            $table->text('instructions')->nullable();
            $table->timestamps();
        });

        // Generated from the course's own frequency; nothing is invented.
        Schema::create('scheduled_doses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('treatment_course_medicine_id')->constrained()->restrictOnDelete();
            $table->foreignId('pig_id')->constrained('pigs_management')->restrictOnDelete();
            $table->dateTime('due_at');
            $table->string('status', 20)->default('scheduled');  // scheduled | administered | missed | cancelled
            $table->text('status_reason')->nullable();
            $table->foreignId('status_changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('status_changed_at')->nullable();
            $table->unsignedBigInteger('medication_administration_id')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status', 'due_at']);
            $table->index(['pig_id', 'due_at']);
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('treatment_courses')->exists()) {
            throw new RuntimeException('Treatment courses exist; rollback would delete prescriptions and schedules.');
        }
        Schema::dropIfExists('scheduled_doses');
        Schema::dropIfExists('treatment_course_medicines');
        Schema::dropIfExists('treatment_course_animals');
        Schema::dropIfExists('treatment_courses');
    }
};
