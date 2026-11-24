<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NotifySuccessfulAction checks "has this user already been audited since the
 * request started?" on every successful write request:
 *
 *   WHERE user_id = ? AND created_at >= ?
 *
 * The only index led by user_id was (user_id, action, name), so MySQL read
 * every one of the user's audit rows to filter on created_at, and each save got
 * a little slower as the user's history grew. This index answers it directly.
 */
return new class extends Migration
{
    private const INDEX = 'activity_logs_user_created_index';

    public function up(): void
    {
        if (! Schema::hasTable('activity_logs') || $this->hasIndex()) {
            return;
        }

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], self::INDEX);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('activity_logs') || ! $this->hasIndex()) {
            return;
        }

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(self::INDEX);
        });
    }

    private function hasIndex(): bool
    {
        return collect(Schema::getIndexes('activity_logs'))
            ->pluck('name')
            ->map(fn ($name) => strtolower((string) $name))
            ->contains(self::INDEX);
    }
};
