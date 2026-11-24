<?php

namespace App\Services\Health;

use App\Models\PigManagement;
use Illuminate\Support\Collection;

/**
 * Finds pigs for health forms by system code, or by ear-tag colour and number
 * ("green 21", "21"). The picker also opens as a list of the farm's live
 * animals (browse), fetched when it is opened rather than built into the page.
 */
class HealthPigFinder
{
    public const LIMIT = 20;

    /** Most animals the opened list shows; beyond it, typing searches them all. */
    public const BROWSE_LIMIT = 1000;

    /** @return Collection<int, array{id: int, code: string, label: string, room: ?string, left: bool}> live pigs by code */
    public function browse(): Collection
    {
        return PigManagement::query()
            ->with(['room', 'currentEarTagAssignment.tag.color'])
            ->orderBy('code')
            ->limit(self::BROWSE_LIMIT)
            ->get()
            ->map(fn (PigManagement $pig) => $this->row($pig));
    }

    /** @return Collection<int, array{id: int, code: string, label: string, room: ?string, left: bool}> */
    public function search(string $query, bool $includeDeparted = false): Collection
    {
        $query = trim($query);
        if ($query === '') {
            return collect();
        }
        $number = null;
        $words = [];
        foreach (preg_split('/\s+/', $query) as $token) {
            ctype_digit($token) ? $number = ltrim($token, '0') ?: '0' : $words[] = mb_strtolower($token);
        }

        return PigManagement::query()
            ->when($includeDeparted, fn ($q) => $q->withTrashed())
            ->where(function ($q) use ($query, $number, $words) {
                $q->where('code', 'like', '%'.$query.'%')
                    ->orWhereHas('currentEarTagAssignment.tag', function ($tag) use ($number, $words) {
                        $tag->when($number !== null, fn ($t) => $t->where('normalized_tag_number', $number))
                            ->when($words !== [], fn ($t) => $t->whereHas('color', function ($colour) use ($words) {
                                foreach ($words as $word) {
                                    $colour->where('normalized_name', 'like', '%'.$word.'%');
                                }
                            }))
                            ->when($number === null && $words === [], fn ($t) => $t->whereRaw('1 = 0'));
                    });
            })
            ->with(['room', 'currentEarTagAssignment.tag.color'])
            ->orderBy('code')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (PigManagement $pig) => $this->row($pig));
    }

    /** Live pigs in a room, for building a group case or course. */
    public function inRoom(int $roomId): Collection
    {
        return PigManagement::query()->where('room_id', $roomId)
            ->with(['room', 'currentEarTagAssignment.tag.color'])->orderBy('code')->get()
            ->map(fn (PigManagement $pig) => $this->row($pig));
    }

    /** Rows for ids sent back by a form that failed validation, so picks are not lost. */
    public static function rowsFor(array $ids): array
    {
        $finder = new self();

        return PigManagement::withTrashed()->whereKey(array_map('intval', $ids))->with(['room', 'currentEarTagAssignment.tag.color'])
            ->get()->map(fn (PigManagement $pig) => $finder->row($pig))->values()->all();
    }

    public function row(PigManagement $pig): array
    {
        return [
            'id'    => (int) $pig->id,
            'code'  => (string) $pig->code,
            'label' => $pig->identityAt(),
            'room'  => $pig->room?->name,
            'left'  => $pig->trashed(),
        ];
    }
}
