<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per feed reminder sent. The unique key (type + record +
        // date or restock) stops a reminder going out twice, even when the
        // scheduler overlaps.
        Schema::create('feed_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('reminder_key', 191);
            $table->string('type', 40);
            $table->unsignedInteger('recipients')->default(0);
            $table->timestamp('sent_at');
            $table->unique(['organization_id', 'reminder_key'], 'feed_reminders_org_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_reminders');
    }
};
