<?php

namespace App\Services\Stock\Tags;

use App\Models\EarTagAssignment;
use App\Models\EarTagColor;
use App\Models\PigManagement;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Phase-2 compatibility writer. It mirrors the legacy ear_tag_number into the
 * new register without changing allocation or permitting reuse.
 */
class EarTagShadowService
{
    public const LEGACY_COLOR = 'Unspecified';

    public function __construct(private EarTagAssignmentService $assignments)
    {
    }

    public function syncNewPig(PigManagement $pig, ?User $actor = null, ?EarTagColor $color = null, bool $migrationBackfill = false): ?EarTagAssignment
    {
        if (! Schema::hasTable('ear_tag_assignments')
            || empty($pig->organization_id)
            || trim((string) $pig->ear_tag_number) === '') {
            return null;
        }

        $existing = EarTagAssignment::withoutGlobalScopes()
            ->where('organization_id', $pig->organization_id)
            ->where('pig_id', $pig->id)
            ->where('is_current', 1)
            ->first();
        if ($existing) {
            return $existing;
        }

        $color ??= $this->legacyColor((int) $pig->organization_id, $actor);

        return $this->assignments->assign(
            $pig,
            $color,
            (string) $pig->ear_tag_number,
            $actor,
            $pig->created_at ?? now(),
            'Assignment created from the legacy animal tag field; initial date uses registration time.',
            $migrationBackfill,
        );
    }

    public function legacyColor(int $organizationId, ?User $actor = null): EarTagColor
    {
        return EarTagColor::withoutGlobalScopes()->firstOrCreate(
            ['organization_id' => $organizationId, 'normalized_name' => 'unspecified'],
            [
                'name' => self::LEGACY_COLOR,
                'short_code' => 'UNSP',
                'is_active' => true,
                'created_by' => $actor?->id,
            ],
        );
    }
}
