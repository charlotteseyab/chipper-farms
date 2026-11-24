<?php

namespace App\Services\Feed;

use App\Domain\Events\Costing\FeedIssued;
use App\Domain\Events\Support\SourceReference;
use App\Models\FeedIssue;
use App\Models\FeedType;
use App\Models\Finance\InventoryBalance;
use App\Models\Finance\InventoryMovement;
use App\Models\Group;
use App\Models\Organization;
use App\Models\User;
use App\Services\DomainEvents\DispatchDomainEvent;
use App\Services\Finance\Costing\GroupCostPoolService;
use App\Services\Finance\Inventory\InventoryService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Phase 5 — orchestrate the feed issuance flow.
 *
 * Operator says: "I issued 50 kg of grower feed to nursery group 3 at
 * 10:30 today." This service:
 *
 *   1. Reads the current finished-feed WAC (from inventory_balances).
 *   2. Computes total_cost = quantity_kg × WAC.
 *   3. Persists a feed_issues row.
 *   4. Calls InventoryService::recordUsage on the finished feed
 *      (item_kind=finished_feed, source=(feed_issue, feed_issue.id)).
 *      → decrements finished feed inventory at WAC.
 *   5. Calls GroupCostPoolService::recordFeedIssue on the group's pool
 *      (source=(feed_issue, feed_issue.id)).
 *      → increases the pool by total_cost.
 *   6. Dispatches FeedIssued domain event → FeedIssuedSubscriber posts
 *      the GL leg (Dr 1230 / Cr 1220 at total_cost).
 *
 * All five DB writes inside one transaction. Either every ledger
 * reflects the issuance or none does.
 *
 * EDGE CASES
 *
 *   - Insufficient finished feed on hand: InventoryService throws
 *     InsufficientInventoryException. The whole transaction rolls back;
 *     no feed_issues row, no pool movement, no GL.
 *
 *   - WAC is zero (e.g. opening balance was zero-cost): total_cost = 0.
 *     The pool movement still gets recorded (for audit), but the
 *     subscriber should detect the zero amount and skip the GL post.
 *
 *   - Group is closed (closed_at != null): refused at this layer.
 *     A closed group represents pigs that all left; issuing feed to it
 *     is operationally meaningless.
 *
 *   - Issuance quantity zero or negative: rejected.
 */
class IssueFeedToGroupService
{
    public function __construct(
        protected CurrentOrganization $currentOrganization,
        protected InventoryService $inventoryService,
        protected GroupCostPoolService $poolService,
        protected DispatchDomainEvent $dispatcher,
    ) {}

    /**
     * @param  array{
     *   group_id: int,
     *   feed_type_id: int,
     *   quantity_kg: float,
     *   issued_at?: ?string,
     *   room_id?: ?int,
     *   reference?: ?string,
     *   notes?: ?string,
     * }  $data
     */
    public function execute(User $actor, array $data): FeedIssue
    {
        $orgId = $this->currentOrganization->requireId();
        $stage = $data['feeding_stage'] ?? null;
        if ($stage !== null && ! isset(FeedStageResolver::STAGES[$stage])) {
            throw new \DomainException("Unknown feeding stage '{$stage}'.");
        }
        $groupId = (int) ($data['group_id'] ?? 0);
        $feedTypeId = (int) $data['feed_type_id'];
        // Optional: the pen or room fed, which must be one of this farm's.
        $roomId = ! empty($data['room_id']) ? (int) $data['room_id'] : null;
        if ($roomId !== null && ! \App\Models\Room::where('organization_id', $orgId)->whereKey($roomId)->exists()) {
            throw new \DomainException('That pen or room is not on this farm.');
        }
        $qty = (float) $data['quantity_kg'];

        if ($qty <= 0 || ! is_finite($qty)) {
            throw new \InvalidArgumentException('Feed issue quantity_kg must be positive.');
        }

        // Stage issues resolve their system group inside the locked transaction.
        $group = $stage === null ? Group::find($groupId) : null;
        if ($stage === null && ! $group) {
            throw new \DomainException("Group #{$groupId} not found.");
        }

        $feedType = FeedType::find($feedTypeId);
        if (! $feedType) {
            throw new \DomainException("Feed type #{$feedTypeId} not found.");
        }

        $issuedAt = isset($data['issued_at'])
            ? CarbonImmutable::parse($data['issued_at'])
            : CarbonImmutable::now();

        return DB::transaction(function () use ($orgId, $groupId, $feedTypeId, $qty, $issuedAt, $actor, $data, $group, $feedType, $stage, $roomId) {
            Organization::whereKey($orgId)->lockForUpdate()->firstOrFail();
            if ($stage !== null) {
                $group = FeedStageResolver::groupFor($orgId, $stage);
                $groupId = (int) $group->id;
            }
            abort_unless((int) $actor->organization_id === $orgId
                && (int) $group->organization_id === $orgId && (int) $feedType->organization_id === $orgId, 403);
            if (! $feedType->is_active) {
                throw new \DomainException('This feed type is inactive.');
            }
            $key = $data['submission_key'] ?? null;
            if ($key && ($existing = FeedIssue::where('organization_id', $orgId)->where('submission_key', $key)->first())) {
                if ((int) $existing->group_id !== $groupId || $existing->feeding_stage !== $stage || (int) $existing->feed_type_id !== $feedTypeId || (float) $existing->quantity_kg !== $qty) {
                    throw new \DomainException('This submission key already belongs to a different feed issue.');
                }

                return $existing;
            }
            $unitCost = $this->readFinishedFeedWac($orgId, $feedTypeId);
            $totalCost = bcmul(number_format($qty, 6, '.', ''), $unitCost, 4);
            $previous = FeedIssue::where('organization_id', $orgId)->notReversed()->where('group_id', $groupId)->where('feed_type_id', $feedTypeId)
                ->whereDate('issued_at', $issuedAt->toDateString())->oldest('id')->first();
            // A stage is fed from the whole herd in that stage; a legacy group
            // issue plans only for the animals assigned to that group.
            $scope = $stage !== null ? ['stage' => $stage] : ['group_id' => $groupId];
            $snapshot = $previous ? ($previous->planning_snapshot ?? ['rows' => [], 'locations' => []]) : app(FeedPlanningService::class)->calculate($orgId,
                $issuedAt->toDateString(), $issuedAt->toDateString(), $scope + ['feed_type_id' => $feedTypeId], applyWastage: false);
            $snapshot['group_name'] ??= $stage !== null ? FeedStageResolver::STAGES[$stage] : $group->name;
            $snapshot['feed_name'] ??= $feedType->name;
            $planned = $previous ? $previous->planned_quantity_kg : (($snapshot['demand_complete'] ?? false) ? $snapshot['required_kg'] : null);

            // 1. Operational row first — gives us an id for the source linkage.
            $issue = FeedIssue::create([
                'organization_id' => $orgId,
                'submission_key' => $key, 'planned_quantity_kg' => $planned, 'planning_snapshot' => $snapshot,
                'group_id' => $groupId,
                'feeding_stage' => $stage,
                'room_id' => $roomId,
                'feed_type_id' => $feedTypeId,
                'quantity_kg' => number_format($qty, 4, '.', ''),
                'unit_cost_at_issuance' => $unitCost,
                'total_cost' => $totalCost,
                'issued_at' => $issuedAt,
                'issued_by_user_id' => $actor->id,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'posting_status' => FeedIssue::POSTING_PENDING,
            ]);

            // 2. Reduce finished feed inventory. Throws on overdraw.
            $this->inventoryService->recordUsage(
                itemKind: InventoryMovement::ITEM_KIND_FINISHED_FEED,
                itemId: $feedTypeId,
                quantity: $qty,
                sourceType: InventoryMovement::SOURCE_FEED_ISSUE,
                sourceId: (int) $issue->id,
                occurredAt: $issuedAt,
                actorUserId: $actor->id,
                reference: sprintf('Feed Issue #%d → %s', $issue->id, $snapshot['group_name']),
                notes: $data['notes'] ?? null,
            );

            // 3. Increase the group pool by the issued value.
            $this->poolService->recordFeedIssue(
                groupId: $groupId,
                totalCost: $totalCost,
                feedIssueId: (int) $issue->id,
                occurredAt: $issuedAt,
                actorUserId: $actor->id,
                reference: sprintf('Feed Issue #%d (%s)', $issue->id, $feedType->name),
                notes: $data['notes'] ?? null,
            );

            // 4. Dispatch domain event for the subscriber to post GL.
            $this->dispatcher->dispatch(new FeedIssued(
                source: new SourceReference(
                    module: 'feed',
                    sourceType: FeedIssue::class,
                    sourceId: (int) $issue->id,
                    organizationId: $orgId,
                ),
                occurredAt: $issuedAt,
                dispatchedByUserId: $actor->id,
                feedIssueId: (int) $issue->id,
                groupId: $groupId,
                feedTypeId: $feedTypeId,
                feedTypeName: (string) $feedType->name,
                quantityKg: $qty,
                unitCost: (float) $unitCost,
                totalCost: (float) $totalCost,
            ));

            app(FeedConfigurationService::class)->audit($actor, 'feed_issued', $issue, [], ['quantity_kg' => $qty, 'planned_quantity_kg' => $planned, 'snapshot' => $snapshot], 'Feed issued to group; inventory and group cost recorded.');

            return $issue->fresh();
        });
    }

    /**
     * Read the finished-feed WAC from the unified inventory balances.
     * Returns '0' if no balance row exists yet (zero-value issuance
     * still records the event for audit, but the subscriber will skip
     * the GL post).
     */
    protected function readFinishedFeedWac(int $orgId, int $feedTypeId): string
    {
        $balance = InventoryBalance::query()
            ->where('organization_id', $orgId)
            ->where('item_kind', InventoryBalance::ITEM_KIND_FINISHED_FEED)
            ->where('item_id', $feedTypeId)
            ->lockForUpdate()->first();

        if (! $balance || bccomp((string) $balance->weighted_avg_cost, '0', 6) <= 0) {
            return '0';
        }

        return (string) $balance->weighted_avg_cost;
    }
}
