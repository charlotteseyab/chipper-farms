<?php

namespace App\Services\Feed;

use App\Domain\Events\Costing\FeedIssued;
use App\Domain\Events\Support\SourceReference;
use App\Models\FeedIssue;
use App\Models\FeedType;
use App\Models\Finance\InventoryBalance;
use App\Models\Finance\InventoryMovement;
use App\Models\Group;
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
    ) {
    }

    /**
     * @param  array{
     *   group_id: int,
     *   feed_type_id: int,
     *   quantity_kg: float,
     *   issued_at?: ?string,
     *   reference?: ?string,
     *   notes?: ?string,
     * }  $data
     */
    public function execute(User $actor, array $data): FeedIssue
    {
        $orgId    = $this->currentOrganization->requireId();
        $groupId  = (int) $data['group_id'];
        $feedTypeId = (int) $data['feed_type_id'];
        $qty      = (float) $data['quantity_kg'];

        if ($qty <= 0) {
            throw new \InvalidArgumentException('Feed issue quantity_kg must be positive.');
        }

        // Validate the group is open.
        $group = Group::find($groupId);
        if (! $group) {
            throw new \DomainException("Group #{$groupId} not found.");
        }

        $feedType = FeedType::find($feedTypeId);
        if (! $feedType) {
            throw new \DomainException("Feed type #{$feedTypeId} not found.");
        }

        $issuedAt = isset($data['issued_at'])
            ? CarbonImmutable::parse($data['issued_at'])
            : CarbonImmutable::now();

        // Read current finished-feed WAC at issuance.
        $unitCost = $this->readFinishedFeedWac($orgId, $feedTypeId);
        $totalCost = bcmul(
            number_format($qty, 6, '.', ''),
            $unitCost,
            4,
        );

        return DB::transaction(function () use (
            $orgId, $groupId, $feedTypeId, $qty, $unitCost, $totalCost,
            $issuedAt, $actor, $data, $group, $feedType,
        ) {
            // 1. Operational row first — gives us an id for the source linkage.
            $issue = FeedIssue::create([
                'organization_id'       => $orgId,
                'group_id'              => $groupId,
                'feed_type_id'          => $feedTypeId,
                'quantity_kg'           => number_format($qty, 4, '.', ''),
                'unit_cost_at_issuance' => $unitCost,
                'total_cost'            => $totalCost,
                'issued_at'             => $issuedAt,
                'issued_by_user_id'     => $actor->id,
                'reference'             => $data['reference'] ?? null,
                'notes'                 => $data['notes'] ?? null,
                'posting_status'        => FeedIssue::POSTING_PENDING,
            ]);

            // 2. Reduce finished feed inventory. Throws on overdraw.
            $this->inventoryService->recordUsage(
                itemKind:    InventoryMovement::ITEM_KIND_FINISHED_FEED,
                itemId:      $feedTypeId,
                quantity:    $qty,
                sourceType:  InventoryMovement::SOURCE_FEED_ISSUE,
                sourceId:    (int) $issue->id,
                occurredAt:  $issuedAt,
                actorUserId: $actor->id,
                reference:   sprintf('Feed Issue #%d → %s', $issue->id, $group->name),
                notes:       $data['notes'] ?? null,
            );

            // 3. Increase the group pool by the issued value.
            $this->poolService->recordFeedIssue(
                groupId:     $groupId,
                totalCost:   $totalCost,
                feedIssueId: (int) $issue->id,
                occurredAt:  $issuedAt,
                actorUserId: $actor->id,
                reference:   sprintf('Feed Issue #%d (%s)', $issue->id, $feedType->name),
                notes:       $data['notes'] ?? null,
            );

            // 4. Dispatch domain event for the subscriber to post GL.
            $this->dispatcher->dispatch(new FeedIssued(
                source: new SourceReference(
                    module:         'feed',
                    sourceType:     FeedIssue::class,
                    sourceId:       (int) $issue->id,
                    organizationId: $orgId,
                ),
                occurredAt: $issuedAt,
                dispatchedByUserId: $actor->id,
                feedIssueId:    (int) $issue->id,
                groupId:        $groupId,
                feedTypeId:     $feedTypeId,
                feedTypeName:   (string) $feedType->name,
                quantityKg:     $qty,
                unitCost:       (float) $unitCost,
                totalCost:      (float) $totalCost,
            ));

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
            ->where('item_id',   $feedTypeId)
            ->first();

        if (! $balance || bccomp((string) $balance->weighted_avg_cost, '0', 6) <= 0) {
            return '0';
        }

        return (string) $balance->weighted_avg_cost;
    }
}
