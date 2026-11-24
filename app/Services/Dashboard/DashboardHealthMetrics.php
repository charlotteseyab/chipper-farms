<?php

namespace App\Services\Dashboard;

use App\Models\HealthRecord;
use App\Models\Medicine;
use App\Models\ScheduledDose;
use App\Services\Health\HealthAlertService;
use App\Services\Health\HealthGlanceService;
use App\Services\Health\HealthWorklistService;
use App\Services\Health\MedicineStockService;
use App\Services\Health\WithdrawalStatusService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The health picture for a farm dashboard, in five questions:
 *
 *   What health work is due?          overdue / today / next days, by kind
 *   Which animals are sick?           open, severe, new this week, unchecked
 *   Which animals must not be sold?   under withdrawal, and clearing soon
 *   Is prevention being kept up?      vaccination and deworming given vs due
 *   Do we have the medicine?          out, low, expiring, short for doses due
 *
 * Every number comes from the service that already owns it — alerts,
 * worklist, withdrawal, stock — so the dashboard agrees with the page each
 * card links to. Works on the organisation currently bound.
 */
class DashboardHealthMetrics
{
    /** Window for the prevention record and the "top conditions" list. */
    public const LOOKBACK_DAYS = 30;

    public function __construct(
        protected HealthAlertService $alerts,
        protected HealthWorklistService $worklist,
        protected WithdrawalStatusService $withdrawals,
        protected MedicineStockService $stock,
        protected HealthGlanceService $glance,
    ) {
    }

    public function summary(CarbonImmutable $now): array
    {
        $alerts = $this->alerts->alerts($now);

        return [
            'tasks'      => $this->tasks($alerts),
            'next'       => $alerts->whereIn('state', ['overdue', 'today'])->take(5)->values(),
            'cases'      => $this->cases($now),
            'withdrawal' => $this->withdrawal($now),
            'prevention' => $this->prevention($now),
            'medicine'   => $this->medicine($now),
            'days'       => HealthAlertService::defaultDays(),
        ];
    }

    private function tasks(Collection $alerts): array
    {
        $byState = fn (string $state) => $alerts->where('state', $state);

        return [
            'overdue'         => $byState('overdue')->count(),
            'today'           => $byState('today')->count(),
            'upcoming'        => $byState('upcoming')->count(),
            'overdue_detail'  => HealthAlertService::describe($byState('overdue')->countBy('type')->all()),
            'today_detail'    => HealthAlertService::describe($byState('today')->countBy('type')->all()),
            'upcoming_detail' => HealthAlertService::describe($byState('upcoming')->countBy('type')->all()),
        ];
    }

    /** Counted in animals, not records: a group case of 12 pigs is 12 sick animals. */
    private function cases(CarbonImmutable $now): array
    {
        $open = HealthRecord::query()->notVoided()->whereIn('case_status', ['open', 'under_treatment'])
            ->with(['animals.pig', 'pig'])->get();

        $animalsOf = fn (Collection $cases) => $cases
            ->flatMap(fn (HealthRecord $case) => $case->animals->pluck('pig')->push($case->pig))
            ->filter(fn ($pig) => $pig && ! $pig->trashed())
            ->unique('id');

        $severe = $open->filter(fn (HealthRecord $c) => in_array($c->severity, ['severe', 'high'], true));
        $since = $now->subDays(self::LOOKBACK_DAYS)->toDateString();

        return [
            'sick_animals'   => $animalsOf($open)->count(),
            'open_cases'     => $open->count(),
            'severe_animals' => $animalsOf($severe)->count(),
            'new_this_week'  => HealthRecord::query()->notVoided()->whereDate('record_date', '>=', $now->subDays(6)->toDateString())->count(),
            'need_check'     => $this->worklist->casesNeedingCheck($now)->count(),
            // Animals affected, so a group case of 12 counts as 12.
            'top_conditions' => HealthRecord::query()->notVoided()->whereDate('record_date', '>=', $since)
                ->whereNotNull('condition')->where('condition', '!=', '')
                ->with(['animals.pig', 'pig'])->get()
                ->groupBy(fn (HealthRecord $case) => trim($case->condition))
                ->map(fn (Collection $cases) => $animalsOf($cases)->count() ?: $cases->count())
                ->sortDesc()->take(3)->all(),
        ];
    }

    private function withdrawal(CarbonImmutable $now): array
    {
        $restricted = $this->withdrawals->restrictedAnimals($now->toDateString())->get();
        $until = $now->addDays(HealthAlertService::defaultDays())->toDateString();

        return [
            'animals'       => $restricted->count(),
            'clearing_soon' => $restricted->filter(fn ($dose) => $dose->withdrawal_ends_on->toDateString() <= $until)->count(),
            'longest_until' => $restricted->max('withdrawal_ends_on'),
        ];
    }

    /**
     * Vaccination and deworming doses due in the last 30 days — up to the
     * point a dose counts as late — and what happened to them.
     */
    private function prevention(CarbonImmutable $now): array
    {
        $doses = ScheduledDose::query()
            ->whereBetween('due_at', [$now->subDays(self::LOOKBACK_DAYS), $now->subHours((int) config('health.reminders.overdue_after_hours', 2))])
            ->whereHas('courseMedicine.course', fn ($q) => $q->whereIn('purpose', ['vaccination', 'deworming']))
            ->whereHas('pig', fn ($q) => $q->whereNull('pigs_management.deleted_at'))
            ->get(['id', 'status']);

        $due = $doses->count();
        $given = $doses->where('status', ScheduledDose::ADMINISTERED)->count();

        return [
            'due'        => $due,
            'given'      => $given,
            'not_given'  => $doses->whereIn('status', [ScheduledDose::MISSED, ScheduledDose::CANCELLED])->count(),
            'unrecorded' => $doses->where('status', ScheduledDose::SCHEDULED)->count(),
            'rate'       => $due > 0 ? round($given / $due * 100) : null,
        ];
    }

    private function medicine(CarbonImmutable $now): array
    {
        $medicines = Medicine::query()->where('is_active', true)->get();
        $onHand = $this->stock->onHandByMedicine($medicines->modelKeys());
        $level = fn (Medicine $m) => (string) ($onHand[$m->id] ?? '0');

        $out = $medicines->filter(fn (Medicine $m) => $m->batches()->exists() && bccomp($level($m), '0', 4) <= 0);
        $low = $medicines->filter(fn (Medicine $m) => $m->reorder_level !== null
            && bccomp($level($m), '0', 4) > 0 && bccomp($level($m), (string) $m->reorder_level, 4) <= 0);
        [$expiring, $expired] = $this->glance->expiryCounts();
        $short = $this->glance->shortForScheduledDoses($now->subDay(), $now->endOfDay()->addDays(HealthAlertService::defaultDays()), $now);

        return [
            'out'      => $out->count(),
            'low'      => $low->count(),
            'expiring' => $expiring,
            'expired'  => $expired,
            'short'    => $short->count(),
            'short_names' => $short->pluck('medicine.name')->take(3)->implode(', '),
            'out_names'   => $out->pluck('name')->take(3)->implode(', '),
        ];
    }
}
