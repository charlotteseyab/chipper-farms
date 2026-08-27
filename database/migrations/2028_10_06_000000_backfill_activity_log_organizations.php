<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('activity_logs')
            ->whereNull('organization_id')
            ->whereNotNull('user_id')
            ->orderBy('id')
            ->chunkById(500, function ($logs): void {
                $organizations = DB::table('users')
                    ->whereIn('id', $logs->pluck('user_id')->filter()->unique())
                    ->pluck('organization_id', 'id');

                foreach ($logs as $log) {
                    $organizationId = $organizations[$log->user_id] ?? null;
                    if ($organizationId !== null) {
                        DB::table('activity_logs')->where('id', $log->id)->update([
                            'organization_id' => $organizationId,
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Historical organization attribution is valid data and is retained.
    }
};
