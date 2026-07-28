<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_stock_intakes', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number', 30)->unique();
            $table->string('supplier_name');
            $table->date('purchase_date');
            $table->string('document_reference')->nullable();
            $table->string('animal_category', 40);
            $table->string('breed', 50)->nullable();
            $table->unsignedInteger('quantity_ordered');
            $table->unsignedInteger('quantity_received')->nullable();
            $table->unsignedInteger('quantity_rejected')->default(0);
            $table->unsignedInteger('male_quantity')->nullable();
            $table->unsignedInteger('female_quantity')->nullable();
            $table->unsignedInteger('estimated_age_days')->nullable();
            $table->date('estimated_date_of_birth')->nullable();
            $table->decimal('average_weight_kg', 10, 2)->nullable();
            $table->decimal('total_purchase_cost', 18, 4)->nullable();
            $table->decimal('transport_cost', 18, 4)->default(0);
            $table->string('status', 40)->default('pending_receive');
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('health_status', 40)->nullable();
            $table->text('health_notes')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('quarantined_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('quarantined_at')->nullable();
            $table->date('quarantine_release_date')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('added_to_stock_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('added_to_stock_at')->nullable();
            $table->unsignedBigInteger('acquisition_journal_batch_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'animal_category']);
            $table->index('purchase_date');
        });

        Schema::table('pigs_management', function (Blueprint $table) {
            if (! Schema::hasColumn('pigs_management', 'purchase_stock_intake_id')) {
                $table->foreignId('purchase_stock_intake_id')
                    ->nullable()
                    ->after('acquisition_journal_batch_id')
                    ->constrained('purchase_stock_intakes')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('pigs_management', function (Blueprint $table) {
            if (Schema::hasColumn('pigs_management', 'purchase_stock_intake_id')) {
                $table->dropConstrainedForeignId('purchase_stock_intake_id');
            }
        });

        Schema::dropIfExists('purchase_stock_intakes');
    }
};
