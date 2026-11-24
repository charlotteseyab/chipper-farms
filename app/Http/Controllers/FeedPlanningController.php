<?php

namespace App\Http\Controllers;

use App\Models\FeedingStandard;
use App\Models\FeedType;
use App\Models\Group;
use App\Models\Organization;
use App\Models\PigManagement;
use App\Models\Room;
use App\Models\Setting;
use App\Models\Structure;
use App\Services\Feed\FeedConfigurationService;
use App\Services\Feed\FeedPlanningService;
use App\Services\Feed\FeedStageResolver;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FeedPlanningController extends Controller
{
    /** The old Plan and Projection pages are now one: Mix & buy. */
    public function plan(Request $request)
    {
        return redirect()->route('feed.mix-buy');
    }

    public function projection(Request $request)
    {
        return redirect()->route('feed.mix-buy', array_filter(['weeks' => $request->query('weeks')]));
    }

    /**
     * Mix & buy: everything the next weeks need, and the two things to do
     * about it. Per feed: needed, ready, to mix, and a Mix button that makes
     * the batch there and then. Per ingredient: needed, in the store, short,
     * and one button for a purchase request with every shortage on it.
     * Then the same weeks one at a time, and where the numbers come from.
     */
    public function mixBuy(Request $request, FeedPlanningService $service)
    {
        $organizationId = app(CurrentOrganization::class)->requireId();
        [$period, $start, $end, $periodLabel] = $this->mixBuyPeriod($request);
        $projection = $service->breakdown($organizationId, $start, $end);
        $whole = $projection['whole'];

        // How much of each feed the store's ingredients would allow mixing now.
        $mixable = [];
        foreach ($whole['feeds'] as $feed) {
            $limit = null;
            foreach ($feed['recipe_snapshot']['ingredients'] ?? [] as $ingredient) {
                $share = (float) $ingredient['quantity_per_100kg'] / 100;
                if ($share > 0) {
                    $kg = $service->availableKg($organizationId, (int) $ingredient['raw_material_id']) / $share;
                    $limit = $limit === null ? $kg : min($limit, $kg);
                }
            }
            $mixable[$feed['feed_type_id']] = $limit === null ? null : floor($limit);
        }

        return view('feeds.mix-buy.index', $projection + [
            'period' => $period, 'periodLabel' => $periodLabel,
            'range' => ['from' => $start, 'to' => $end],
            // What a Mix form sends back, so the page reopens on the same period.
            'periodQuery' => array_filter(['period' => $period, 'from' => $period === 'custom' ? $start->toDateString() : null, 'to' => $period === 'custom' ? $end->toDateString() : null]),
            'mixable' => $mixable,
            'materials' => collect($whole['materials'])->filter(fn ($m) => $m['required_kg'] > 0 || $m['shortfall_kg'] > 0)->values(),
            'recentMixes' => \App\Models\FeedBatch::with('feedType')->latest('batch_date')->latest('id')->limit(5)->get(),
            'stageLabels' => FeedStageResolver::STAGES,
        ]);
    }

    /**
     * The period Mix & buy covers: today, 1–8 weeks, the rest of this month,
     * or chosen dates. Old links with ?weeks=N still work.
     *
     * @return array{0: string, 1: CarbonImmutable, 2: CarbonImmutable, 3: string}
     */
    private function mixBuyPeriod(Request $request): array
    {
        $today = CarbonImmutable::today();
        $period = (string) $request->query('period', in_array((int) $request->query('weeks'), [1, 2, 4, 8], true) ? $request->query('weeks').'w' : '2w');

        if ($period === 'custom') {
            $dates = $request->validate([
                'from' => ['required', 'date_format:Y-m-d'],
                'to'   => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            ]);
            $from = CarbonImmutable::parse($dates['from']);
            $to = CarbonImmutable::parse($dates['to']);
            if ($from->diffInDays($to) > 365) {
                throw \Illuminate\Validation\ValidationException::withMessages(['to' => 'Choose a period of a year or less.']);
            }

            return ['custom', $from, $to, 'from '.$from->format('j M').' to '.$to->format('j M Y')];
        }

        return match ($period) {
            'today' => ['today', $today, $today, 'today'],
            'month' => ['month', $today, $today->endOfMonth()->startOfDay(), 'the rest of this month (to '.$today->endOfMonth()->format('j M').')'],
            '1w'    => ['1w', $today, $today->addDays(6), 'the next week'],
            '4w'    => ['4w', $today, $today->addDays(27), 'the next 4 weeks'],
            '8w'    => ['8w', $today, $today->addDays(55), 'the next 8 weeks'],
            default => ['2w', $today, $today->addDays(13), 'the next 2 weeks'],
        };
    }

    /** Feed Today → Planned vs actual. */
    public function consumption(Request $request, FeedPlanningService $service)
    {
        return $this->index($request->merge(['tab' => 'consumption']), $service);
    }

    /** Ration history and settings now live on the one Feed Setup page. */
    public function settings()
    {
        return redirect()->to(route('feed-setup.index', ['settings' => 1]).'#settings');
    }

    public function index(Request $request, FeedPlanningService $service)
    {
        $organizationId = app(CurrentOrganization::class)->requireId();
        $input = $request->validate([
            'tab' => ['nullable', Rule::in(['plan', 'consumption', 'setup'])],
            'period' => ['nullable', Rule::in(['today', 'weekly', 'bi-weekly', 'monthly', 'custom'])],
            'from' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d'], 'to' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'stage' => ['nullable', Rule::in(array_keys(FeedStageResolver::STAGES))],
            'feed_type_id' => ['nullable', 'integer', Rule::exists('feed_types', 'id')->where('organization_id', $organizationId)],
            'group_id' => ['nullable', 'integer', Rule::exists('groups', 'id')->where('organization_id', $organizationId)],
            'room_id' => ['nullable', 'integer', Rule::exists('rooms', 'id')->where('organization_id', $organizationId)],
            'structure_id' => ['nullable', 'integer', Rule::exists('structures', 'id')->where('organization_id', $organizationId)],
        ]);
        $tab = $input['tab'] ?? 'plan';
        $period = $input['period'] ?? ($tab === 'consumption' ? 'monthly' : 'weekly');
        $today = CarbonImmutable::today();
        [$from, $to] = match ($period) {
            'today' => [$today, $today],
            'monthly' => [$today->startOfMonth(), $today->endOfMonth()],
            'bi-weekly' => $tab === 'consumption' ? [$today->subDays(13), $today] : [$today, $today->addDays(13)],
            'custom' => [CarbonImmutable::parse($input['from'] ?? $today), CarbonImmutable::parse($input['to'] ?? $today)],
            default => $tab === 'consumption' ? [$today->subDays(6), $today] : [$today, $today->addDays(6)],
        };
        $plan = $service->calculate($organizationId, $from->toDateString(), $to->toDateString(), $input);
        // A month of feedings is a long list: 25 rows a page, newest day first.
        $consumptionRows = collect($service->consumption($organizationId, $plan['from'], $plan['to'], $input))->sortByDesc('date')->values();
        $consumption = \App\Support\CollectionPage::make($consumptionRows, 25);
        // Totals for the whole period, not just the page shown.
        $glance = $tab === 'consumption' ? app(\App\Services\Feed\FeedGlanceService::class)->variance($consumptionRows) : [];
        $feedTypes = FeedType::where('is_active', true)->orderBy('name')->get();
        $standards = $tab === 'setup' ? FeedingStandard::with('feedType')->orderBy('stage')->orderByDesc('effective_from')->get() : collect();
        $stages = FeedStageResolver::STAGES;
        $groups = Group::animalGroups()->orderBy('name')->get();
        // Room names repeat across buildings ("sty 1" in two), so show the building too.
        $rooms = Room::with('structure')->get()
            ->each(fn (Room $room) => $room->setAttribute('name', trim(($room->structure?->name ? $room->structure->name.' · ' : '').$room->name)))
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();
        $structures = Structure::orderBy('name')->get();
        $animals = $tab === 'setup' ? PigManagement::active()->orderBy('code')->get() : collect();
        // Recipe history lives with the other setup history (it used to sit under Mill → Mix & batches).
        $recipeVersions = $tab === 'setup' ? \App\Models\FeedRecipeVersion::with('feedType')->orderByDesc('effective_from')->orderByDesc('id')->get() : collect();

        return view('feeds.planning-reports.index', compact('tab', 'period', 'plan', 'consumption', 'recipeVersions', 'glance',
            'standards', 'stages', 'feedTypes', 'groups', 'rooms', 'structures', 'animals'));
    }

    public function alerts(Request $request, FeedConfigurationService $service)
    {
        $data = $request->validate([
            'variance_percent' => ['nullable', 'numeric', 'gt:0', 'max:10000'],
            'cover_days'       => ['nullable', 'numeric', 'gt:0', 'max:366'],
            'cover_urgent_days' => ['nullable', 'numeric', 'gt:0', 'max:366'],
            'feeding_due_by'   => ['nullable', 'date_format:H:i'],
            'wastage_percent'  => ['nullable', 'numeric', 'min:0', 'max:50'],
        ]);
        // Only when both are entered: a short "running low" on its own just makes everything under it urgent.
        if (isset($data['cover_days'], $data['cover_urgent_days']) && (float) $data['cover_urgent_days'] >= (float) $data['cover_days']) {
            return back()->withInput()->withErrors(['cover_urgent_days' => 'The "mix now" days must be fewer than the "running low" days.']);
        }
        DB::transaction(function () use ($request, $data, $service) {
            Organization::whereKey($request->user()->organization_id)->lockForUpdate()->firstOrFail();
            $old = ['variance_percent' => Setting::get('feed.alerts.variance_percent'), 'cover_days' => Setting::get('feed.alerts.cover_days'),
                'cover_urgent_days' => Setting::get('feed.alerts.cover_urgent_days'), 'feeding_due_by' => Setting::get('feed.alerts.feeding_due_by'),
                'wastage_percent' => Setting::get(FeedPlanningService::WASTAGE_SETTING)];
            foreach (['variance_percent', 'cover_days', 'cover_urgent_days', 'feeding_due_by'] as $key) {
                Setting::set('feed.alerts.'.$key, $data[$key] ?? '');
            }
            Setting::set(FeedPlanningService::WASTAGE_SETTING, $data['wastage_percent'] ?? '');
            $service->audit($request->user(), 'feed_alert_thresholds_updated', $request->user()->organization, $old, $data, 'Feed planning settings saved. A blank reminder setting uses its default; a blank variance alert is off.');
        });

        return back()->with('success', 'Feed planning settings saved.');
    }

    public function standard(Request $request, FeedConfigurationService $service)
    {
        $service->standard($request->user(), $request->all());

        return back()->with('success', 'Feeding standard version saved. Earlier versions remain unchanged.');
    }

    public function override(Request $request, FeedConfigurationService $service)
    {
        $data = $request->validate(['pig_id' => ['required', 'integer'], 'stage' => ['nullable', 'string'], 'reason' => ['required', 'string']]);
        $pig = PigManagement::where('organization_id', $request->user()->organization_id)->findOrFail($data['pig_id']);
        $service->override($request->user(), $pig, $data['stage'] ?? null, $data['reason']);

        return back()->with('success', 'Stage override recorded with its reason.');
    }
}
