<?php

namespace App\Services\Health;

use App\Models\MedicationAdministration;
use App\Models\ScheduledDose;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Health work that is overdue, due today, or coming up — by kind.
 *
 * One list for the alerts page, the pig profile and the dashboards, so the
 * numbers in each agree. Built from the records that already drive the
 * worklist and the reminders:
 *
 *   vaccination / deworming / treatment — scheduled doses on active courses,
 *     kind taken from the course's purpose ("other" counts as treatment)
 *   follow_up  — course follow-ups and vet re-checks still to do
 *   withdrawal — animals whose withdrawal ends inside the window, i.e. that
 *     can be sold soon; on one pig's page, any withdrawal still running
 *
 * Overdue looks back as far as the worklist does (OVERDUE_DAYS). A dose due
 * earlier today becomes overdue once it is late by the same margin the
 * reminders use, so the page and the bell never disagree about it.
 */
class HealthAlertService
{
    public const TYPES = [
        'vaccination' => 'Vaccination',
        'deworming'   => 'Deworming',
        'treatment'   => 'Treatment',
        'follow_up'   => 'Follow-up',
        'withdrawal'  => 'Withdrawal',
    ];

    public const STATES = [
        'overdue'  => 'Overdue',
        'today'    => 'Due today',
        'upcoming' => 'Coming up',
    ];

    public function __construct(
        protected HealthWorklistService $worklist,
        protected WithdrawalStatusService $withdrawals,
    ) {
    }

    public static function defaultDays(): int
    {
        return (int) config('health.alerts.upcoming_days', 7);
    }

    /**
     * @return Collection<int, array{type: string, state: string, due: CarbonImmutable, title: string, detail: ?string, animals: int, url: ?string}>
     */
    public function alerts(CarbonImmutable $now, ?int $days = null, ?int $pigId = null): Collection
    {
        $days ??= self::defaultDays();
        $until = $now->startOfDay()->addDays($days)->endOfDay();

        return $this->doses($now, $until, $pigId)
            ->concat($this->followUps($now, $until, $pigId))
            ->concat($this->withdrawal($now, $until, $pigId))
            ->sortBy([
                fn ($a, $b) => array_search($a['state'], array_keys(self::STATES)) <=> array_search($b['state'], array_keys(self::STATES)),
                fn ($a, $b) => $a['due'] <=> $b['due'],
            ])
            ->values();
    }

    /**
     * Counts for a dashboard: by state, and by type within each state.
     *
     * @return array{overdue: int, today: int, upcoming: int, by_type: array<string, array<string, int>>, days: int}
     */
    public function summary(CarbonImmutable $now, ?int $days = null): array
    {
        $days ??= self::defaultDays();
        $alerts = $this->alerts($now, $days);

        $byType = [];
        foreach (array_keys(self::STATES) as $state) {
            $byType[$state] = $alerts->where('state', $state)->countBy('type')->all();
        }

        return [
            'overdue'  => $alerts->where('state', 'overdue')->count(),
            'today'    => $alerts->where('state', 'today')->count(),
            'upcoming' => $alerts->where('state', 'upcoming')->count(),
            'by_type'  => $byType,
            'days'     => $days,
        ];
    }

    /** "2 vaccinations, 1 deworming" from one state's counts in summary(). */
    public static function describe(array $countsByType): string
    {
        $parts = [];
        foreach (self::TYPES as $type => $label) {
            if (($n = $countsByType[$type] ?? 0) > 0) {
                $word = match ($type) {
                    'deworming'  => 'deworming',
                    'follow_up'  => 'follow-up',
                    'withdrawal' => 'withdrawal ending',
                    default      => strtolower($label),
                };
                $parts[] = $n.' '.($type === 'deworming' || $type === 'withdrawal' ? $word : str($word)->plural($n));
            }
        }

        return $parts === [] ? 'Nothing' : implode(', ', $parts);
    }

    public static function typeFor(?string $purpose): string
    {
        return in_array($purpose, ['vaccination', 'deworming'], true) ? $purpose : 'treatment';
    }

    /** One alert per course medicine and day: "PRRS vaccine — 12 animals". */
    private function doses(CarbonImmutable $now, CarbonImmutable $until, ?int $pigId): Collection
    {
        $lateAfter = $now->subHours((int) config('health.reminders.overdue_after_hours', 2));

        return ScheduledDose::query()
            ->with(['pig', 'courseMedicine.medicine', 'courseMedicine.course'])
            ->where('status', ScheduledDose::SCHEDULED)
            ->whereHas('courseMedicine.course', fn ($q) => $q->where('status', 'active'))
            ->whereHas('pig', fn ($q) => $q->whereNull('pigs_management.deleted_at'))
            ->when($pigId, fn ($q, $id) => $q->where('pig_id', $id))
            ->whereBetween('due_at', [$now->startOfDay()->subDays(HealthWorklistService::OVERDUE_DAYS), $until])
            ->orderBy('due_at')
            ->get()
            ->groupBy(fn (ScheduledDose $d) => $d->treatment_course_medicine_id.'|'.$d->due_at->toDateString()
                // Today's doses split into late and still-to-come.
                .'|'.($d->due_at->lt($lateAfter) ? 'late' : 'ontime'))
            ->map(function (Collection $slot) use ($now, $lateAfter) {
                $first = $slot->first();
                $line = $first->courseMedicine;
                $course = $line?->course;
                $due = CarbonImmutable::parse($first->due_at);
                $type = self::typeFor($course?->purpose);

                $state = match (true) {
                    $due->lt($lateAfter)                => 'overdue',
                    $due->isSameDay($now)               => 'today',
                    default                             => 'upcoming',
                };

                $codes = $slot->pluck('pig.code')->filter()->unique()->values();

                return [
                    'type'    => $type,
                    'state'   => $state,
                    'due'     => $due,
                    'title'   => self::TYPES[$type].': '.($line?->medicine?->name ?? 'medicine').($course ? ' (course #'.$course->id.')' : ''),
                    'detail'  => $codes->count().' '.str('animal')->plural($codes->count()).': '.$this->codes($codes),
                    'animals' => $codes->count(),
                    'url'     => route('health.worklist', ['date' => $due->toDateString()]),
                ];
            })
            ->values();
    }

    private function followUps(CarbonImmutable $now, CarbonImmutable $until, ?int $pigId): Collection
    {
        return $this->worklist
            ->followUpsBetween($now->startOfDay()->subDays(HealthWorklistService::OVERDUE_DAYS), $until, pigId: $pigId)
            ->map(fn (array $item) => [
                'type'    => 'follow_up',
                'state'   => match (true) {
                    $item['due_on']->lt($now->startOfDay()) => 'overdue',
                    $item['due_on']->isSameDay($now)        => 'today',
                    default                                 => 'upcoming',
                },
                'due'     => $item['due_on'],
                'title'   => $item['title'],
                'detail'  => $item['detail'],
                'animals' => 0,
                'url'     => $item['url'],
            ]);
    }

    /**
     * Farm-wide: one alert per end date inside the window. One pig: its
     * withdrawal whenever it ends, because on that animal's page "is it safe
     * to sell?" matters at any distance.
     */
    private function withdrawal(CarbonImmutable $now, CarbonImmutable $until, ?int $pigId): Collection
    {
        $doses = $this->withdrawals->restrictedAnimals($now->toDateString())
            ->when($pigId, fn ($q, $id) => $q->where('pig_id', $id))
            ->when(! $pigId, fn ($q) => $q->whereDate('withdrawal_ends_on', '<=', $until->toDateString()))
            ->get();

        return $doses
            ->groupBy(fn (MedicationAdministration $dose) => $dose->withdrawal_ends_on->toDateString())
            ->map(function (Collection $group, string $endsOn) use ($now) {
                $ends = CarbonImmutable::parse($endsOn);
                $codes = $group->pluck('pig.code')->filter()->unique()->values();
                $medicines = $group->pluck('medication_name')->filter()->unique()->implode(', ');

                return [
                    'type'    => 'withdrawal',
                    'state'   => $ends->isSameDay($now) ? 'today' : 'upcoming',
                    'due'     => $ends,
                    'title'   => 'Withdrawal ends '.$ends->format('D j M').'. Can be sold from '.$ends->addDay()->format('D j M'),
                    'detail'  => $codes->count().' '.str('animal')->plural($codes->count()).': '.$this->codes($codes)
                        .($medicines !== '' ? ' ('.$medicines.')' : ''),
                    'animals' => $codes->count(),
                    'url'     => route('health.alerts', ['type' => 'withdrawal']),
                ];
            })
            ->values();
    }

    private function codes(Collection $codes): string
    {
        return $codes->take(6)->implode(', ').($codes->count() > 6 ? ' and '.($codes->count() - 6).' more' : '');
    }
}
