<?php

namespace App\Services\Feed;

use App\Models\FeedingStandard;
use App\Models\FeedStageOverride;
use App\Models\FeedType;
use App\Models\Organization;
use App\Models\PigManagement;
use App\Models\User;
use App\Services\Audit\AuditTrailService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FeedConfigurationService
{
    public function standard(User $actor, array $data): FeedingStandard
    {
        abort_unless($actor->hasPermission('manage_feed_production'), 403);
        $data = Validator::make($data, [
            'stage' => ['required', Rule::in(array_keys(FeedStageResolver::STAGES))],
            'feed_type_id' => ['required', 'integer'],
            'daily_kg' => ['required', 'decimal:0,4', 'numeric', 'gt:0', 'max:100000'],
            'effective_from' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'is_active' => ['sometimes', 'boolean'],
        ])->validate();

        return DB::transaction(function () use ($actor, $data) {
            Organization::whereKey($actor->organization_id)->lockForUpdate()->firstOrFail();
            FeedType::where('organization_id', $actor->organization_id)->where('is_active', true)->findOrFail($data['feed_type_id']);
            if (FeedingStandard::where('organization_id', $actor->organization_id)->where('stage', $data['stage'])->whereDate('effective_from', $data['effective_from'])->exists()) {
                throw ValidationException::withMessages(['effective_from' => 'A version for this stage and effective date already exists. Choose a later date.']);
            }
            $row = FeedingStandard::create($data + ['organization_id' => $actor->organization_id,
                'stage_basis' => FeedStageResolver::basis($data['stage']), 'created_by' => $actor->id]);
            $this->audit($actor, 'feeding_standard_created', $row, [], $row->toArray(), 'New effective-dated feeding standard.');

            return $row;
        }, 3);
    }

    public function override(User $actor, PigManagement $pig, ?string $stage, string $reason): FeedStageOverride
    {
        abort_unless($actor->hasPermission('manage_feed_production') && (int) $pig->organization_id === (int) $actor->organization_id, 403);
        Validator::make(['stage' => $stage, 'reason' => $reason], [
            'stage' => ['nullable', Rule::in(array_keys(FeedStageResolver::STAGES))],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ])->validate();

        return DB::transaction(function () use ($actor, $pig, $stage, $reason) {
            Organization::whereKey($actor->organization_id)->lockForUpdate()->firstOrFail();
            $pig = PigManagement::active()->where('organization_id', $actor->organization_id)->findOrFail($pig->id);
            $before = app(FeedStageResolver::class)->resolve($pig);
            $row = FeedStageOverride::create(['organization_id' => $pig->organization_id, 'pig_id' => $pig->id,
                'previous_stage' => $before, 'stage' => $stage, 'reason' => $reason, 'created_by' => $actor->id]);
            app(FeedStageResolver::class)->forgetOverrides();
            $this->audit($actor, 'feed_stage_overridden', $row, ['stage' => $before], ['stage' => $stage], $reason);

            return $row;
        }, 3);
    }

    public function audit(User $actor, string $action, $model, array $before, array $after, string $reason): void
    {
        if (! app(AuditTrailService::class)->log('feed', $action, ['user' => $actor, 'organization_id' => $actor->organization_id,
            'auditable' => $model, 'old_values' => $before, 'new_values' => $after, 'description' => $reason])) {
            throw new \RuntimeException('The feed audit could not be saved. No changes were committed.');
        }
    }
}
