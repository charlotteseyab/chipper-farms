<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Mating;
use App\Models\PigManagement;
use App\Models\PigWeight;
use App\Models\PurchaseStockIntake;
use Carbon\Carbon;

class DataEntryController extends Controller
{
    private const MAX_DAILY_WEIGHT_RANGE_DAYS = 92;

    /**
     * The data entry dashboard: today's work first. A to-do list of the day's
     * jobs (each only if the person can open it), quick entry, one list of
     * records with missing details, and the latest entries.
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $orgId = app(\App\Support\CurrentOrganization::class)->id();
        // Every panel asks what its destination page asks, so revoking a
        // permission removes the data here as well as the page.
        $access = \App\Services\Access\DashboardAccess::for($user);
        $can = fn (string $route) => $access->canOpen($route);
        $now = \Carbon\CarbonImmutable::now();

        // ---- Today: the day's jobs --------------------------------------
        $tasks = collect();

        if ($orgId && $can('feed.issues.create')) {
            $stages = app(\App\Services\Feed\FeedTodayService::class)->stagesToday($orgId)->where('animals', '>', 0);
            $notFed = $stages->where('status', 'not_fed');
            $tasks->push([
                'key' => 'feed', 'title' => 'Feed the animals',
                'count' => $notFed->count(),
                'done_detail' => $stages->isEmpty() ? 'No animals to feed.' : 'Every stage has been fed.',
                'detail' => 'Not fed yet: ' . $notFed->pluck('label')->implode(', '),
                'progress' => $stages->isEmpty() ? null : ($stages->count() - $notFed->count()) . ' of ' . $stages->count() . ' stages fed',
                'action' => 'Record feeding', 'route' => route('feed.issues.create'),
            ]);
        }

        if ($can('health.worklist')) {
            $health = app(\App\Services\Dashboard\DashboardHealthMetrics::class)->summary($now)['tasks'];
            $tasks->push([
                'key' => 'health', 'title' => 'Health tasks',
                'count' => $health['overdue'] + $health['today'],
                'done_detail' => 'Nothing due today.' . ($health['upcoming'] ? ' Coming up: ' . $health['upcoming_detail'] . '.' : ''),
                'detail' => trim(($health['overdue'] ? 'Overdue: ' . $health['overdue_detail'] . '. ' : '') . ($health['today'] ? 'Today: ' . $health['today_detail'] . '.' : '')),
                'progress' => null,
                'action' => 'Open the worklist', 'route' => route('health.worklist'),
            ]);
        }

        if ($can('weights.index')) {
            $overdueWeighing = PigManagement::countOverdueForWeighing();
            $dueWeighing = PigManagement::countDueForWeighing();
            $tasks->push([
                'key' => 'weights', 'title' => 'Weigh pigs',
                'count' => $overdueWeighing,
                'done_detail' => 'No pig is overdue.' . ($dueWeighing ? " {$dueWeighing} due soon." : ''),
                'detail' => $overdueWeighing . ' ' . str('pig')->plural($overdueWeighing) . ' past their weighing date' . ($dueWeighing ? ", {$dueWeighing} more due soon." : '.'),
                'progress' => null,
                'action' => 'Record weights', 'route' => route('weights.index'),
            ]);
        }

        if ($can('mating.manage')) {
            $pregnancyChecks = Mating::query()->pregnancyFollowUpDue()->count();
            $pregnancyOverdue = Mating::query()->pregnancyFollowUpOverdue()->count();
            $tasks->push([
                'key' => 'pregnancy', 'title' => 'Pregnancy checks',
                'count' => $pregnancyChecks,
                'done_detail' => 'No sow needs a check.',
                'detail' => $pregnancyChecks . ' ' . str('sow')->plural($pregnancyChecks) . ' to check again' . ($pregnancyOverdue ? " ({$pregnancyOverdue} overdue)." : '.'),
                'progress' => null,
                'action' => 'Check sows', 'route' => route('mating.manage'),
            ]);
        }

        if ($can('purchase-stock-intakes.index')) {
            $toReceive = PurchaseStockIntake::where('status', PurchaseStockIntake::STATUS_PENDING_RECEIVE)->count();
            $toHouse = PurchaseStockIntake::where('status', PurchaseStockIntake::STATUS_RECEIVED)->count();
            $tasks->push([
                'key' => 'purchases', 'title' => 'Bought animals arriving',
                'count' => $toReceive + $toHouse,
                'done_detail' => 'No purchase waiting.',
                'detail' => trim(($toReceive ? "{$toReceive} to receive. " : '') . ($toHouse ? "{$toHouse} to put in quarantine." : '')),
                'progress' => null,
                'action' => 'Open purchases', 'route' => route('purchase-stock-intakes.index'),
            ]);
        }

        // ---- Quick entry -------------------------------------------------
        $quickEntry = collect([
            ['Record feeding', 'feed.issues.create', 'feed'],
            ['Give a dose', 'health.create-medication', 'dose'],
            ['Record weight', 'weights.create', 'weight'],
            ['New health case', 'health.create', 'case'],
            ['Record mating', 'mating.index', 'mating'],
            ['Add a pig', 'all-stocks.create', 'pig'],
            ['Import stock', 'all-stocks.import.create', 'import'],
            ['Purchased stock', 'purchase-stock-intakes.create', 'purchase'],
        ])->filter(fn ($item) => \Illuminate\Support\Facades\Route::has($item[1]) && $can($item[1]))
            ->map(fn ($item) => ['label' => $item[0], 'route' => route($item[1]), 'icon' => $item[2]])->values();

        // ---- Missing details: one list -----------------------------------
        $active = fn () => PigManagement::active();
        $blank = fn (string $column) => fn ($q) => $q->whereNull($column)->orWhere($column, '');
        $allStock = route('all-stocks.index');
        $missing = ! $access->sees('pigs') ? collect() : collect([
            ['label' => 'No first weight', 'count' => $active()->whereDoesntHave('weights')->count(), 'where' => 'Record a weight', 'route' => $can('weights.create') ? route('weights.create') : null],
            ['label' => 'No room', 'count' => $active()->whereNull('room_id')->count(), 'where' => 'All Stock, edit the pig', 'route' => $allStock],
            ['label' => 'No breed', 'count' => $active()->where($blank('breed'))->count(), 'where' => 'All Stock, edit the pig', 'route' => $allStock],
            ['label' => 'No sex', 'count' => $active()->where($blank('gender'))->count(), 'where' => 'All Stock, edit the pig', 'route' => $allStock],
            ['label' => 'No date of birth', 'count' => $active()->whereNull('date_of_birth')->count(), 'where' => 'All Stock, edit the pig', 'route' => $allStock],
            ['label' => 'No stock type', 'count' => $active()->where($blank('stock_type'))->count(), 'where' => 'All Stock, edit the pig', 'route' => $allStock],
        ])->filter(fn ($row) => $row['count'] > 0)->values();
        $completeness = null;
        if ($access->sees('pigs')) {
            $herd = max(1, PigManagement::active()->count());
            $incomplete = PigManagement::active()->where(fn ($q) => $q->whereNull('room_id')->orWhereDoesntHave('weights')
                ->orWhere($blank('breed'))->orWhere($blank('gender'))->orWhereNull('date_of_birth')->orWhere($blank('stock_type')))->count();
            $completeness = (int) round((($herd - $incomplete) / $herd) * 100);
        }

        // ---- Latest entries ----------------------------------------------
        $pigLink = fn ($pig) => $pig && $access->sees('pigs') ? route('pigs.show', $pig->id) : null;
        $entries = collect()
            ->merge(! $access->sees('weights') ? [] : PigWeight::with('pig')->latest()->limit(8)->get()->map(fn ($w) => [
                'type' => 'Weight', 'what' => ($w->pig?->code ?? 'Pig') . ' · ' . rtrim(rtrim(number_format((float) $w->weight, 2), '0'), '.') . ' kg',
                'time' => $w->created_at, 'route' => $pigLink($w->pig) ?? route('weights.index')]))
            ->merge(! $can('feed.issues.index') ? [] : \App\Models\FeedIssue::with('feedType')->latest()->limit(8)->get()->map(fn ($f) => [
                'type' => 'Feeding', 'what' => (\App\Services\Feed\FeedStageResolver::STAGES[$f->feeding_stage] ?? 'Feed') . ' · ' . rtrim(rtrim(number_format((float) $f->quantity_kg, 2), '0'), '.') . ' kg ' . ($f->feedType?->name ?? ''),
                'time' => $f->created_at, 'route' => route('feed.issues.index')]))
            ->merge(! $access->sees('health') ? [] : \App\Models\MedicationAdministration::with('pig')->whereNull('voided_at')->latest()->limit(8)->get()->map(fn ($m) => [
                'type' => 'Dose', 'what' => ($m->pig?->code ?? 'Pig') . ' · ' . $m->medication_name,
                'time' => $m->created_at, 'route' => route('medications.show', $m->id)]))
            ->merge(! $access->sees('pigs') ? [] : PigManagement::latest()->limit(8)->get()->map(fn ($p) => [
                'type' => 'New pig', 'what' => $p->code . ($p->ear_tag_number ? ' · tag ' . $p->ear_tag_number : ''),
                'time' => $p->created_at, 'route' => route('pigs.show', $p->id)]))
            ->merge(! $access->sees('breeding') ? [] : Mating::with('female')->latest()->limit(8)->get()->map(fn ($m) => [
                'type' => 'Mating', 'what' => 'Sow ' . ($m->female?->code ?? '?'),
                'time' => $m->created_at, 'route' => route('mating.index')]))
            ->filter(fn ($e) => $e['time'] !== null)
            ->sortByDesc('time')->take(8)->values();
        $weightsToday = $access->sees('weights') ? PigWeight::whereDate('created_at', today())->count() : 0;
        $feedingsToday = $can('feed.issues.index') ? \App\Models\FeedIssue::whereDate('created_at', today())->count() : 0;
        $dosesToday = $access->sees('health') ? \App\Models\MedicationAdministration::whereDate('created_at', today())->count() : 0;
        $entriesToday = $weightsToday + $feedingsToday + $dosesToday
            + ($access->sees('pigs') ? PigManagement::whereDate('created_at', today())->count() : 0)
            + ($access->sees('breeding') ? Mating::whereDate('created_at', today())->count() : 0);
        $todayParts = collect([
            $access->sees('weights') ? "{$weightsToday} " . str('weight')->plural($weightsToday) : null,
            $can('feed.issues.index') ? "{$feedingsToday} " . str('feeding')->plural($feedingsToday) : null,
            $access->sees('health') ? "{$dosesToday} " . str('dose')->plural($dosesToday) : null,
        ])->filter()->implode(' · ');

        // ---- Stat cards --------------------------------------------------
        // Built before the cards are filtered, so a hidden card still needs a value.
        $overdueWeighing ??= $access->sees('weights') ? PigManagement::countOverdueForWeighing() : 0;
        $dueWeighing ??= $access->sees('weights') ? PigManagement::countDueForWeighing() : 0;
        $purchased = $access->sees('intakes')
            ? app(\App\Services\Stock\PurchasedStockDashboardMetrics::class)->summary()
            : ['pending_intakes' => 0, 'recent_intakes' => 0, 'approved_waiting_stock' => 0];
        $stats = collect([
            ['label' => 'Entries today', 'value' => $entriesToday, 'tone' => 'emerald', 'route' => 'weights.index',
                'sub' => $todayParts],
            ['label' => 'Weights recorded', 'value' => $weightsToday, 'tone' => 'sky', 'route' => 'weights.create',
                'sub' => PigWeight::where('created_at', '>=', now()->startOfWeek())->count() . ' this week'],
            ['label' => 'Pending weighing', 'value' => $overdueWeighing, 'tone' => 'amber', 'route' => 'weights.index',
                'sub' => "overdue · {$dueWeighing} due soon"],
            ['label' => 'Missing details', 'value' => $missing->sum('count'), 'tone' => 'rose', 'route' => 'all-stocks.index',
                'sub' => "{$completeness}% of pig records complete"],
            ['label' => 'Purchased intake', 'value' => $purchased['pending_intakes'], 'tone' => 'violet', 'route' => 'purchase-stock-intakes.index',
                'sub' => $purchased['recent_intakes'] . ' new this week · ' . $purchased['approved_waiting_stock'] . ' ready for stock'],
        ])->filter(fn ($card) => $can($card['route']))
            ->map(fn ($card) => ['url' => route($card['route'])] + $card)->values();

        return view('dataentry.dashboard', compact('stats', 'tasks', 'quickEntry', 'missing', 'completeness', 'entries', 'entriesToday'));
    }

    public function getDailyWeightsData(Request $request)
    {
        // The route admits by role; the data is weights, so it needs the same
        // permission as the Weights page.
        $this->abortUnlessPermission('view_weights');

        $validated = $request->validate([
            'start_date' => ['nullable', 'date', 'required_with:end_date'],
            'end_date' => ['nullable', 'date', 'required_with:start_date', 'after_or_equal:start_date'],
        ]);

        // Default to the last 7 days.
        $end = Carbon::parse($validated['end_date'] ?? now())->startOfDay();
        $start = Carbon::parse($validated['start_date'] ?? $end->copy()->subDays(6))->startOfDay();

        // Bounded so one request cannot ask for years of daily buckets.
        abort_if($start->diffInDays($end) > self::MAX_DAILY_WEIGHT_RANGE_DAYS, 422,
            'Choose a range of at most '.self::MAX_DAILY_WEIGHT_RANGE_DAYS.' days.');

        // One grouped query for the range rather than one per day.
        $counts = PigWeight::query()
            ->whereBetween('created_at', [$start, $end->copy()->endOfDay()])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $dates = [];
        $weightsData = [];
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $dates[] = $day->format('M j');
            $weightsData[] = (int) ($counts[$day->toDateString()] ?? 0);
        }

        return response()->json([
            'labels' => $dates,
            'data' => $weightsData,
        ]);
    }
}
