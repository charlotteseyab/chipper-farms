<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_stock_intakes', function (Blueprint $table) {
            $table->string('quarantine_outcome', 30)->nullable()->after('quarantine_release_date');
            $table->unsignedInteger('quarantine_rejected_quantity')->default(0)->after('quarantine_outcome');
            $table->string('quarantine_disposition', 40)->nullable()->after('quarantine_rejected_quantity');
            $table->text('quarantine_review_notes')->nullable()->after('quarantine_disposition');
            $table->foreignId('quarantine_reviewed_by')->nullable()->after('quarantine_review_notes')->constrained('users')->nullOnDelete();
            $table->timestamp('quarantine_reviewed_at')->nullable()->after('quarantine_reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_stock_intakes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quarantine_reviewed_by');
            $table->dropColumn([
                'quarantine_outcome',
                'quarantine_rejected_quantity',
                'quarantine_disposition',
                'quarantine_review_notes',
                'quarantine_reviewed_at',
            ]);
        });
    }
};
