<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $missing = \Illuminate\Support\Facades\DB::table('pigs_management as p')
            ->whereNotNull('ear_tag_number')->where('ear_tag_number', '!=', '')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('ear_tag_assignments as a')->whereColumn('a.pig_id', 'p.id'))->exists();
        if ($missing) {
            throw new RuntimeException('Tag activation blocked: run ear-tags:reconcile --backfill and review its conflicts before retrying migration.');
        }
        if (\Illuminate\Support\Facades\Artisan::call('ear-tags:reconcile') !== 0) {
            throw new RuntimeException('Tag activation blocked by reconciliation: '.\Illuminate\Support\Facades\Artisan::output());
        }
        // Reconciliation must pass before relaxing the legacy constraint.
        // Assignment remains disabled until the following identity migration
        // installs organisation/number uniqueness and all integrity guards.
        Schema::table('pigs_management', function (Blueprint $table): void {
            $table->dropUnique('pigs_org_ear_tag_unique');
            $table->index(['organization_id', 'ear_tag_number'], 'pigs_org_ear_tag_lookup');
        });
    }

    public function down(): void
    {
        // Reintroducing number-only uniqueness can fail after two colours have
        // legitimately used the same number, so activation is not reversed
        // automatically.
    }
};
