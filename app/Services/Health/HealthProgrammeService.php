<?php

namespace App\Services\Health;

use App\Models\Farrowing;
use App\Models\HealthProgramme;
use App\Models\HealthProgrammeDose;
use App\Models\Mating;
use App\Models\PigManagement;
use App\Models\ScheduledDose;
use App\Models\TreatmentCourse;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Turns herd health programmes into scheduled doses.
 *
 * Each due date becomes an ordinary one-dose course — so the worklist, the
 * alerts, the reminders, recording, stock and withdrawal all work exactly as
 * they do for a course somebody prescribed by hand.
 *
 * THREE RULES
 * -----------
 * Looks ahead `schedule_days_ahead` days, far enough that the alerts and the
 * day-ahead reminders see a dose before it is due.
 *
 * Never schedules into the past beyond the day the programme was created or
 * the day the animal came onto the system. Without this, setting up "PCV2 at
 * 21 days" on a farm of 400 older pigs would declare 400 doses overdue.
 *
 * Schedules each animal's occurrence once (health_programme_doses). A dose
 * staff skip — cancelled with a reason on the worklist — stays skipped.
 */
class HealthProgrammeService
{
    public function __construct(protected TreatmentCourseService $courses)
    {
    }

    public static function daysAhead(): int
    {
        return (int) config('health.programmes.schedule_days_ahead', 14);
    }

    /** @return int doses scheduled */
    public function scheduleAll(CarbonImmutable $now): int
    {
        return HealthProgramme::query()->where('is_active', true)->with('medicine')->get()
            ->sum(fn (HealthProgramme $programme) => $this->schedule($programme, $now));
    }

    /** @return int doses scheduled */
    public function schedule(HealthProgramme $programme, CarbonImmutable $now): int
    {
        if (! $programme->is_active) {
            return 0;
        }

        $actor = $this->actorFor($programme);
        if ($actor === null) {
            Log::warning('Health programme has no user to schedule as; skipped.', ['programme_id' => $programme->id]);

            return 0;
        }

        $until = $now->startOfDay()->addDays(self::daysAhead());
        $floor = $this->floor($programme);

        $due = $this->candidates($programme, $floor, $until)
            ->reject(fn (array $c) => $c['due_on']->lt($floor));

        if ($due->isEmpty()) {
            return 0;
        }

        $already = HealthProgrammeDose::query()
            ->where('health_programme_id', $programme->id)
            ->whereIn('pig_id', $due->pluck('pig.id')->unique()->all())
            ->get(['pig_id', 'occurrence_key'])
            ->map(fn ($row) => $row->pig_id.'|'.$row->occurrence_key)
            ->flip();

        $new = $due->reject(fn (array $c) => isset($already[$c['pig']->id.'|'.$c['key']]));

        $scheduled = 0;
        foreach ($new->groupBy(fn (array $c) => $c['due_on']->toDateString()) as $date => $group) {
            try {
                $scheduled += $this->scheduleDay($programme, $actor, CarbonImmutable::parse($date), $group->unique(fn ($c) => $c['pig']->id.'|'.$c['key'])->values());
            } catch (\Throwable $e) {
                // A medicine taken out of the catalogue, a unit changed: one
                // bad programme must not stop the others being scheduled.
                Log::warning('Health programme could not be scheduled.', [
                    'programme_id' => $programme->id, 'due_on' => $date, 'error' => $e->getMessage(),
                ]);
            }
        }

        return $scheduled;
    }

    /**
     * Stop scheduling and cancel what has not been given yet. Doses already
     * recorded stay on record.
     *
     * @return int doses cancelled
     */
    public function pause(HealthProgramme $programme, User $actor, string $reason): int
    {
        return DB::transaction(function () use ($programme, $actor, $reason) {
            $programme->update(['is_active' => false, 'paused_reason' => $reason, 'updated_by' => $actor->id]);

            $doses = ScheduledDose::query()
                ->where('status', ScheduledDose::SCHEDULED)
                ->whereHas('courseMedicine.course', fn ($q) => $q->where('health_programme_id', $programme->id))
                ->get();

            foreach ($doses as $dose) {
                app(ScheduledDoseService::class)->close($actor, $dose, ScheduledDose::CANCELLED, 'Programme paused: '.$reason);
            }

            // Forget these occurrences, so resuming schedules them again if
            // they are still ahead. A dose staff skipped one by one is not
            // touched: it was never cancelled by the pause.
            HealthProgrammeDose::query()->where('health_programme_id', $programme->id)
                ->whereIn('scheduled_dose_id', $doses->modelKeys())->delete();

            return $doses->count();
        });
    }

    public function resume(HealthProgramme $programme, User $actor, CarbonImmutable $now): int
    {
        $programme->update(['is_active' => true, 'paused_reason' => null, 'updated_by' => $actor->id]);

        return $this->schedule($programme->fresh('medicine'), $now);
    }

    /**
     * Who the programme schedules as: the person who last changed it, so the
     * audit trail names someone who decided on this dose.
     */
    protected function actorFor(HealthProgramme $programme): ?User
    {
        foreach ([$programme->updated_by, $programme->created_by] as $id) {
            if ($id && ($user = User::withoutGlobalScopes()->find($id))) {
                return $user;
            }
        }

        return null;
    }

    /** The earliest date a dose may be scheduled for. */
    protected function floor(HealthProgramme $programme): CarbonImmutable
    {
        $created = CarbonImmutable::parse($programme->created_at ?? now())->startOfDay();
        $starts = CarbonImmutable::parse($programme->starts_on)->startOfDay();

        return $starts->gt($created) ? $starts : $created;
    }

    /**
     * Every (pig, occurrence, due date) the programme asks for up to $until.
     *
     * @return Collection<int, array{pig: PigManagement, key: string, due_on: CarbonImmutable}>
     */
    protected function candidates(HealthProgramme $programme, CarbonImmutable $floor, CarbonImmutable $until): Collection
    {
        $days = (int) $programme->days;

        return match ($programme->trigger) {
            'age' => $this->animals($programme)
                ->whereNotNull('date_of_birth')
                ->whereDate('date_of_birth', '>=', $floor->subDays($days)->toDateString())
                ->whereDate('date_of_birth', '<=', $until->subDays($days)->toDateString())
                ->get()
                ->map(fn (PigManagement $pig) => ['pig' => $pig, 'key' => 'age', 'due_on' => CarbonImmutable::parse($pig->date_of_birth)->addDays($days)])
                ->filter(fn ($c) => $c['due_on']->gte($this->joined($c['pig'])))
                ->values(),

            'interval' => $this->intervalCandidates($programme, $floor, $until),

            'before_mating' => Mating::query()->where('status', 'planned')->whereNotNull('planned_date')
                ->whereDate('planned_date', '>=', $floor->addDays($days)->toDateString())
                ->whereDate('planned_date', '<=', $until->addDays($days)->toDateString())
                ->whereIn('female_id', $this->animals($programme)->select('pigs_management.id'))
                ->with('female')->get()
                ->filter(fn (Mating $m) => $m->female)
                ->map(fn (Mating $m) => ['pig' => $m->female, 'key' => 'mating:'.$m->id,
                    'due_on' => CarbonImmutable::parse($m->planned_date)->subDays($days)])
                ->values(),

            'before_farrowing' => Mating::query()->where('status', 'completed')->where('pregnancy_status', 'pregnant')
                ->whereNotNull('expected_farrowing_date')
                ->whereDate('expected_farrowing_date', '>=', $floor->addDays($days)->toDateString())
                ->whereDate('expected_farrowing_date', '<=', $until->addDays($days)->toDateString())
                ->whereIn('female_id', $this->animals($programme)->select('pigs_management.id'))
                ->with('female')->get()
                ->filter(fn (Mating $m) => $m->female)
                ->map(fn (Mating $m) => ['pig' => $m->female, 'key' => 'gestation:'.$m->id,
                    'due_on' => CarbonImmutable::parse($m->expected_farrowing_date)->subDays($days)])
                ->values(),

            'after_farrowing' => Farrowing::query()->whereNotNull('farrowing_date')
                ->whereDate('farrowing_date', '>=', $floor->subDays($days)->toDateString())
                ->whereDate('farrowing_date', '<=', $until->subDays($days)->toDateString())
                ->whereIn('sow_id', $this->animals($programme)->select('pigs_management.id'))
                ->with('sow')->get()
                ->filter(fn (Farrowing $f) => $f->sow)
                ->map(fn (Farrowing $f) => ['pig' => $f->sow, 'key' => 'farrowing:'.$f->id,
                    'due_on' => CarbonImmutable::parse($f->farrowing_date)->addDays($days)])
                ->values(),

            default => collect(),
        };
    }

    /** Herd-wide dates — starts_on, then every N days — for every animal in the group on that date. */
    protected function intervalCandidates(HealthProgramme $programme, CarbonImmutable $floor, CarbonImmutable $until): Collection
    {
        $every = max(1, (int) $programme->days);
        $date = CarbonImmutable::parse($programme->starts_on)->startOfDay();
        if ($date->lt($floor)) {
            $date = $date->addDays((int) ceil($date->diffInDays($floor) / $every) * $every);
        }

        $dates = [];
        for (; $date->lte($until); $date = $date->addDays($every)) {
            $dates[] = $date;
        }
        if ($dates === []) {
            return collect();
        }

        $pigs = $this->animals($programme)->get();

        return collect($dates)->flatMap(fn (CarbonImmutable $on) => $pigs
            ->filter(fn (PigManagement $pig) => $on->gte($this->joined($pig)))
            ->map(fn (PigManagement $pig) => ['pig' => $pig, 'key' => 'interval:'.$on->toDateString(), 'due_on' => $on]))
            ->values();
    }

    /** Live animals in the programme's group. */
    protected function animals(HealthProgramme $programme): Builder
    {
        $group = in_array($programme->trigger, HealthProgramme::BREEDING_TRIGGERS, true) ? 'breeding_females' : $programme->applies_to;
        $female = fn ($q) => $q->whereIn(DB::raw('UPPER(SUBSTR(gender, 1, 1))'), ['F']);
        $male = fn ($q) => $q->whereIn(DB::raw('UPPER(SUBSTR(gender, 1, 1))'), ['M']);

        return PigManagement::query()->active()
            ->when($group === 'growers', fn ($q) => $q->where('stock_type', 'standard'))
            ->when(in_array($group, ['breeding', 'breeding_females', 'boars'], true), fn ($q) => $q->where('stock_type', 'breeding'))
            ->when($group === 'breeding_females', $female)
            ->when($group === 'boars', $male);
    }

    /** The day an animal came onto the system: nothing earlier is scheduled for it. */
    protected function joined(PigManagement $pig): CarbonImmutable
    {
        return CarbonImmutable::parse($pig->created_at ?? now())->startOfDay();
    }

    /** One course per programme and day: new animals that qualify later join it. */
    protected function scheduleDay(HealthProgramme $programme, User $actor, CarbonImmutable $day, Collection $candidates): int
    {
        $dueAt = CarbonImmutable::parse($day->toDateString().' '.substr((string) $programme->due_time, 0, 5));

        return DB::transaction(function () use ($programme, $actor, $dueAt, $candidates) {
            $course = TreatmentCourse::query()
                ->where('health_programme_id', $programme->id)
                ->where('status', 'active')
                ->whereHas('medicines', fn ($q) => $q->where('start_at', $dueAt))
                ->with('medicines')
                ->lockForUpdate()
                ->first();

            $pigs = $candidates->pluck('pig')->unique('id')->values();

            if ($course === null) {
                $course = $this->courses->prescribe($actor, [
                    'purpose'             => $programme->purpose,
                    'health_programme_id' => $programme->id,
                    'responsible_user_id' => $programme->responsible_user_id,
                    'notes'               => 'Scheduled by herd health programme: '.$programme->name.' ('.$programme->ruleText().').',
                ], $pigs->all(), [[
                    'medicine_id'     => $programme->medicine_id,
                    'dose_amount'     => (string) $programme->dose_amount,
                    'dose_unit'       => $programme->dose_unit,
                    'route'           => $programme->route,
                    'frequency'       => 'once',
                    'interval'        => null,
                    'number_of_doses' => 1,
                    'start_at'        => $dueAt->format('Y-m-d H:i:s'),
                    'instructions'    => $programme->instructions,
                ]]);
            } else {
                $line = $course->medicines->first();
                $onCourse = $course->animals()->pluck('pig_id')->map(fn ($id) => (int) $id)->flip();
                foreach ($pigs as $pig) {
                    if (! isset($onCourse[(int) $pig->id])) {
                        $course->animals()->create(['pig_id' => $pig->id, 'tag_label_snapshot' => $pig->tagLabelAt($dueAt)]);
                        $line->scheduledDoses()->create(['pig_id' => $pig->id, 'due_at' => $dueAt, 'status' => ScheduledDose::SCHEDULED]);
                    }
                }
            }

            $doseIds = ScheduledDose::query()
                ->whereIn('treatment_course_medicine_id', $course->medicines()->pluck('id'))
                ->pluck('id', 'pig_id');

            foreach ($candidates as $c) {
                HealthProgrammeDose::create([
                    'health_programme_id' => $programme->id,
                    'pig_id'              => $c['pig']->id,
                    'occurrence_key'      => $c['key'],
                    'due_on'              => $c['due_on']->toDateString(),
                    'scheduled_dose_id'   => $doseIds[$c['pig']->id] ?? null,
                ]);
            }

            return $candidates->count();
        });
    }
}
