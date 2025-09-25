<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feed_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->onDelete('cascade');
            $table->foreignId('feed_type_id')->constrained('feed_types')->onDelete('cascade');
            $table->decimal('planned_amount', 12, 3);
            $table->string('unit', 10)->default('kg');
            $table->date('scheduled_for');
            $table->enum('status', ['planned','fed','skipped'])->default('planned');
            $table->timestamp('fed_at')->nullable();
            $table->foreignId('fed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['scheduled_for','status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_schedules');
    }
};


