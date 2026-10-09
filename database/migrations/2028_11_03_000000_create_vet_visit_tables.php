<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A vet visit and each animal the vet actually examined. Most visiting
        // vets have no account, so the vet is a typed name; the staff member
        // who entered the notes is recorded separately (decision D13).
        Schema::create('vet_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->dateTime('visited_at');
            $table->string('veterinarian_name');
            $table->foreignId('veterinarian_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('clinic')->nullable();
            $table->string('contact')->nullable();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->string('location')->nullable();
            $table->string('reason')->nullable();
            $table->text('general_notes')->nullable();
            $table->text('recommendations')->nullable();
            $table->foreignId('entered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('draft');          // draft | finalised
            $table->dateTime('finalised_at')->nullable();
            $table->foreignId('finalised_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'visited_at']);
        });

        // Animals the farm meant the vet to see. Never counted as examined.
        Schema::create('vet_visit_planned_animals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('vet_visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pig_id')->constrained('pigs_management')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['vet_visit_id', 'pig_id']);
        });

        Schema::create('vet_examinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('vet_visit_id')->constrained()->restrictOnDelete();
            $table->foreignId('pig_id')->constrained('pigs_management')->restrictOnDelete();
            $table->string('tag_label_at_visit')->nullable();            // tag worn that day
            $table->dateTime('examined_at');
            $table->string('assessment', 30);                             // no_issue_found | abnormal_findings | pending_assessment
            $table->text('findings')->nullable();
            $table->text('assessment_text')->nullable();
            $table->text('vet_notes')->nullable();
            $table->text('recommendations')->nullable();
            $table->text('tests_requested')->nullable();
            $table->text('test_results')->nullable();
            $table->date('follow_up_on')->nullable();
            $table->boolean('follow_up_not_needed')->default(false);
            $table->unsignedBigInteger('health_record_id')->nullable();   // FK added with the case columns
            // 0 for the first examination of a pig on a visit, 1+ for a deliberate repeat.
            $table->unsignedSmallInteger('repeat_number')->default(0);
            $table->foreignId('repeat_of_examination_id')->nullable()->constrained('vet_examinations')->nullOnDelete();
            $table->foreignId('entered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();
            $table->timestamps();
            // A pig is examined once per visit unless deliberately repeated.
            $table->unique(['vet_visit_id', 'pig_id', 'repeat_number'], 'vet_examinations_once_per_visit');
            $table->index(['organization_id', 'pig_id', 'examined_at']);
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('vet_visits')->exists()) {
            throw new RuntimeException('Vet visits exist; rollback would delete examination history.');
        }
        Schema::dropIfExists('vet_examinations');
        Schema::dropIfExists('vet_visit_planned_animals');
        Schema::dropIfExists('vet_visits');
    }
};
