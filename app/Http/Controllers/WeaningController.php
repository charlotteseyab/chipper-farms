<?php

namespace App\Http\Controllers;

use App\Domain\Events\Lifecycle\LitterWeaned;
use App\Domain\Events\Lifecycle\PigWeaned;
use App\Domain\Events\Support\SourceReference;
use App\Models\Farrowing;
use App\Models\PigManagement;
use App\Models\Weaning;
use App\Services\DomainEvents\DispatchDomainEvent;
use App\Support\CurrentOrganization;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Weaning dashboard + per-status views + weaning write paths.
 *
 * Extracted from PigManagementController — lines 1422 (dashboard), 1550
 * (weanLitter), 1597 (inProgress), 1666 (weaned), 1697 (overdue), 1826
 * (recordWeaning), 1900 (completeMaturity), 1946 (updateWeaningStatus).
 *
 * The getSowsWithLitterStatusQuery helper is preserved as a protected
 * method — it's only used by weaningOverdue and doesn't justify a service.
 *
 * Behaviour preserved exactly; only location has changed.
 */
class WeaningController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected DispatchDomainEvent $dispatcher,
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    public function dashboard()
    {
        $this->authorize('viewAny', Weaning::class);
        $weaningAge  = config('breeding.cycle.birth_to_weaning_days', 45);
        $maturityAge = config('breeding.cycle.birth_to_maturity_days', 180);

        // READY NOW — litters >= weaningAge, not yet weaned, with surviving piglets
        $rawReadyNow = Farrowing::with(['sow', 'pigletDeaths'])
            ->where('status', 'active')
            ->whereRaw('DATEDIFF(NOW(), farrowing_date) >= ?', [$weaningAge])
            ->whereDoesntHave('weanings')
            ->orderByRaw('DATEDIFF(NOW(), farrowing_date) DESC')
            ->get();

        $filteredReady = $rawReadyNow->filter(fn ($l) => max(0, ($l->live_births ?? 0) - $l->pigletDeaths->sum('count')) > 0)->values();

        $readyPage = request()->input('ready_page', 1);
        $perPage   = 15;

        $readyNowLitters = new LengthAwarePaginator(
            $filteredReady->forPage($readyPage, $perPage),
            $filteredReady->count(),
            $perPage,
            $readyPage,
            ['pageName' => 'ready_page']
        );
        $readyNowLitters->getCollection()->transform(function ($litter) use ($weaningAge) {
            $currentAge = (int) $litter->farrowing_date->diffInDays(now());
            $litter->current_age        = $currentAge;
            $litter->days_overdue       = max(0, $currentAge - $weaningAge);
            $litter->is_overdue         = $currentAge >= ($weaningAge + 7);
            $litter->is_critical        = $currentAge >= ($weaningAge + 14);
            $litter->surviving_piglets  = max(0, ($litter->live_births ?? 0) - $litter->pigletDeaths->sum('count'));
            return $litter;
        });

        // DUE SOON — litters 38-44 days old
        $rawDueSoon = Farrowing::with(['sow', 'pigletDeaths'])
            ->where('status', 'active')
            ->whereRaw('DATEDIFF(NOW(), farrowing_date) BETWEEN ? AND ?', [$weaningAge - 7, $weaningAge - 1])
            ->whereDoesntHave('weanings')
            ->orderBy('farrowing_date', 'asc')
            ->get();

        $filteredSoon = $rawDueSoon->filter(fn ($l) => max(0, ($l->live_births ?? 0) - $l->pigletDeaths->sum('count')) > 0)->values();

        $soonPage = request()->input('soon_page', 1);
        $dueSoonLitters = new LengthAwarePaginator(
            $filteredSoon->forPage($soonPage, $perPage),
            $filteredSoon->count(),
            $perPage,
            $soonPage,
            ['pageName' => 'soon_page']
        );
        $dueSoonLitters->getCollection()->transform(function ($litter) use ($weaningAge) {
            $currentAge = (int) $litter->farrowing_date->diffInDays(now());
            $litter->current_age         = $currentAge;
            $litter->days_until_weaning  = max(0, $weaningAge - $currentAge);
            $litter->surviving_piglets   = max(0, ($litter->live_births ?? 0) - $litter->pigletDeaths->sum('count'));
            return $litter;
        });

        $inProgress = PigManagement::active()->whereNotNull('date_of_birth')
            ->whereHas('weaning', fn ($q) => $q->where('maturity_completed', false))
            ->with(['mother', 'father', 'weaning', 'weights' => fn ($q) => $q->latest('weigh_date')])
            ->orderBy('date_of_birth', 'asc')
            ->paginate(15, ['*'], 'progress_page');

        $completed = Weaning::with(['pig', 'mother', 'recordedBy', 'completedBy'])
            ->where('maturity_completed', true)
            ->orderBy('maturity_completion_date', 'desc')
            ->paginate(15, ['*'], 'completed_page');

        $inProgress->getCollection()->transform(function ($pig) use ($maturityAge) {
            if ($pig->weaning) {
                $weaningDate = $pig->weaning->weaning_date instanceof Carbon
                    ? $pig->weaning->weaning_date
                    : Carbon::parse($pig->weaning->weaning_date);
                $pig->days_since_weaning = (int) $weaningDate->diffInDays(now());

                $dob = $pig->date_of_birth instanceof Carbon ? $pig->date_of_birth : Carbon::parse($pig->date_of_birth);
                $daysPastWeaning     = $dob->diffInDays(now()) - 45;
                $daysTotalToMaturity = $maturityAge - 45;
                $pig->maturity_progress = min(100, max(0, ($daysPastWeaning / $daysTotalToMaturity) * 100));
            }
            return $pig;
        });

        $totalReadyPiglets = $filteredReady->sum(fn ($l) => max(0, ($l->live_births ?? 0) - $l->pigletDeaths->sum('count')));
        $totalDueSoonPiglets = $filteredSoon->sum(fn ($l) => max(0, ($l->live_births ?? 0) - $l->pigletDeaths->sum('count')));
        $overdueCount = $filteredReady->filter(fn ($l) => $l->farrowing_date->diffInDays(now()) >= ($weaningAge + 7))->count();
        $criticalOverdueCount = $filteredReady->filter(fn ($l) => $l->farrowing_date->diffInDays(now()) >= ($weaningAge + 14))->count();

        $stats = [
            'ready_now'            => $filteredReady->count(),
            'ready_now_total'      => $totalReadyPiglets,
            'due_soon'             => $filteredSoon->count(),
            'due_soon_total'       => $totalDueSoonPiglets,
            'in_progress'          => $inProgress->total(),
            'completed_total'      => Weaning::where('maturity_completed', true)->count(),
            'completed_this_month' => Weaning::where('maturity_completed', true)
                ->whereMonth('maturity_completion_date', now()->month)
                ->whereYear('maturity_completion_date', now()->year)
                ->count(),
            'overdue_count'        => $overdueCount,
            'critical_overdue'     => $criticalOverdueCount,
        ];

        return view('weaning.dashboard', compact(
            'readyNowLitters', 'dueSoonLitters', 'inProgress', 'completed', 'stats', 'weaningAge', 'maturityAge'
        ));
    }

    public function weanLitter(Request $request, Farrowing $farrowing)
    {
        $this->authorize('create', Farrowing::class);
        $data = $request->validate([
            'weaning_date' => ['required', 'date', 'before_or_equal:today'],
            'notes'        => ['nullable', 'string', 'max:1000'],
        ]);

        if ($farrowing->status !== 'active') {
            return back()->withErrors(['weaning' => 'This litter is not active or may have already been processed for weaning.']);
        }

        $weaningAge   = (int) config('breeding.cycle.birth_to_weaning_days', 45);
        $farrowingDate = $farrowing->farrowing_date;

        if ($farrowingDate) {
            $weaningDate         = Carbon::parse($data['weaning_date'])->endOfDay();
            $litterAgeAtWeaning  = (int) $farrowingDate->copy()->startOfDay()->diffInDays($weaningDate);
            if ($litterAgeAtWeaning < $weaningAge) {
                return back()->withErrors(['weaning' => "Litter is too young for weaning. Minimum age is {$weaningAge} days."]);
            }
        }

        $existingNotes = trim((string) $farrowing->notes);
        $litterNote    = 'Litter weaned on ' . $data['weaning_date'];
        if (! empty($data['notes'])) {
            $litterNote .= ' - ' . $data['notes'];
        }

        DB::transaction(function () use ($farrowing, $data, $existingNotes, $litterNote) {
            $farrowing->status       = 'completed';
            $farrowing->date_weaned  = $data['weaning_date'];
            $farrowing->notes        = trim($existingNotes . (empty($existingNotes) ? '' : PHP_EOL) . $litterNote);
            $farrowing->save();

            $organizationId = $this->currentOrganization->id();
            if ($organizationId !== null) {
                $farrowing->loadMissing(['sow', 'pigletDeaths']);
                $surviving = max(0, (int) ($farrowing->live_births ?? 0) - (int) $farrowing->pigletDeaths->sum('count'));

                $this->dispatcher->dispatch(new LitterWeaned(
                    source: new SourceReference(
                        module: 'lifecycle',
                        sourceType: Farrowing::class,
                        sourceId: (int) $farrowing->id,
                        organizationId: $organizationId,
                    ),
                    occurredAt: CarbonImmutable::parse($data['weaning_date']),
                    dispatchedByUserId: auth()->id(),
                    farrowingId: (int) $farrowing->id,
                    sowId: (int) $farrowing->sow_id,
                    groupId: $farrowing->sow?->group_id ? (int) $farrowing->sow->group_id : null,
                    survivingPiglets: $surviving,
                    averageBirthWeightKg: $farrowing->average_birth_weight !== null
                        ? (float) $farrowing->average_birth_weight
                        : null,
                ));
            }
        });

        return back()->with('success', 'Litter weaning recorded for sow ' . optional($farrowing->sow)->ear_tag_number . '.');
    }

    public function inProgress()
    {
        $this->authorize('viewAny', PigManagement::class);
        $maturityAge = config('breeding.cycle.birth_to_maturity_days', 180);

        $inProgressPigs = PigManagement::active()->whereNotNull('date_of_birth')
            ->whereHas('weaning', fn ($q) => $q->where('maturity_completed', false))
            ->with(['mother', 'father', 'weaning'])
            ->orderBy('date_of_birth', 'asc')
            ->paginate(10);

        $inProgressPigs->getCollection()->transform(function ($pig) use ($maturityAge) {
            if ($pig->weaning) {
                $weaningDate = $pig->weaning->weaning_date instanceof Carbon ? $pig->weaning->weaning_date : Carbon::parse($pig->weaning->weaning_date);
                $dob         = $pig->date_of_birth instanceof Carbon ? $pig->date_of_birth : Carbon::parse($pig->date_of_birth);

                $pig->days_since_weaning    = (int) $weaningDate->diffInDays(now());
                $pig->days_until_mature     = $maturityAge - (int) $dob->diffInDays(now());

                $daysTotalToMaturity = $maturityAge - 45;
                $daysPastWeaning     = $dob->diffInDays(now()) - 45;
                $pig->progress_percentage = round(($daysPastWeaning / $daysTotalToMaturity) * 100, 1);
                $pig->expected_maturity_date = $dob->copy()->addDays($maturityAge);
            }
            return $pig;
        });

        $stats = [
            'total_in_progress' => PigManagement::active()->whereNotNull('date_of_birth')
                ->whereHas('weaning', fn ($q) => $q->where('maturity_completed', false))
                ->count(),
            'recently_weaned' => PigManagement::active()->whereNotNull('date_of_birth')
                ->whereHas('weaning', fn ($q) => $q->where('weaning_date', '>=', now()->subDays(7))->where('maturity_completed', false))
                ->count(),
            'near_maturity' => PigManagement::active()->whereNotNull('date_of_birth')
                ->whereRaw('DATEDIFF(NOW(), date_of_birth) BETWEEN ? AND ?', [$maturityAge - 14, $maturityAge - 1])
                ->whereHas('weaning', fn ($q) => $q->where('maturity_completed', false))
                ->count(),
            'ready_for_completion' => PigManagement::active()->whereNotNull('date_of_birth')
                ->whereRaw('DATEDIFF(NOW(), date_of_birth) >= ?', [$maturityAge])
                ->whereHas('weaning', fn ($q) => $q->where('maturity_completed', false))
                ->count(),
        ];

        return view('weaning.in-progress', compact('inProgressPigs', 'stats'));
    }

    public function weaned()
    {
        $this->authorize('viewAny', Weaning::class);
        $completedPigs = Weaning::with(['pig', 'mother', 'recordedBy', 'completedBy'])
            ->where('maturity_completed', true)
            ->orderBy('maturity_completion_date', 'desc')
            ->paginate(10);

        $stats = [
            'total_completed' => Weaning::where('maturity_completed', true)->count(),
            'completed_this_month' => Weaning::where('maturity_completed', true)
                ->whereMonth('maturity_completion_date', now()->month)
                ->whereYear('maturity_completion_date', now()->year)
                ->count(),
            'average_maturity_age' => Weaning::where('maturity_completed', true)->avg('age_at_maturity_days'),
            'breeding_destination' => Weaning::where('maturity_completed', true)->where('next_destination', 'breeding')->count(),
            'market_destination'   => Weaning::where('maturity_completed', true)->where('next_destination', 'market')->count(),
        ];

        return view('weaning.weaned', compact('completedPigs', 'stats'));
    }

    public function overdue()
    {
        $this->authorize('viewAny', PigManagement::class);
        $weaningAge       = config('breeding.cycle.birth_to_weaning_days', 45);
        $overdueThreshold = $weaningAge + 7;

        $overduePigs = PigManagement::active()->whereNotNull('date_of_birth')
            ->whereRaw('DATEDIFF(NOW(), date_of_birth) >= ?', [$overdueThreshold])
            ->whereDoesntHave('weaning')
            ->with(['mother', 'father'])
            ->orderBy('date_of_birth', 'asc')
            ->paginate(10);

        $overdueSowQuery = $this->getSowsWithLitterStatusQuery($overdueThreshold, null, true);

        $overdueSows = $overdueSowQuery->with(['litters' => function ($q) use ($overdueThreshold) {
                $q->whereNull('date_weaned')
                  ->whereRaw('DATEDIFF(NOW(), date_of_birth) >= ?', [$overdueThreshold])
                  ->oldest('date_of_birth');
            }])
            ->orderBy('ear_tag_number', 'asc')
            ->get();

        $stats = [
            'total_overdue' => PigManagement::active()->whereNotNull('date_of_birth')
                ->whereRaw('DATEDIFF(NOW(), date_of_birth) >= ?', [$overdueThreshold])
                ->whereDoesntHave('weaning')
                ->count(),
            'critically_overdue' => PigManagement::active()->whereNotNull('date_of_birth')
                ->whereRaw('DATEDIFF(NOW(), date_of_birth) >= ?', [$weaningAge + 14])
                ->whereDoesntHave('weaning')
                ->count(),
            'total_sows_overdue'      => $overdueSowQuery->count(),
            'sows_critically_overdue' => $this->getSowsWithLitterStatusQuery($weaningAge + 14, null, true)->count(),
        ];

        return view('weaning.overdue', compact('overduePigs', 'overdueSows', 'stats'));
    }

    protected function getSowsWithLitterStatusQuery(
        int $minDays,
        ?int $maxDays = null,
        bool $mustBeUnweaned = true,
        ?bool $mustBeWeaned = null,
    ): Builder {
        $query = PigManagement::active()->where('gender', 'F');

        $query->whereHas('litters', function ($q) use ($minDays, $maxDays, $mustBeUnweaned, $mustBeWeaned) {
            $q->whereNotNull('date_of_birth');
            if ($mustBeUnweaned) {
                $q->whereNull('date_weaned');
            } elseif ($mustBeWeaned !== null) {
                $q->whereNotNull('date_weaned');
            }
            if ($maxDays !== null) {
                $q->whereRaw('DATEDIFF(NOW(), date_of_birth) < ?', [$maxDays]);
            }
            $q->whereRaw('DATEDIFF(NOW(), date_of_birth) >= ?', [$minDays]);
        });

        return $query;
    }

    // ---------------------------------------------------------------- weaning writes

    public function recordWeaning(Request $request, $id)
    {
        $this->authorize('create', Weaning::class);
        $pig = PigManagement::findOrFail($id);

        $data = $request->validate([
            'weaning_date'          => ['required', 'date', 'before_or_equal:today'],
            'weaning_weight'        => ['nullable', 'numeric', 'min:0', 'max:500'],
            'weaning_method'        => ['required', 'in:natural,forced,gradual'],
            'weaning_reason'        => ['required', 'in:age_appropriate,health_issue,mother_issue,management_decision'],
            'notes'                 => ['nullable', 'string', 'max:1000'],
            'post_weaning_status'   => ['required', 'in:healthy,stressed,sick,monitoring'],
            'follow_up_date'        => ['nullable', 'date', 'after:weaning_date'],
        ]);

        if (! $pig->date_of_birth) {
            return back()->withErrors(['weaning' => 'Cannot wean pig without birth date.']);
        }
        if ($pig->weaning) {
            return back()->withErrors(['weaning' => 'This pig has already been weaned.']);
        }

        $birthDate   = $pig->date_of_birth instanceof Carbon ? $pig->date_of_birth->copy()->startOfDay() : Carbon::parse($pig->date_of_birth)->startOfDay();
        $weaningDate = Carbon::parse($data['weaning_date'])->endOfDay();

        $ageAtWeaning   = (int) $birthDate->diffInDays($weaningDate);
        $minWeaningAge  = config('breeding.cycle.birth_to_weaning_days', 45);

        if ($ageAtWeaning < $minWeaningAge) {
            return back()->withErrors(['weaning' => "Pig is too young for weaning. Minimum age is {$minWeaningAge} days."]);
        }

        DB::transaction(function () use ($pig, $data, $ageAtWeaning) {
            $weaning = Weaning::create([
                'pig_id'              => $pig->id,
                'mother_id'           => $pig->mother_id,
                'weaning_date'        => $data['weaning_date'],
                'age_at_weaning_days' => $ageAtWeaning,
                'weaning_weight'      => $data['weaning_weight'] ?? null,
                'weaning_method'      => $data['weaning_method'],
                'weaning_reason'      => $data['weaning_reason'],
                'notes'               => $data['notes']         ?? null,
                'post_weaning_status' => $data['post_weaning_status'],
                'follow_up_date'      => $data['follow_up_date'] ?? null,
                'recorded_by'         => auth()->id(),
                'maturity_completed'  => false,
            ]);

            if (isset($data['weaning_weight'])) {
                $pig->weights()->create([
                    'weight'      => $data['weaning_weight'],
                    'weigh_date'  => $data['weaning_date'],
                    'notes'       => 'Weaning weight',
                ]);
            }

            $organizationId = $this->currentOrganization->id();
            if ($organizationId !== null) {
                $this->dispatcher->dispatch(new PigWeaned(
                    source: new SourceReference(
                        module: 'lifecycle',
                        sourceType: Weaning::class,
                        sourceId: (int) $weaning->id,
                        organizationId: $organizationId,
                    ),
                    occurredAt: CarbonImmutable::parse($data['weaning_date']),
                    dispatchedByUserId: auth()->id(),
                    pigId: (int) $pig->id,
                    groupId: $pig->group_id ? (int) $pig->group_id : null,
                    weaningWeightKg: isset($data['weaning_weight']) ? (float) $data['weaning_weight'] : null,
                ));
            }
        });

        $statusMessage = match ($data['post_weaning_status']) {
            'healthy'    => 'Pig appears healthy post-weaning.',
            'stressed'   => 'Pig showing signs of stress. Monitor closely.',
            'sick'       => 'Health concerns noted. Consider veterinary consultation.',
            'monitoring' => 'Pig requires ongoing monitoring.',
        };

        return back()->with('success',
            "Weaning recorded for {$pig->code} at {$ageAtWeaning} days old. {$statusMessage}"
        );
    }

    public function completeMaturity(Request $request, $id)
    {
        $pig = PigManagement::findOrFail($id);
        $this->authorize('update', $pig);

        if (! $pig->weaning) {
            return back()->withErrors(['maturity' => 'This pig has not been weaned yet.']);
        }

        $data = $request->validate([
            'completion_date'        => ['required', 'date', 'before_or_equal:today'],
            'final_weight'           => ['nullable', 'numeric', 'min:0', 'max:500'],
            'health_status'          => ['required', 'in:excellent,good,fair,poor'],
            'body_condition_score'   => ['nullable', 'integer', 'min:1', 'max:5'],
            'next_destination'       => ['required', 'in:breeding,market'],
            'growth_performance'     => ['nullable', 'in:excellent,good,average,poor'],
            'completion_notes'       => ['nullable', 'string', 'max:1000'],
            'alive_males'            => ['nullable', 'integer', 'min:0'],
            'alive_females'          => ['nullable', 'integer', 'min:0'],
        ]);

        $ageAtMaturity = (int) $pig->date_of_birth->diffInDays($data['completion_date']);

        $pig->weaning->update([
            'maturity_completed'          => true,
            'maturity_completion_date'    => $data['completion_date'],
            'age_at_maturity_days'        => $ageAtMaturity,
            'final_weight'                => $data['final_weight']         ?? null,
            'health_status_at_maturity'   => $data['health_status'],
            'body_condition_score'        => $data['body_condition_score'] ?? null,
            'next_destination'            => $data['next_destination'],
            'growth_performance'          => $data['growth_performance']   ?? null,
            'maturity_notes'              => $data['completion_notes']     ?? null,
            'alive_males'                 => $data['alive_males']          ?? null,
            'alive_females'               => $data['alive_females']        ?? null,
            'completed_by'                => auth()->id(),
        ]);

        if ($data['next_destination'] === 'market' && $pig->stock_type === 'breeding') {
            $pig->stock_type = 'standard';
            $pig->save();
        }

        return back()->with('success', "Weaning completed for {$pig->code}. Age at completion: {$ageAtMaturity} days.");
    }

    public function updateWeaningStatus(Request $request, $id)
    {
        $pig = PigManagement::findOrFail($id);
        $this->authorize('update', $pig);

        $data = $request->validate([
            'status'      => ['required', 'in:weaned,delayed,health_issue'],
            'status_date' => ['required', 'date', 'before_or_equal:today'],
            'notes'       => ['nullable', 'string', 'max:1000'],
        ]);

        if ($data['status'] === 'weaned') {
            $pig->update([
                'weaning_date'  => $data['status_date'],
                'weaning_notes' => $data['notes'] ?? null,
            ]);
            return back()->with('success', "Weaning status updated for {$pig->code}.");
        }

        $pig->update(['weaning_notes' => $data['notes'] ?? null]);
        $message = match ($data['status']) {
            'delayed'      => "Weaning delayed for {$pig->code}. Monitor for readiness.",
            'health_issue' => "Health issue noted for {$pig->code}. Consult veterinarian before weaning.",
        };
        return back()->with('success', $message);
    }
}
