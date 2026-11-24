<?php

namespace App\Services\Stock\Tags;

use App\Models\EarTag;
use App\Models\EarTagAssignment;
use App\Models\EarTagColor;
use App\Models\Organization;
use App\Models\User;
use App\Services\Audit\AuditTrailService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EarTagAdministration
{
    public function __construct(private AuditTrailService $audit) {}

    public function reconcile(EarTag $tag, User $actor, array $data): void
    {
        abort_unless(($actor->isAdmin() || $actor->isManager()) && (int) $actor->organization_id === (int) $tag->organization_id, 403);
        DB::transaction(function () use ($tag, $actor, $data) {
            Organization::whereKey($tag->organization_id)->lockForUpdate()->firstOrFail();
            $tag = EarTag::whereKey($tag->id)->lockForUpdate()->firstOrFail();
            $assigned = $tag->currentAssignment()->exists();
            if (! in_array($data['status'], EarTag::STATUSES, true) || $assigned !== ($data['status'] === EarTag::IN_USE)) {
                throw ValidationException::withMessages(['status' => 'The physical status conflicts with the current assignment. Refresh the page.']);
            }
            $color = EarTagColor::where('organization_id', $tag->organization_id)->whereKey($data['ear_tag_color_id'])->where('is_active', true)->firstOrFail();
            if (EarTag::where('organization_id', $tag->organization_id)->where('ear_tag_color_id', $color->id)
                ->where('normalized_tag_number', $tag->normalized_tag_number)->whereKeyNot($tag->id)->exists()) {
                throw ValidationException::withMessages(['ear_tag_color_id' => 'This colour and number already identify another physical tag.']);
            }
            $before = $tag->only(['ear_tag_color_id', 'status', 'condition_notes']);
            $tag->update(['ear_tag_color_id' => $color->id, 'status' => $data['status'], 'condition_notes' => $data['condition_notes'] ?? null, 'updated_by' => $actor->id]);
            $logged = $this->audit->log('stock', 'ear_tag_reconciled', [
                'auditable' => $tag, 'user' => $actor, 'organization_id' => $tag->organization_id,
                'old_values' => $before, 'new_values' => $tag->only(array_keys($before)),
                'description' => $data['condition_notes'] ?? 'Physical tag status reviewed.',
            ]);
            if (! $logged) {
                throw new \RuntimeException('Tag reconciliation audit failed. No changes were saved.');
            }
        }, 3);
    }

    public function correct(EarTagAssignment $assignment, User $actor, array $data): void
    {
        abort_unless($actor->isAdmin() && (int) $actor->organization_id === (int) $assignment->organization_id, 403);
        if (trim($data['correction_reason'] ?? '') === '') {
            throw ValidationException::withMessages(['correction_reason' => 'Explain why this history is being corrected.']);
        }
        DB::transaction(function () use ($assignment, $actor, $data) {
            Organization::whereKey($assignment->organization_id)->lockForUpdate()->firstOrFail();
            $row = EarTagAssignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $start = Carbon::parse($data['assigned_at']);
            $end = empty($data['released_at']) ? null : Carbon::parse($data['released_at']);
            try {
                app(EarTagAssignmentService::class)->assertHistoryDates($row->pig, $start, $end);
            } catch (\App\Services\Stock\StockValidationException $e) {
                throw ValidationException::withMessages([$e->field => $e->getMessage()]);
            }
            if (($row->released_at === null) !== ($end === null) || ($end && $end->lt($start))) {
                throw ValidationException::withMessages(['released_at' => 'Corrections cannot reopen or release assignments. Release dates must follow assignment dates.']);
            }
            $overlap = EarTagAssignment::where('organization_id', $row->organization_id)->whereKeyNot($row->id)
                ->where(fn ($q) => $q->where('pig_id', $row->pig_id)->orWhere('ear_tag_id', $row->ear_tag_id))
                ->when($end, fn ($q) => $q->where('assigned_at', '<', $end))
                ->where(fn ($q) => $q->whereNull('released_at')->orWhere('released_at', '>', $start))->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['assigned_at' => 'These dates overlap another assignment for this animal or tag.']);
            }
            $before = $row->only(['assigned_at', 'released_at', 'release_reason', 'notes', 'correction_reason']);
            $row->update(['assigned_at' => $start, 'released_at' => $end, 'release_reason' => $end ? $data['release_reason'] : null, 'correction_reason' => $data['correction_reason']]);
            $logged = $this->audit->log('stock', 'ear_tag_corrected', [
                'auditable' => $row, 'user' => $actor, 'organization_id' => $row->organization_id,
                'old_values' => $before, 'new_values' => $row->only(array_keys($before)),
                'description' => $data['correction_reason'],
            ]);
            if (! $logged) {
                throw new \RuntimeException('Assignment correction audit failed. No changes were saved.');
            }
        }, 3);
    }
}
