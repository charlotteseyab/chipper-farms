<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feed_purchase_requests', function (Blueprint $table) {
            $table->text('decision_reason')->nullable()->after('notes');
            $table->foreignId('decided_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('decided_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('feed_purchase_requests', function (Blueprint $table) {
            $table->dropForeign(['decided_by']);
            $table->dropColumn(['decision_reason', 'decided_by', 'decided_at']);
        });
    }
};
