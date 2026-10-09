<?php

namespace App\Services\Stock\Tags;

use App\Models\EarTag;
use App\Models\EarTagAssignment;
use App\Models\PigManagement;
use Carbon\Carbon;

class EarTagHistory
{
    public function numberAt(PigManagement $pig, mixed $date): ?string
    {
        $assignment = $this->assignmentAt($pig, $date);

        return $assignment ? $assignment->tag?->tag_number : $this->legacyNumber($pig);
    }

    /**
     * Colour and number, e.g. "Green 5". Colour is part of physical identity,
     * so Green 5 and Yellow 5 must not share a historical label.
     */
    public function labelAt(PigManagement $pig, mixed $date): ?string
    {
        $assignment = $this->assignmentAt($pig, $date);

        return $assignment ? self::label($assignment->tag) : $this->legacyNumber($pig);
    }

    /** The unconfirmed legacy colour is omitted rather than shown as "Unspecified 5". */
    public static function label(?EarTag $tag): ?string
    {
        if (! $tag) {
            return null;
        }
        $colour = $tag->color;

        return $colour && $colour->normalized_name !== 'unspecified'
            ? "{$colour->name} {$tag->tag_number}"
            : $tag->tag_number;
    }

    /**
     * Eager-load this on animals before resolving tags for many rows (lists,
     * reports, exports); resolution then runs in memory instead of one or two
     * queries per row.
     */
    public const RELATION = 'earTagAssignments.tag.color';

    /** The assignment with the latest start, or null for an animal never tagged. */
    public static function latestAssignment(PigManagement $pig): ?EarTagAssignment
    {
        if ($pig->relationLoaded('earTagAssignments')) {
            return self::newestFirst($pig->earTagAssignments)->first();
        }

        return $pig->earTagAssignments()->with('tag.color')->orderByDesc('assigned_at')->orderByDesc('id')->first();
    }

    private function assignmentAt(PigManagement $pig, mixed $date): ?EarTagAssignment
    {
        if ($pig->relationLoaded('earTagAssignments')) {
            return $this->loadedAssignmentAt($pig, $date);
        }
        if ($date === null) {
            return $pig->currentEarTagAssignment?->loadMissing('tag.color');
        }
        $when = Carbon::parse($date);
        $query = $pig->earTagAssignments()->where('organization_id', $pig->organization_id)->with('tag.color');
        // Operational records store calendar dates. A date-only event cannot
        // distinguish two replacements on the same day; latest that day wins.
        if (self::isDateOnly($when)) {
            $query->whereDate('assigned_at', '<=', $when->toDateString())
                ->where(fn ($q) => $q->whereNull('released_at')->orWhereDate('released_at', '>=', $when->toDateString()));
        } else {
            $query->where('assigned_at', '<=', $when)
                ->where(fn ($q) => $q->whereNull('released_at')->orWhere('released_at', '>=', $when));
        }

        return $query->orderByDesc('assigned_at')->orderByDesc('id')->first();
    }

    /** Same rules as the query path above, applied to preloaded assignments. */
    private function loadedAssignmentAt(PigManagement $pig, mixed $date): ?EarTagAssignment
    {
        $rows = $pig->earTagAssignments->where('organization_id', $pig->organization_id);
        if ($date === null) {
            return $rows->first(fn (EarTagAssignment $row) => $row->is_current);
        }
        $when = Carbon::parse($date);
        if (self::isDateOnly($when)) {
            $day = $when->toDateString();
            $rows = $rows->filter(fn (EarTagAssignment $row) => $row->assigned_at->toDateString() <= $day
                && ($row->released_at === null || $row->released_at->toDateString() >= $day));
        } else {
            $rows = $rows->filter(fn (EarTagAssignment $row) => $row->assigned_at->lte($when)
                && ($row->released_at === null || $row->released_at->gte($when)));
        }

        return self::newestFirst($rows)->first();
    }

    private static function newestFirst(iterable $rows)
    {
        return collect($rows)->sort(fn (EarTagAssignment $a, EarTagAssignment $b) => [$b->assigned_at, $b->id] <=> [$a->assigned_at, $a->id]);
    }

    private static function isDateOnly(Carbon $when): bool
    {
        return $when->format('H:i:s') === '00:00:00';
    }

    // Legacy data has no reconstructed history until backfill. Never
    // substitute a current tag for a date outside a known assignment.
    private function legacyNumber(PigManagement $pig): ?string
    {
        $tagged = $pig->relationLoaded('earTagAssignments')
            ? $pig->earTagAssignments->isNotEmpty()
            : $pig->earTagAssignments()->exists();

        return $tagged ? null : ($pig->ear_tag_number ?: null);
    }
}
