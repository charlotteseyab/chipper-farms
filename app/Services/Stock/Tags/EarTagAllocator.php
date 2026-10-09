<?php

namespace App\Services\Stock\Tags;

use App\Models\EarTag;
use App\Models\EarTagColor;

class EarTagAllocator
{
    /**
     * Return eligible suggestions without reserving them. Assignment must
     * recheck under a row lock; a suggestion is never a promise.
     *
     * @return list<array<string, mixed>>
     */
    public function propose(int $organizationId, EarTagColor $color, int $quantity = 1): array
    {
        if ($quantity < 1 || (int) $color->organization_id !== $organizationId || ! $color->is_active) {
            return [];
        }

        $tags = EarTag::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('ear_tag_color_id', $color->id)
            ->with(['assignments' => fn ($query) => $query->orderByDesc('released_at')])
            ->get();

        $available = $tags->where('status', EarTag::AVAILABLE)
            ->filter(fn (EarTag $tag) => ! $tag->assignments->contains(fn ($row) => $row->released_at === null))
            ->sort(function (EarTag $a, EarTag $b) {
                return strnatcmp($a->normalized_tag_number, $b->normalized_tag_number);
            });

        $result = [];
        foreach ($available as $tag) {
            $last = $tag->assignments->first();
            $result[] = $this->payload($tag->normalized_tag_number, $tag, $last);
            if (count($result) === $quantity) {
                return $result;
            }
        }

        $legacy = \App\Models\PigManagement::withoutGlobalScopes()->withTrashed()->where('organization_id', $organizationId)
            ->whereDoesntHave('earTagAssignments')->pluck('ear_tag_number')->map(fn ($number) => EarTag::normalizeNumber((string) $number));
        $used = $tags->pluck('normalized_tag_number')->merge($legacy)->filter(fn ($value) => ctype_digit((string) $value))
            ->flip();
        $candidate = 1;
        while (count($result) < $quantity) {
            if (! $used->has($candidate)) {
                $result[] = $this->payload((string) $candidate);
                $used->put($candidate, true);
            }
            $candidate++;
        }

        return $result;
    }

    private function payload(string $number, ?EarTag $tag = null, $last = null): array
    {
        return [
            'ear_tag_id' => $tag?->id,
            'tag_number' => $number,
            'previously_used' => $tag !== null && $tag->assignments->isNotEmpty(),
            'reuse_count' => $tag?->assignments->count() ?? 0,
            'last_released_at' => $last?->released_at,
            'last_release_reason' => $last?->release_reason,
        ];
    }
}
