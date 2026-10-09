<?php

namespace App\Services\Feed;

use App\Models\FeedProductionPlan;
use App\Models\FeedType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FeedProductionPlanService
{
    public function create(User $actor, array $data): FeedProductionPlan
    {
        abort_unless($actor->hasPermission('manage_feed_production'), 403);

        return DB::transaction(function () use ($actor, $data) {
            Organization::whereKey($actor->organization_id)->lockForUpdate()->firstOrFail();
            $existing = FeedProductionPlan::where('organization_id', $actor->organization_id)->where('submission_key', $data['submission_key'])->first();
            if ($existing) {
                abort_unless((int) $existing->feed_type_id === (int) $data['feed_type_id'] && (float) $existing->planned_quantity_kg === (float) $data['planned_quantity_kg'] && $existing->production_date->toDateString() === $data['production_date'], 422, 'This submission key belongs to a different production plan.');

                return $existing;
            }
            $type = FeedType::where('organization_id', $actor->organization_id)->findOrFail($data['feed_type_id']);
            $snapshot = app(FeedRecipeService::class)->resolve($type, $data['production_date']);
            abort_if((float) $data['planned_quantity_kg'] <= 0, 422, 'Planned quantity must be positive.');
            $plan = FeedProductionPlan::create($data + ['organization_id' => $actor->organization_id,
                'recipe_snapshot' => $snapshot, 'created_by' => $actor->id]);
            app(FeedConfigurationService::class)->audit($actor, 'feed_production_planned', $plan, [], $plan->toArray(), 'Production planned; no inventory posted.');

            return $plan;
        }, 3);
    }

    public function complete(User $actor, FeedProductionPlan $plan, array $data): FeedProductionPlan
    {
        abort_unless($actor->hasPermission('manage_feed_production') && (int) $actor->organization_id === (int) $plan->organization_id, 403);

        return DB::transaction(function () use ($actor, $plan, $data) {
            Organization::whereKey($actor->organization_id)->lockForUpdate()->firstOrFail();
            $plan = FeedProductionPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
            if ($plan->status === 'completed') {
                return $plan;
            }
            abort_if(($plan->recipe_snapshot['effective_from'] ?? null) && $data['batch_date'] < $plan->recipe_snapshot['effective_from'], 422, 'Production cannot precede the selected recipe effective date.');
            $result = app(ProduceFeedBatchService::class)->execute($actor, [
                'feed_type_id' => $plan->feed_type_id, 'batch_date' => $data['batch_date'],
                'batch_size_kg' => $data['finished_quantity_kg'], 'planned_quantity_kg' => $plan->planned_quantity_kg,
                'recipe_snapshot' => $plan->recipe_snapshot, 'actual_quantities' => $data['actual_quantities'] ?? [],
                'submission_key' => $plan->submission_key, 'notes' => 'Confirmed from production plan #'.$plan->id,
            ]);
            $plan->update(['status' => 'completed', 'feed_batch_id' => $result['feed_batch']->id]);

            return $plan;
        }, 3);
    }
}
