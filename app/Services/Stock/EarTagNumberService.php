<?php

namespace App\Services\Stock;

use App\Models\PigManagement;
use App\Support\CurrentOrganization;

class EarTagNumberService
{
    public function __construct(protected CurrentOrganization $currentOrganization)
    {
    }

    /** @return array<int, string> */
    public function propose(int $quantity, ?int $organizationId = null): array
    {
        if ($quantity <= 0) {
            return [];
        }

        $organizationId ??= $this->currentOrganization->id();
        $usedTags = PigManagement::withoutGlobalScopes()
            ->withTrashed()
            ->when($organizationId !== null, fn ($query) => $query->where('organization_id', $organizationId))
            ->pluck('ear_tag_number')
            ->filter(fn ($tag) => trim((string) $tag) !== '')
            ->map(fn ($tag) => trim((string) $tag))
            ->flip();

        // Ear tags in this system are farm-local numbers. Text-prefixed legacy
        // or demo tags must not change the convention for the next real animal.
        $numericTags = $usedTags->keys()
            ->filter(fn (string $tag) => ctype_digit($tag))
            ->map(fn (string $tag) => (int) $tag);
        $number = (int) ($numericTags->max() ?? 0);

        $proposals = [];
        while (count($proposals) < $quantity) {
            $number++;
            $candidate = (string) $number;
            if (! $usedTags->has($candidate)) {
                $proposals[] = $candidate;
            }
        }

        return $proposals;
    }
}
