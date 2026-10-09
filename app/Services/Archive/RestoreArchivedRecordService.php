<?php

namespace App\Services\Archive;

use App\Models\PigletDeath;
use App\Models\PigManagement;
use App\Models\User;
use App\Services\Audit\AuditTrailService;
use App\Models\EarTag;
use App\Services\Stock\StockValidationException;
use App\Services\Stock\Tags\EarTagAssignmentService;
use Illuminate\Support\Facades\DB;

/**
 * Un-archive (restore) pigs and piglet death records.
 *
 * Extracted from PigManagementController::restorePig (was line 1818) and
 * ::restorePigletDeath (was line 1811).
 *
 * Deliberately kept minimal — these are simple soft-delete reversals and
 * no domain event is emitted because restoration is a data-correction
 * action, not a lifecycle event.
 *
 * Wrapped in a transaction to match the discipline of the other services.
 */
class RestoreArchivedRecordService
{
    public function __construct(
        protected AuditTrailService $audit,
        protected EarTagAssignmentService $tagAssignments,
    ) {}

    public function restorePig(PigManagement $pig, User $actor): PigManagement
    {
        return DB::transaction(function () use ($pig, $actor) {
            \App\Models\Organization::whereKey($pig->organization_id)->lockForUpdate()->firstOrFail();
            $this->tagAssignments->assertActor($actor, (int) $pig->organization_id);
            $pig = PigManagement::withTrashed()->whereKey($pig->id)->lockForUpdate()->firstOrFail();
            if (! $pig->trashed()) {
                return $pig;
            }
            $latest = $pig->earTagAssignments()->with('tag.color')->orderByDesc('assigned_at')->orderByDesc('id')->first();
            $pig->restore();
            $color = $latest?->tag?->color
                ?? app(\App\Services\Stock\Tags\EarTagShadowService::class)->legacyColor((int) $pig->organization_id, $actor);
            if (! $color->is_active) {
                $color = app(\App\Services\Stock\Tags\EarTagShadowService::class)->legacyColor((int) $pig->organization_id, $actor);
            }
            $formerTag = $latest?->tag
                ? EarTag::withoutGlobalScopes()->whereKey($latest->tag->id)->lockForUpdate()->first()
                : null;
            // An archive or lifecycle event recorded in error leaves the tag in
            // the animal's ear, so Pending Recovery is resolved by the restore
            // itself. Lost, damaged, retired or reserved tags are never reused,
            // and a tag already worn by another animal is never displaced.
            $reusable = $formerTag
                && (int) $formerTag->ear_tag_color_id === (int) $color->id
                && in_array($formerTag->status, [EarTag::AVAILABLE, EarTag::PENDING_RECOVERY], true)
                && ! $formerTag->currentAssignment()->exists();
            if ($reusable) {
                $note = 'Restored animal: new assignment.';
                if ($formerTag->status === EarTag::PENDING_RECOVERY) {
                    $formerTag->update(['status' => EarTag::AVAILABLE, 'updated_by' => $actor->id]);
                    $note = 'Restored animal: former tag was pending recovery and is still worn by this animal.';
                }
                $this->tagAssignments->assign($pig, $color, $formerTag->tag_number, $actor, now(), $note);
            } else {
                $this->tagAssignments->assignAutomatically($pig, $color, $actor);
            }

            $this->audit->log('stock', 'restored', [
                'user' => $actor,
                'auditable' => $pig,
                'organization_id' => $pig->organization_id,
                'record_label' => $pig->ear_tag_number ?: $pig->code,
                'description' => "Pig {$pig->code} was restored to active stock.",
            ]);

            return $pig;
        });
    }

    public function restorePigletDeath(PigletDeath $pigletDeath, User $actor): PigletDeath
    {
        return DB::transaction(function () use ($pigletDeath, $actor) {
            if ($pigletDeath->trashed()) {
                $pigletDeath->restore();
            }

            $this->audit->log('mortality', 'restored', [
                'user' => $actor,
                'auditable' => $pigletDeath,
                'record_label' => 'Piglet death #' . $pigletDeath->id,
                'description' => 'Archived piglet death record was restored.',
            ]);

            return $pigletDeath;
        });
    }
}
