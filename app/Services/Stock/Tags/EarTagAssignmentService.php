<?php

namespace App\Services\Stock\Tags;

use App\Models\EarTag;
use App\Models\EarTagAssignment;
use App\Models\EarTagColor;
use App\Models\PigManagement;
use App\Models\User;
use App\Services\Audit\AuditTrailService;
use App\Services\Stock\StockValidationException;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class EarTagAssignmentService
{
    public function __construct(private AuditTrailService $audit)
    {
    }

    public function assign(
        PigManagement $pig,
        EarTagColor $color,
        string $number,
        ?User $actor,
        CarbonInterface|string|null $assignedAt = null,
        ?string $notes = null,
        bool $migrationBackfill = false,
    ): EarTagAssignment {
        if (! $migrationBackfill && ! \Illuminate\Support\Facades\Schema::hasIndex('ear_tags', 'ear_tags_org_color_identity_ready')) {
            throw new StockValidationException('ear_tag_number', 'Tag assignment is disabled until the organisation tag migration and reconciliation are complete.');
        }
        $organizationId = (int) $pig->organization_id;
        if ($organizationId < 1 || (int) $color->organization_id !== $organizationId || ! $color->is_active) {
            throw new StockValidationException('ear_tag_color_id', 'Select an active tag colour belonging to this farm.');
        }

        $normalized = EarTag::normalizeNumber($number);
        if ($normalized === '') {
            throw new StockValidationException('ear_tag_number', 'Enter an ear tag number.');
        }

        return DB::transaction(function () use ($pig, $color, $number, $normalized, $actor, $assignedAt, $notes, $organizationId, $migrationBackfill) {
            \App\Models\Organization::whereKey($organizationId)->lockForUpdate()->firstOrFail();
            $this->assertActor($actor, $organizationId);
            $pig = PigManagement::withoutGlobalScopes()->withTrashed()->whereKey($pig->id)->where('organization_id', $organizationId)->lockForUpdate()->firstOrFail();
            if ($pig->trashed() && ! $migrationBackfill) {
                throw new StockValidationException('ear_tag_number', 'Restore the animal before assigning a physical tag.');
            }
            $legacyConflict = PigManagement::withoutGlobalScopes()->withTrashed()->where('organization_id', $organizationId)
                ->whereKeyNot($pig->id)->whereDoesntHave('earTagAssignments')->pluck('ear_tag_number')
                ->contains(fn ($value) => EarTag::normalizeNumber((string) $value) === $normalized);
            if ($legacyConflict) {
                throw new StockValidationException('ear_tag_number', 'This tag has unreconciled legacy ownership. Reconcile its history before assignment.');
            }
            $tag = EarTag::withoutGlobalScopes()->firstOrCreate(
                [
                    'organization_id' => $organizationId,
                    'ear_tag_color_id' => $color->id,
                    'normalized_tag_number' => $normalized,
                ],
                [
                    'ear_tag_color_id' => $color->id,
                    'tag_number' => trim($number),
                    'status' => EarTag::AVAILABLE,
                    'created_by' => $actor?->id,
                ]
            );

            $tag = EarTag::withoutGlobalScopes()->whereKey($tag->id)->lockForUpdate()->firstOrFail();
            if ($tag->status !== EarTag::AVAILABLE || $tag->currentAssignment()->exists()) {
                throw new StockValidationException('ear_tag_number', "{$color->name} tag {$normalized} is not available.");
            }
            if (EarTagAssignment::withoutGlobalScopes()->where('pig_id', $pig->id)->where('is_current', 1)->exists()) {
                throw new StockValidationException('ear_tag_number', 'This animal already has a current ear tag.');
            }

            $start = \Carbon\Carbon::parse($assignedAt ?? now());
            if (! $migrationBackfill) {
                $this->assertHistoryDates($pig, $start);
            }
            $overlap = EarTagAssignment::withoutGlobalScopes()->where('organization_id', $organizationId)
                ->where(fn ($q) => $q->where('pig_id', $pig->id)->orWhere('ear_tag_id', $tag->id))
                ->where(fn ($q) => $q->whereNull('released_at')->orWhere('released_at', '>', $start))->exists();
            if ($overlap) {
                throw new StockValidationException('assigned_at', 'The assignment date overlaps existing tag history.');
            }
            $assignment = EarTagAssignment::create([
                'organization_id' => $organizationId,
                'ear_tag_id' => $tag->id,
                'pig_id' => $pig->id,
                'assigned_at' => $start,
                'assigned_by' => $actor?->id,
                'notes' => $notes,
                'is_current' => 1,
            ]);

            $tag->update(['status' => EarTag::IN_USE, 'updated_by' => $actor?->id]);
            if ((string) $pig->ear_tag_number !== $tag->tag_number) {
                $pig->forceFill(['ear_tag_number' => $tag->tag_number])->save();
            }

            $logged = $this->audit->log('stock', 'ear_tag_assigned', [
                'user' => $actor,
                'auditable' => $assignment,
                'organization_id' => $organizationId,
                'record_label' => "{$color->name} {$normalized} · {$pig->code}",
                'description' => "Tag {$normalized} was assigned to animal {$pig->code}.",
                'new_values' => $assignment->only(['pig_id', 'ear_tag_id', 'assigned_at', 'is_current']),
                'metadata' => ['animal_code' => $pig->code, 'tag_number' => $tag->tag_number],
            ]);

            if (! $logged) {
                throw new \RuntimeException('Assignment audit could not be recorded. No tag changes were saved.');
            }
            return $assignment->load('tag.color');
        }, 3);
    }

    public function release(
        EarTagAssignment $assignment,
        string $tagStatus,
        string $reason,
        ?User $actor,
        CarbonInterface|string|null $releasedAt = null,
        ?string $notes = null,
    ): EarTagAssignment {
        if (! in_array($tagStatus, EarTag::STATUSES, true) || $tagStatus === EarTag::IN_USE) {
            throw new StockValidationException('tag_status', 'Select a valid physical tag outcome.');
        }

        return DB::transaction(function () use ($assignment, $tagStatus, $reason, $actor, $releasedAt, $notes) {
            \App\Models\Organization::whereKey($assignment->organization_id)->lockForUpdate()->firstOrFail();
            $this->assertActor($actor, (int) $assignment->organization_id);
            $locked = EarTagAssignment::withoutGlobalScopes()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            if (! $locked->is_current || $locked->released_at !== null) {
                throw new StockValidationException('ear_tag_number', 'This tag assignment has already been closed.');
            }

            $releaseDate = \Carbon\Carbon::parse($releasedAt ?? now());
            // Lifecycle forms contain a calendar date, so preserve same-day registrations.
            if ($releaseDate->lt($locked->assigned_at) && $releaseDate->isSameDay($locked->assigned_at)) {
                $releaseDate = $locked->assigned_at->copy();
            }
            if ($releaseDate->lt($locked->assigned_at) || trim($reason) === '') {
                throw new StockValidationException('released_at', 'Release must be on or after assignment and must include a reason.');
            }
            $tag = EarTag::withoutGlobalScopes()->whereKey($locked->ear_tag_id)->lockForUpdate()->firstOrFail();
            $locked->update([
                'released_at' => $releaseDate,
                'release_reason' => $reason,
                'released_by' => $actor?->id,
                'notes' => $notes ?? $locked->notes,
                'is_current' => null,
            ]);
            $tag->update(['status' => $tagStatus, 'updated_by' => $actor?->id]);
            // During staged legacy backfill this column is still NOT NULL and
            // unique. Preserve its old value until activation removes that
            // constraint; otherwise the second archived animal would collide
            // on an empty-string cache. Activation clears these closed caches.
            if (! \Illuminate\Support\Facades\Schema::hasIndex('pigs_management', 'pigs_org_ear_tag_unique')) {
                PigManagement::withoutGlobalScopes()->withTrashed()->whereKey($locked->pig_id)
                    ->where('organization_id', $locked->organization_id)->update(['ear_tag_number' => '']);
            }

            $logged = $this->audit->log('stock', 'ear_tag_released', [
                'user' => $actor,
                'auditable' => $locked,
                'organization_id' => $locked->organization_id,
                'record_label' => "{$tag->tag_number} · assignment {$locked->id}",
                'old_values' => ['released_at' => null, 'status' => EarTag::IN_USE],
                'new_values' => ['released_at' => $releaseDate, 'status' => $tagStatus, 'release_reason' => $reason],
                'metadata' => ['pig_id' => $locked->pig_id, 'ear_tag_id' => $tag->id],
                'description' => "Ear tag assignment was closed as {$reason}; physical tag status is {$tagStatus}.",
            ]);

            if (! $logged) {
                throw new \RuntimeException('Release audit could not be recorded. No tag changes were saved.');
            }
            return $locked->fresh(['tag.color', 'pig']);
        }, 3);
    }
    /** Physical tagging cannot precede birth or describe a future event. */
    public function assertHistoryDates(PigManagement $pig, CarbonInterface $start, ?CarbonInterface $end = null): void
    {
        if ($start->isFuture()) {
            throw new StockValidationException('assigned_at', 'The assignment date cannot be in the future.');
        }
        if ($pig->date_of_birth && $start->toDateString() < $pig->date_of_birth->toDateString()) {
            throw new StockValidationException('assigned_at', 'The assignment date cannot be before this animal was born.');
        }
        if ($end?->isFuture()) {
            throw new StockValidationException('released_at', 'The release date cannot be in the future.');
        }
    }

    public function assertActor(?User $actor, int $organizationId): void
    {
        if ($actor && (int) $actor->organization_id !== $organizationId) {
            throw new StockValidationException('ear_tag_number', 'The user and tag must belong to the same farm.');
        }
    }

    public function assignAutomatically(PigManagement $pig, EarTagColor $color, ?User $actor): EarTagAssignment
    {
        return DB::transaction(function () use ($pig, $color, $actor) {
            \App\Models\Organization::whereKey($pig->organization_id)->lockForUpdate()->firstOrFail();
            $candidate = app(EarTagAllocator::class)->propose((int) $pig->organization_id, $color)[0] ?? null;
            if (! $candidate) {
                throw new StockValidationException('ear_tag_number', 'No eligible tag could be allocated.');
            }
            return $this->assign($pig, $color, $candidate['tag_number'], $actor);
        }, 3);
    }

}
