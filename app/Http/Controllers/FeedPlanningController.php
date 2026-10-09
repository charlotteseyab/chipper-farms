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
    /** Mill → Plan. */
    public function plan(Request $request, FeedPlanningService $service)
    {
        return $this->index($request->merge(['tab' => 'plan']), $service);
    }

    /** Feed Today → Planned vs actual. */
    public function consumption(Request $request, FeedPlanningService $service)
    {
        return $this->index($request->merge(['tab' => 'consumption']), $service);
    }

    /** Feed Setup → Ration history & settings. */
    public function settings(Request $request, FeedPlanningService $service)
    {
        return $this->index($request->merge(['tab' => 'setup']), $service);
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
        $consumption = $service->consumption($organizationId, $plan['from'], $plan['to'], $input);
        $feedTypes = FeedType::where('is_active', true)->orderBy('name')->get();
        $standards = $tab === 'setup' ? FeedingStandard::with('feedType')->orderBy('stage')->orderByDesc('effective_from')->get() : collect();
        $stages = FeedStageResolver::STAGES;
        $groups = Group::animalGroups()->orderBy('name')->get();
        $rooms = Room::orderBy('name')->get();
        $structures = Structure::orderBy('name')->get();
        $animals = $tab === 'setup' ? PigManagement::active()->orderBy('code')->get() : collect();

        return view('feeds.planning-reports.index', compact('tab', 'period', 'plan', 'consumption',
            'standards', 'stages', 'feedTypes', 'groups', 'rooms', 'structures', 'animals'));
    }

    public function alerts(Request $request, FeedConfigurationService $service)
    {
        $data = $request->validate([
            'variance_percent' => ['nullable', 'numeric', 'gt:0', 'max:10000'],
            'cover_days'       => ['nullable', 'numeric', 'gt:0', 'max:366'],
            'wastage_percent'  => ['nullable', 'numeric', 'min:0', 'max:50'],
        ]);
        DB::transaction(function () use ($request, $data, $service) {
            Organization::whereKey($request->user()->organization_id)->lockForUpdate()->firstOrFail();
            $old = ['variance_percent' => Setting::get('feed.alerts.variance_percent'), 'cover_days' => Setting::get('feed.alerts.cover_days'),
                'wastage_percent' => Setting::get(FeedPlanningService::WASTAGE_SETTING)];
            foreach (['variance_percent', 'cover_days'] as $key) {
                Setting::set('feed.alerts.'.$key, $data[$key] ?? '');
            }
            Setting::set(FeedPlanningService::WASTAGE_SETTING, $data['wastage_percent'] ?? '');
            $service->audit($request->user(), 'feed_alert_thresholds_updated', $request->user()->organization, $old, $data, 'Feed planning settings saved. Blank disables that setting.');
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
