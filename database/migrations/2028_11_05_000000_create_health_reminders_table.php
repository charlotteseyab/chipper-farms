<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per reminder sent. The unique key (type + record + due date)
        // is what stops a reminder going out twice, even if the scheduler runs
        // twice at once.
        Schema::create('health_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('reminder_key', 191);
            $table->string('type', 40);
            $table->unsignedInteger('recipients')->default(0);
            $table->timestamp('sent_at');
            $table->unique(['organization_id', 'reminder_key'], 'health_reminders_org_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_reminders');
    }
};
