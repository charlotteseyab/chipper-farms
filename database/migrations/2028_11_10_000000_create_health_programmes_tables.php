<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Herd health programmes: routine vaccinations, deworming and other must-do
 * doses, set up once and scheduled for each animal as it qualifies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_programmes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('purpose', 20);                        // vaccination | deworming | other
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->decimal('dose_amount', 14, 4);
            $table->string('dose_unit', 20);
            $table->string('route', 50)->nullable();
            $table->text('instructions')->nullable();
            $table->string('trigger', 30);                        // age | interval | before_mating | before_farrowing | after_farrowing
            $table->unsignedSmallInteger('days');                 // the age, interval, or days before/after
            $table->string('applies_to', 30);                     // all | growers | breeding | breeding_females | boars
            $table->time('due_time')->default('08:00:00');
            $table->date('starts_on');
            $table->boolean('is_active')->default(true);
            $table->text('paused_reason')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'is_active']);
        });

        // One row per animal per occurrence, so a dose is scheduled once and a
        // dose staff skipped is never put back.
        Schema::create('health_programme_doses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('health_programme_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pig_id')->constrained('pigs_management')->restrictOnDelete();
            $table->string('occurrence_key', 60);                 // age | interval:2026-06-01 | mating:12 | farrowing:7
            $table->date('due_on');
            $table->foreignId('scheduled_dose_id')->nullable()->constrained('scheduled_doses')->nullOnDelete();
            $table->timestamps();
            $table->unique(['health_programme_id', 'pig_id', 'occurrence_key'], 'health_programme_doses_once');
        });

        Schema::table('treatment_courses', function (Blueprint $table) {
            $table->foreignId('health_programme_id')->nullable()->after('vet_examination_id')->constrained('health_programmes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('treatment_courses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('health_programme_id');
        });
        Schema::dropIfExists('health_programme_doses');
        Schema::dropIfExists('health_programmes');
    }
};
