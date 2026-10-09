<?php

namespace App\Console\Commands;

use App\Models\EarTag;
use App\Models\EarTagAssignment;
use App\Models\PigDeath;
use App\Models\PigManagement;
use App\Models\PigSale;
use App\Models\User;
use App\Services\Stock\Tags\EarTagAssignmentService;
use App\Services\Stock\Tags\EarTagShadowService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReconcileEarTags extends Command
{
    protected $signature = 'ear-tags:reconcile
                            {--backfill : Create missing register and assignment records.}
                            {--organization= : Limit the operation to one organisation ID.}
                            {--details : Show physical tags and their assignment holders.}';

    protected $description = 'Audit or backfill reusable ear-tag register data without enabling tag reuse.';

    public function handle(EarTagShadowService $shadow, EarTagAssignmentService $assignments): int
    {
        if (! Schema::hasTable('ear_tags') || ! Schema::hasTable('ear_tag_assignments')) {
            $this->error('Reusable ear-tag tables are not installed. Run migrations first.');
            return self::FAILURE;
        }

        $organizationId = $this->option('organization');
        if ($organizationId !== null && (! ctype_digit((string) $organizationId) || (int) $organizationId < 1)) {
            $this->error('--organization must be a positive organisation ID.');
            return self::INVALID;
        }

        $tags = EarTag::withoutGlobalScopes()->when($organizationId, fn ($q) => $q->where('organization_id', (int) $organizationId))
            ->with(['color', 'assignments.pig'])->get();
        $duplicates = $tags->groupBy(fn ($tag) => $tag->organization_id.'|'.$tag->ear_tag_color_id.'|'.$tag->normalized_tag_number)->filter(fn ($rows) => $rows->count() > 1);
        if ($duplicates->isNotEmpty() || $this->option('details')) {
            $this->table(['Tag ID', 'Organisation', 'Number', 'Colour', 'Status', 'Animal codes'], $tags->map(fn ($tag) => [
                $tag->id, $tag->organization_id, $tag->tag_number, $tag->color?->name, $tag->status,
                $tag->assignments->map(fn ($row) => ($row->released_at ? 'Previous: ' : 'Current: ').($row->pig?->code ?? 'MISSING'))->implode('; '),
            ])->all());
        }
        $invalid = $tags->filter(function ($tag) {
            $open = $tag->assignments->filter(fn ($row) => $row->released_at === null);
            return ($tag->status === EarTag::IN_USE) !== ($open->count() === 1)
                || $open->count() > 1
                || $tag->assignments->contains(fn ($row) => ! $row->pig
                    || (int) $row->pig->organization_id !== (int) $tag->organization_id
                    || (int) $row->organization_id !== (int) $tag->organization_id
                    || ($row->released_at === null) !== (bool) $row->is_current
                    || ($row->released_at && $row->released_at->lt($row->assigned_at)));
        });
        if ($duplicates->isNotEmpty() || $invalid->isNotEmpty()) {
            $this->line('Invalid tag IDs: '.$invalid->pluck('id')->implode(', '));
            $this->error('Critical conflicts: '.$duplicates->count().' duplicate organisation/colour/number group(s), '.$invalid->count().' invalid tag state(s). Reuse must remain disabled until these are reconciled.');
            return self::FAILURE;
        }

        $query = PigManagement::withoutGlobalScopes()->withTrashed()
            ->where(fn ($q) => $q->where('ear_tag_number', '!=', '')->orWhereHas('earTagAssignments'))
            ->when($organizationId, fn ($q) => $q->where('organization_id', (int) $organizationId))
            ->orderBy('id');

        $before = $this->metrics(clone $query);
        $this->showMetrics('Before', $before);

        if (! $this->option('backfill')) {
            $this->warn('Audit only: no data changed. Re-run with --backfill to create missing records.');
            return $before['missing_assignments'] > 0 || $before['mismatches'] > 0
                ? self::FAILURE
                : self::SUCCESS;
        }

        $created = 0;
        $closed = 0;
        $errors = [];

        $query->chunkById(200, function ($pigs) use ($shadow, $assignments, &$created, &$closed, &$errors): void {
            foreach ($pigs as $pig) {
                try {
                    $result = DB::transaction(function () use ($pig, $shadow, $assignments): array {
                        $any = EarTagAssignment::withoutGlobalScopes()->where('pig_id', $pig->id)->first();
                        if ($any) {
                            return [0, 0];
                        }

                        $actor = $this->actorFor($pig);
                        $assignment = $shadow->syncNewPig($pig, $actor, migrationBackfill: true);
                        if (! $assignment) {
                            return [0, 0];
                        }

                        if ($pig->trashed()) {
                            [$releasedAt, $reason] = $this->releaseContext($pig);
                            $assignments->release(
                                $assignment,
                                EarTag::PENDING_RECOVERY,
                                $reason,
                                $actor,
                                $releasedAt,
                                'Backfilled archived animal; physical tag requires farm review.',
                            );
                            return [1, 1];
                        }
                        return [1, 0];
                    });
                    // Count committed work only; a failed release rolls back
                    // its new assignment and must not inflate reconciliation.
                    $created += $result[0];
                    $closed += $result[1];
                } catch (\Throwable $exception) {
                    $errors[] = [
                        'pig_id' => $pig->id,
                        'organization_id' => $pig->organization_id,
                        'tag' => $pig->ear_tag_number,
                        'error' => $exception->getMessage(),
                    ];
                }
            }
        }, 'id');

        $this->info("Created {$created} assignment(s); closed {$closed} archived assignment(s) as pending recovery.");
        if ($errors !== []) {
            $this->error(count($errors).' record(s) could not be backfilled.');
            $this->table(['Pig', 'Organisation', 'Tag', 'Error'], $errors);
        }

        $afterQuery = PigManagement::withoutGlobalScopes()->withTrashed()
            ->where(fn ($q) => $q->where('ear_tag_number', '!=', '')->orWhereHas('earTagAssignments'))
            ->when($organizationId, fn ($q) => $q->where('organization_id', (int) $organizationId));
        $after = $this->metrics($afterQuery);
        $this->showMetrics('After', $after);

        return $errors !== [] || $after['missing_assignments'] > 0 || $after['mismatches'] > 0
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function metrics($pigQuery): array
    {
        $pigIds = (clone $pigQuery)->pluck('id');
        $assignments = EarTagAssignment::withoutGlobalScopes()
            ->whereIn('pig_id', $pigIds)->with('tag')->get();
        $byPig = $assignments->groupBy('pig_id');
        $missing = 0;
        $mismatches = 0;

        foreach ((clone $pigQuery)->get(['id', 'organization_id', 'ear_tag_number', 'deleted_at']) as $pig) {
            $rows = $byPig->get($pig->id, collect());
            if ($rows->isEmpty()) {
                $missing++;
                continue;
            }
            $expectedCurrent = ! $pig->trashed();
            $current = $rows->firstWhere('is_current', true);
            if (($expectedCurrent && ! $current) || (! $expectedCurrent && $current)) {
                $mismatches++;
                continue;
            }
            $relevant = $current ?: $rows->sortByDesc('assigned_at')->first();
            if (! $relevant?->tag
                || (int) $relevant->organization_id !== (int) $pig->organization_id
                || ($expectedCurrent && EarTag::normalizeNumber((string) $relevant->tag->tag_number) !== EarTag::normalizeNumber((string) $pig->ear_tag_number))) {
                $mismatches++;
            }
        }

        return [
            'animals' => $pigIds->count(),
            'assignments' => $assignments->count(),
            'missing_assignments' => $missing,
            'mismatches' => $mismatches,
            'pending_recovery' => EarTag::withoutGlobalScopes()
                ->where('status', EarTag::PENDING_RECOVERY)
                ->when($this->option('organization'), fn ($q) => $q->where('organization_id', (int) $this->option('organization')))
                ->count(),
        ];
    }

    private function showMetrics(string $label, array $metrics): void
    {
        $this->line($label.' reconciliation:');
        $this->table(['Tagged animals', 'Assignments', 'Missing', 'Mismatches', 'Pending recovery'], [[
            $metrics['animals'], $metrics['assignments'], $metrics['missing_assignments'],
            $metrics['mismatches'], $metrics['pending_recovery'],
        ]]);
    }

    private function actorFor(PigManagement $pig): ?User
    {
        $userId = PigDeath::withoutGlobalScopes()->withTrashed()->where('pig_id', $pig->id)->value('recorded_by')
            ?: PigSale::withoutGlobalScopes()->withTrashed()->where('pig_id', $pig->id)->value('sold_by_user_id');

        return $userId ? User::withoutGlobalScopes()->find($userId) : null;
    }

    /** @return array{0:mixed,1:string} */
    private function releaseContext(PigManagement $pig): array
    {
        $death = PigDeath::withoutGlobalScopes()->withTrashed()->where('pig_id', $pig->id)->latest('death_date')->first();
        if ($death) {
            return [$death->death_date, 'mortality'];
        }
        $sale = PigSale::withoutGlobalScopes()->withTrashed()->where('pig_id', $pig->id)->latest('sale_date')->first();
        if ($sale) {
            return [$sale->sale_date, 'sale'];
        }

        return [$pig->deleted_at ?? now(), 'other'];
    }
}
