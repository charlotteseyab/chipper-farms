<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A wrong feeding or batch is reversed, never deleted: the original
        // row stays with who reversed it, when and why.
        foreach (['feed_issues', 'feed_batches'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->timestamp('reversed_at')->nullable();
                $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('reversal_reason')->nullable();
                $table->unsignedBigInteger('reversal_journal_batch_id')->nullable();
            });
        }

        Schema::table('feed_production_plans', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('feed_issues')->whereNotNull('reversed_at')->exists()
            || \Illuminate\Support\Facades\DB::table('feed_batches')->whereNotNull('reversed_at')->exists()) {
            throw new RuntimeException('Reversed feed records exist; rollback would hide that they were reversed.');
        }
        foreach (['feed_issues', 'feed_batches'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('reversed_by');
                $table->dropColumn(['reversed_at', 'reversal_reason', 'reversal_journal_batch_id']);
            });
        }
        Schema::table('feed_production_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['cancelled_at', 'cancellation_reason']);
        });
    }
};
