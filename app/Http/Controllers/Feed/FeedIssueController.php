<?php

namespace App\Http\Controllers\Feed;

use App\Http\Controllers\Controller;
use App\Http\Requests\Feed\StoreFeedIssueRequest;
use App\Models\FeedIssue;
use App\Models\FeedType;
use App\Models\Finance\InventoryBalance;
use App\Models\Group;
use App\Services\Feed\IssueFeedToGroupService;
use App\Support\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Phase 5 — feed issue management UI.
 *
 * Workflow:
 *   1. List recent issues with posting status.
 *   2. Create a new issue: pick group + feed type + qty.
 *   3. Service computes total_cost at current WAC, persists rows,
 *      dispatches FeedIssued domain event → GL posts on next outbox tick.
 *
 * Permission gating:
 *   - view_group_pools (read)
 *   - manage_feed_issues (create)
 */
class FeedIssueController extends Controller
{
    public function __construct(
        protected CurrentOrganization $currentOrganization,
        protected IssueFeedToGroupService $service,
    ) {
    }

    public function index(Request $request)
    {
        $orgId = $this->currentOrganization->id();

        $issues = FeedIssue::query()
            ->with(['group', 'feedType', 'issuedBy', 'journalBatch'])
            ->when(
                $orgId !== null,
                fn ($q) => $q->where('organization_id', $orgId),
                fn ($q) => $q->whereNull('organization_id'),
            )
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('feed.issues.index', [
            'issues' => $issues,
        ]);
    }

    public function create(Request $request)
    {
        $orgId = $this->currentOrganization->id();

        // Show finished feed types with available on-hand inventory.
        $availableFeedTypes = $this->availableFeedTypes($orgId);

        return view('feed.issues.create', [
            'groups'             => Group::query()->orderBy('name')->get(['id', 'name', 'type']),
            'feedTypes'          => $availableFeedTypes,
        ]);
    }

    public function store(StoreFeedIssueRequest $request)
    {
        $validated = $request->validated();

        try {
            $issue = $this->service->execute(
                actor: $request->user(),
                data: [
                    'group_id'     => (int) $validated['group_id'],
                    'feed_type_id' => (int) $validated['feed_type_id'],
                    'quantity_kg'  => (float) $validated['quantity_kg'],
                    'issued_at'    => $validated['issued_at'] ?? null,
                    'reference'    => $validated['reference'] ?? null,
                    'notes'        => $validated['notes']     ?? null,
                ],
            );

            return redirect()
                ->route('finance.feed.issues.show', $issue)
                ->with('success', sprintf(
                    'Feed issue #%d recorded — %s kg of %s to group %s.',
                    $issue->id,
                    number_format((float) $issue->quantity_kg, 2),
                    $issue->feedType?->name,
                    $issue->group?->name,
                ));
        } catch (\Throwable $e) {
            Log::error('FeedIssueController::store failed', [
                'error' => $e->getMessage(),
                'data'  => $validated,
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    public function show(FeedIssue $issue)
    {
        $this->ensureInOrg($issue);
        $issue->load(['group', 'feedType', 'issuedBy', 'journalBatch']);

        return view('feed.issues.show', [
            'issue' => $issue,
        ]);
    }

    // ============================================================
    // Helpers
    // ============================================================

    protected function ensureInOrg(FeedIssue $issue): void
    {
        $orgId = $this->currentOrganization->id();
        if ($issue->organization_id !== null && $issue->organization_id !== $orgId) {
            abort(404);
        }
    }

    /**
     * Feed types with at least some on-hand inventory.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    protected function availableFeedTypes(?int $orgId): \Illuminate\Support\Collection
    {
        $balances = InventoryBalance::query()
            ->where('item_kind', InventoryBalance::ITEM_KIND_FINISHED_FEED)
            ->when(
                $orgId !== null,
                fn ($q) => $q->where('organization_id', $orgId),
                fn ($q) => $q->whereNull('organization_id'),
            )
            ->where('on_hand_qty', '>', 0)
            ->get()
            ->keyBy('item_id');

        $feedTypeIds = $balances->keys()->all();
        $feedTypes = FeedType::query()->whereIn('id', $feedTypeIds)->orderBy('name')->get();

        return $feedTypes->map(function (FeedType $ft) use ($balances) {
            $bal = $balances->get($ft->id);
            return [
                'id'         => $ft->id,
                'name'       => $ft->name,
                'on_hand'    => (string) ($bal?->on_hand_qty ?? '0'),
                'wac'        => (string) ($bal?->weighted_avg_cost ?? '0'),
            ];
        });
    }
}
