<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One "Record dose" submission. Stock is taken once per event, not once
        // per animal, which is how a group dose avoids double counting. The
        // idempotency key stops a double-click or retry deducting twice.
        Schema::create('medication_dosing_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->dateTime('administered_at');
            $table->foreignId('administered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('route', 50)->nullable();
            $table->decimal('total_stock_quantity', 14, 4);
            $table->string('stock_unit', 20);
            $table->uuid('idempotency_key');
            $table->string('status', 20)->default('recorded');  // recorded | corrected | voided
            $table->foreignId('corrects_event_id')->nullable()->constrained('medication_dosing_events')->nullOnDelete();
            $table->text('correction_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'idempotency_key'], 'dosing_events_idempotency_unique');
        });

        // One per batch used, so a dose split across two batches traces to both.
        Schema::create('medication_dosing_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('medication_dosing_event_id')->constrained()->restrictOnDelete();
            $table->foreignId('medicine_batch_id')->constrained()->restrictOnDelete();
            $table->decimal('stock_quantity', 14, 4);
            $table->decimal('unit_cost_snapshot', 18, 6)->default(0);
            $table->date('expiry_snapshot');
            $table->unsignedBigInteger('inventory_movement_id')->nullable();
            $table->timestamps();
        });

        // Per-animal dose rows stay in medication_administrations so the indexed
        // withdrawal query and existing screens keep working. Each new dose
        // copies the medicine's name, units and withdrawal at the time given.
        Schema::table('medication_administrations', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->foreignId('medication_dosing_event_id')->nullable()->constrained()->restrictOnDelete();
            $table->dateTime('administered_at')->nullable()->after('administration_date');
            $table->foreignId('medicine_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('health_record_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('treatment_course_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('scheduled_dose_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('dose_amount', 14, 4)->nullable();
            $table->string('dose_unit', 20)->nullable();
            $table->decimal('stock_quantity', 14, 4)->nullable();     // this animal's share of the event
            $table->string('medicine_name_snapshot')->nullable();
            $table->unsignedSmallInteger('withdrawal_days_snapshot')->nullable();
            $table->string('withdrawal_basis', 40)->nullable();       // e.g. dose_date_plus_days (D7)
            $table->string('status', 20)->default('recorded');        // recorded | corrected | voided
            $table->boolean('is_legacy')->default(false);             // recorded before this upgrade; never moves stock
            $table->softDeletes();
            $table->index(['organization_id', 'status']);
        });

        DB::statement('UPDATE medication_administrations SET organization_id = (SELECT organization_id FROM pigs_management WHERE pigs_management.id = medication_administrations.pig_id) WHERE organization_id IS NULL');

        Schema::table('scheduled_doses', function (Blueprint $table) {
            $table->foreign('medication_administration_id')->references('id')->on('medication_administrations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('medication_dosing_events')->exists() || DB::table('medication_administrations')->where('is_legacy', true)->exists()) {
            throw new RuntimeException('Doses have been recorded or backfilled under the new model; rollback would lose that history.');
        }
        Schema::table('scheduled_doses', fn (Blueprint $table) => $table->dropForeign(['medication_administration_id']));
        Schema::table('medication_administrations', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'status']);
            foreach (['scheduled_dose_id', 'treatment_course_id', 'health_record_id', 'medicine_id', 'medication_dosing_event_id', 'organization_id'] as $fk) {
                $table->dropConstrainedForeignId($fk);
            }
            $table->dropColumn(['administered_at', 'dose_amount', 'dose_unit', 'stock_quantity', 'medicine_name_snapshot',
                'withdrawal_days_snapshot', 'withdrawal_basis', 'status', 'is_legacy', 'deleted_at']);
        });
        Schema::dropIfExists('medication_dosing_allocations');
        Schema::dropIfExists('medication_dosing_events');
    }
};
