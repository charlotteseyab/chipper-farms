<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('ear_tags')->select('organization_id', 'ear_tag_color_id', 'normalized_tag_number')
            ->groupBy('organization_id', 'ear_tag_color_id', 'normalized_tag_number')->havingRaw('COUNT(*) > 1')->get();
        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException('Tag migration blocked: duplicate organisation/colour/tag identities. Reconcile these physical identities before retrying: '.$duplicates->toJson());
        }
        $invalid = DB::table('ear_tag_assignments as a')
            ->join('ear_tags as t', 't.id', '=', 'a.ear_tag_id')
            ->join('pigs_management as p', 'p.id', '=', 'a.pig_id')
            ->whereRaw('a.organization_id <> t.organization_id OR a.organization_id <> p.organization_id OR (a.released_at IS NULL AND (a.is_current IS NULL OR a.is_current <> 1)) OR (a.released_at IS NOT NULL AND a.is_current IS NOT NULL) OR a.released_at < a.assigned_at')->exists();
        if ($invalid) {
            throw new RuntimeException('Tag migration blocked: inconsistent assignment dates, state or organisation. Run ear-tags:reconcile and resolve conflicts.');
        }
        if (\Illuminate\Support\Facades\Artisan::call('ear-tags:reconcile') !== 0) {
            throw new RuntimeException('Tag activation blocked by reconciliation: '.\Illuminate\Support\Facades\Artisan::output());
        }
        Schema::table('pigs_management', fn (Blueprint $table) => $table->string('ear_tag_number')->nullable()->change());

        Schema::table('pigs_management', fn (Blueprint $table) => $table->unique(['id', 'organization_id'], 'pigs_id_org_tag_reference'));
        Schema::table('ear_tag_colors', fn (Blueprint $table) => $table->unique(['id', 'organization_id'], 'ear_colors_id_org_reference'));
        Schema::table('ear_tags', function (Blueprint $table) {
            $table->unique(['id', 'organization_id'], 'ear_tags_id_org_reference');
            $table->foreign(['ear_tag_color_id', 'organization_id'], 'ear_tags_color_org_fk')->references(['id', 'organization_id'])->on('ear_tag_colors')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::table('ear_tag_assignments', function (Blueprint $table) {
            $table->foreign(['ear_tag_id', 'organization_id'], 'ear_assignments_tag_org_fk')->references(['id', 'organization_id'])->on('ear_tags')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['pig_id', 'organization_id'], 'ear_assignments_pig_org_fk')->references(['id', 'organization_id'])->on('pigs_management')->restrictOnDelete()->restrictOnUpdate();
        });

        $invalidRow = "NEW.organization_id <> (SELECT organization_id FROM ear_tags WHERE id = NEW.ear_tag_id) OR NEW.organization_id <> (SELECT organization_id FROM pigs_management WHERE id = NEW.pig_id) OR (NEW.released_at IS NULL AND (NEW.is_current IS NULL OR NEW.is_current <> 1)) OR (NEW.released_at IS NOT NULL AND NEW.is_current IS NOT NULL) OR NEW.released_at < NEW.assigned_at";
        foreach (['INSERT', 'UPDATE'] as $event) {
            $condition = $invalidRow;
            if ($event === 'UPDATE') {
                $condition .= ' OR NEW.pig_id <> OLD.pig_id OR NEW.ear_tag_id <> OLD.ear_tag_id OR NEW.organization_id <> OLD.organization_id OR (OLD.released_at IS NOT NULL AND NEW.released_at IS NULL)';
            }
            $name = 'ear_assignments_guard_'.strtolower($event);
            if (DB::getDriverName() === 'sqlite') {
                DB::unprepared("CREATE TRIGGER {$name} BEFORE {$event} ON ear_tag_assignments FOR EACH ROW WHEN {$condition} BEGIN SELECT RAISE(ABORT, 'Invalid ear tag assignment'); END");
            } else {
                DB::unprepared("CREATE TRIGGER {$name} BEFORE {$event} ON ear_tag_assignments FOR EACH ROW BEGIN IF {$condition} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid ear tag assignment'; END IF; END");
            }
        }
        DB::table('pigs_management')->whereExists(fn ($q) => $q->selectRaw('1')
            ->from('ear_tag_assignments as a')->whereColumn('a.pig_id', 'pigs_management.id'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('ear_tag_assignments as a')
                ->whereColumn('a.pig_id', 'pigs_management.id')->whereNull('a.released_at'))
            ->update(['ear_tag_number' => '']);

        // Assignment is enabled only after every preceding integrity guard succeeds.
        Schema::table('ear_tags', fn (Blueprint $table) => $table->unique(['organization_id', 'ear_tag_color_id', 'normalized_tag_number'], 'ear_tags_org_color_identity_ready'));

    }

    public function down(): void
    {
        // History and the current-tag cache are deliberately retained. Restore
        // a verified pre-deployment backup to return to the legacy application.
        throw new RuntimeException('Ear tag identity migration is forward-only. Use the documented backup recovery procedure; do not drop assignment history.');
    }
};
