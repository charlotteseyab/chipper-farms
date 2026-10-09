<?php

namespace App\Http\Controllers\Feed;

use App\Http\Controllers\Controller;
use App\Http\Requests\Feed\StoreFeedIssueRequest;
use App\Models\FeedIssue;
use App\Models\FeedType;
use App\Models\Finance\InventoryBalance;
use App\Services\Feed\FeedCorrectionService;
use App\Services\Feed\FeedPlanningService;
use App\Services\Feed\FeedStageResolver;
use App\Services\Feed\IssueFeedToGroupService;
use App\Support\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Phase 5 — feed issue management UI.
 *
 * Workflow:
 *   1. List recent issues with posting status.
 *   2. Record a feeding round: kg of feed given to each feeding stage.
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
        $orgId = (int) $this->currentOrganization->requireId();

        // Show finished feed types with available on-hand inventory. Cost
        // stays with finance users; farm staff only need quantities.
        $availableFeedTypes = $this->availableFeedTypes($orgId);
        if (! $request->user()->hasPermission('view_finance')) {
            $availableFeedTypes = $availableFeedTypes->map(fn (array $feed) => array_merge($feed, ['wac' => '0']));
        }

        // One whole-farm plan for today gives each stage's herd size, feed
        // and expected daily kg.
        $plan = app(FeedPlanningService::class)->calculate($orgId, now()->toDateString(), now()->toDateString(), applyWastage: false);
        $animalCounts = collect($plan['locations'])->countBy('stage');
        $rows = collect($plan['rows'])->keyBy('stage');

        $stages = collect(FeedStageResolver::STAGES)->map(fn (string $label, string $stage) => [
            'stage'         => $stage,
            'label'         => $label,
            'animal_count'  => (int) ($animalCounts[$stage] ?? 0),
            'feed_type_id'  => $rows[$stage]['feed_type_id'] ?? null,
            'feed_type'     => $rows[$stage]['feed_type'] ?? null,
            'rate'          => $rows[$stage]['rate'] ?? null,
            'expected_kg'   => isset($rows[$stage]) ? round((float) $rows[$stage]['daily_kg'], 2) : null,
            'submission_key' => (string) Str::uuid(),
        ])->sortByDesc('animal_count')->values();

        return view('feed.issues.create', [
            'stages'     => $stages,
            'feedTypes'  => $availableFeedTypes,
            'demandComplete' => $plan['demand_complete'],
        ]);
    }

    public function store(StoreFeedIssueRequest $request)
    {
        $validated = $request->validated();
        $entries = $request->filledEntries();
        $orgId = (int) $this->currentOrganization->requireId();

        // Check stock per feed before writing anything, so a round that
        // overdraws one feed is rejected with a clear message.
        $onHand = $this->availableFeedTypes($orgId)->keyBy('id');
        foreach (collect($entries)->groupBy('feed_type_id') as $feedTypeId => $rows) {
            $needed = (float) $rows->sum('quantity_kg');
            $available = (float) ($onHand[$feedTypeId]['on_hand'] ?? 0);
            if ($needed > $available) {
                $name = $onHand[$feedTypeId]['name'] ?? FeedType::find($feedTypeId)?->name ?? 'this feed';

                return back()->withInput()->with('error', sprintf(
                    'Not enough %s in stock: this round uses %s kg but only %s kg is available. Mix more or reduce the quantities.',
                    $name, number_format($needed, 2), number_format($available, 2),
                ));
            }
        }

        try {
            $issues = DB::transaction(fn () => collect($entries)->map(fn (array $entry) => $this->service->execute(
                actor: $request->user(),
                data: [
                    'submission_key' => $entry['submission_key'],
                    'feeding_stage'  => $entry['stage'],
                    'feed_type_id'   => $entry['feed_type_id'],
                    'quantity_kg'    => $entry['quantity_kg'],
                    'issued_at'      => $validated['issued_at'] ?? null,
                    'reference'      => $validated['reference'] ?? null,
                    'notes'          => $validated['notes'] ?? null,
                ],
            )), 3);
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('FeedIssueController::store failed', ['error' => $e->getMessage(), 'entries' => $entries]);

            return back()->withInput()->with('error', 'The feeding could not be recorded. Nothing was saved; please try again.');
        }

        return redirect()
            ->route('feed.issues.index')
            ->with('success', 'Feeding recorded: '.$issues->map(fn (FeedIssue $issue) => sprintf(
                '%s %s kg (%s)',
                $issue->recipient_label,
                rtrim(rtrim(number_format((float) $issue->quantity_kg, 2), '0'), '.'),
                $issue->feedType?->name,
            ))->implode(', ').'.');
    }

    public function reverse(Request $request, FeedIssue $issue, FeedCorrectionService $corrections)
    {
        $this->ensureInOrg($issue);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']]);

        try {
            $corrections->reverseIssue($request->user(), $issue, $data['reason']);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\App\Finance\Exceptions\PostingException $e) {
            return back()->with('error', 'The accounts could not record this reversal: '.$e->getMessage());
        }

        return back()->with('success', 'Feeding reversed. The feed is back in stock.');
    }

    public function show(FeedIssue $issue)
    {
        $this->ensureInOrg($issue);
        $issue->load(['group', 'feedType', 'issuedBy', 'journalBatch', 'reverser']);

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
        $feedTypes = FeedType::query()->where('is_active', true)->whereIn('id', $feedTypeIds)->orderBy('name')->get();

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
