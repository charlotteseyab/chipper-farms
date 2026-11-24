<?php

namespace App\Services\Health;

use App\Models\HealthRecord;
use App\Models\MedicationAdministration;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\ScheduledDose;
use App\Models\TreatmentCourse;
use App\Models\User;
use App\Models\VetExamination;
use App\Services\Notifications\InAppNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Health reminders (decision D9): doses due today, overdue doses, follow-ups
 * due tomorrow, withdrawal ending within two days, batches near expiry and
 * medicine at or below its reorder level. In-app, to farm managers and the
 * responsible person.
 *
 * Each reminder carries keys (type + record + due date). A key is written to
 * health_reminders when the reminder is sent, so it is never sent twice; the
 * unique index makes that hold even if two scheduler runs overlap. Queries
 * only pick live items, so cancelled doses, stopped courses, closed cases and
 * animals that have left stop reminding by themselves.
 *
 * Works on the organisation currently bound; the command binds each in turn.
 */
class HealthReminderService
{
    public function __construct(
        protected InAppNotificationService $notifications,
        protected MedicineStockService $stock,
    ) {
    }

    /** @return array<string, int> reminders sent, by type */
    public function send(int $organizationId, CarbonImmutable $now): array
    {
        $sent = [];
        foreach ($this->due($organizationId, $now) as $reminder) {
            $recipients = DB::transaction(function () use ($organizationId, $reminder, $now) {
                $claimed = DB::table('health_reminders')->insertOrIgnore(array_map(fn ($key) => [
                    'organization_id' => $organizationId, 'reminder_key' => $key, 'type' => $reminder['type'], 'sent_at' => $now,
                ], $reminder['keys']));
                if ($claimed === 0) {
                    return null;
                }
                $users = $this->notifications->notifyRelevantStaff($organizationId, $reminder['type'], $reminder['title'], $reminder['message'],
                    $reminder['link'], $reminder['severity'], ['reminder_keys' => $reminder['keys']], $this->recipientIds($organizationId, $reminder['responsible']));
                DB::table('health_reminders')->where('organization_id', $organizationId)->whereIn('reminder_key', $reminder['keys'])
                    ->update(['recipients' => $users->count()]);

                return $users->count();
            });
            if ($recipients !== null) {
                $sent[$reminder['type']] = ($sent[$reminder['type']] ?? 0) + 1;
            }
        }

        return $sent;
    }

    /**
     * Reminders whose keys have not been sent yet.
     *
     * @return Collection<int, array{type: string, keys: list<string>, title: string, message: string, link: ?string, severity: string, responsible: list<int>}>
     */
    public function due(int $organizationId, CarbonImmutable $now): Collection
    {
        $all = collect()
            ->merge($this->dosesDueToday($now))
            ->merge($this->overdueDoses($now))
            ->merge($this->routineDosesAhead($now))
            ->merge($this->followUps($now))
            ->merge($this->withdrawalEnding($now))
            ->merge($this->batchesExpiring($now))
            ->merge($this->lowStock())
            ->merge($this->outOfStock())
            ->merge($this->stockShortForDoses($now))
            ->merge($this->batchesExpired($now))
            ->merge($this->coursesFinished($now))
            ->merge($this->followUpsOverdue($now))
            ->merge($this->casesNeedingReview($now));

        $sent = DB::table('health_reminders')->where('organization_id', $organizationId)
            ->whereIn('reminder_key', $all->pluck('keys')->flatten()->all())->pluck('reminder_key')->flip();

        return $all->map(function (array $reminder) use ($sent) {
            $reminder['keys'] = array_values(array_filter($reminder['keys'], fn ($key) => ! isset($sent[$key])));

            return $reminder;
        })->filter(fn ($reminder) => $reminder['keys'] !== [])->values();
    }

    private function dosesDueToday(CarbonImmutable $now): Collection
    {
        $from = CarbonImmutable::parse($now->toDateString().' '.config('health.reminders.doses_due_from', '06:00'), $now->getTimezone());
        if ($now->lt($from)) {
            return collect();
        }

        return $this->doseSlots($this->liveDoses()->whereBetween('due_at', [$now->startOfDay(), $now->endOfDay()])->get(), 'health.treatment_due', 'dose_due',
            fn ($slot, $line, $at) => ["{$this->kind($line)} due {$at->format('H:i')}: {$line->medicine?->name}",
                "{$slot->count()} animal(s) on course #{$line->treatment_course_id} are due {$line->medicine?->display_name} at {$at->format('H:i')}: ".$this->codes($slot->pluck('pig.code')).'.', 'info']);
    }

    private function overdueDoses(CarbonImmutable $now): Collection
    {
        $cutoff = $now->subHours((int) config('health.reminders.overdue_after_hours', 2));
        $since = $now->subDays((int) config('health.reminders.overdue_lookback_days', 7));

        return $this->doseSlots($this->liveDoses()->whereBetween('due_at', [$since, $cutoff])->get(), 'health.dose_overdue', 'dose_overdue',
            fn ($slot, $line, $at) => ["Overdue {$this->kind($line, lower: true)}: {$line->medicine?->name} due {$at->format('D j M H:i')}",
                "{$slot->count()} dose(s) of {$line->medicine?->display_name} on course #{$line->treatment_course_id} have not been recorded: "
                .$this->codes($slot->pluck('pig.code')).'. Record them as given, or mark them missed with a reason.', 'warning']);
    }

    /**
     * Vaccinations and deworming a day or so ahead: unlike a treatment, the
     * product often has to be fetched and the animals gathered first.
     */
    private function routineDosesAhead(CarbonImmutable $now): Collection
    {
        $days = (int) config('health.reminders.routine_doses_days_ahead', 1);
        if ($days < 1) {
            return collect();
        }

        $doses = $this->liveDoses()
            ->whereHas('courseMedicine.course', fn ($q) => $q->whereIn('purpose', ['vaccination', 'deworming']))
            ->whereBetween('due_at', [$now->startOfDay()->addDay(), $now->startOfDay()->addDays($days)->endOfDay()])
            ->get();

        return $this->doseSlots($doses, 'health.dose_upcoming', 'dose_upcoming',
            fn ($slot, $line, $at) => ["{$this->kind($line)} {$this->day($at, $now)} {$at->format('H:i')}: {$line->medicine?->name}",
                "{$slot->count()} animal(s) on course #{$line->treatment_course_id} are due {$line->medicine?->display_name}: "
                .$this->codes($slot->pluck('pig.code')).'. Check there is enough in stock and plan to have the animals penned.', 'info']);
    }

    /** "Vaccination", "Deworming" or "Treatment", from the course's purpose. */
    private function kind($line, bool $lower = false): string
    {
        $label = \App\Services\Health\HealthAlertService::TYPES[\App\Services\Health\HealthAlertService::typeFor($line?->course?->purpose)];

        return $lower ? strtolower($label) : $label;
    }

    private function followUps(CarbonImmutable $now): Collection
    {
        $until = $now->addDays((int) config('health.reminders.follow_up_days_ahead', 1))->toDateString();
        $reminders = collect();

        TreatmentCourse::with('medicines.medicine')->pendingFollowUp()
            ->whereDate('follow_up_on', '>=', $now->toDateString())->whereDate('follow_up_on', '<=', $until)->get()
            ->each(fn (TreatmentCourse $course) => $reminders->push([
                'type' => 'health.follow_up_due', 'keys' => ["follow_up:course:{$course->id}:{$course->follow_up_on->toDateString()}"],
                'title' => 'Follow-up due '.$this->day($course->follow_up_on, $now).': course #'.$course->id,
                'message' => ucfirst($course->purpose).' course ('.$course->medicines->map(fn ($m) => $m->medicine?->name)->implode(', ').') is due a follow-up check.',
                'link' => route('treatment-courses.show', $course), 'severity' => 'info',
                'responsible' => array_filter([(int) $course->responsible_user_id]),
            ]));

        VetExamination::with(['pig', 'healthRecord', 'visit'])->whereNull('voided_at')->whereNotNull('follow_up_on')
            ->whereDate('follow_up_on', '>=', $now->toDateString())->whereDate('follow_up_on', '<=', $until)
            ->whereHas('pig', fn ($q) => $q->whereNull('pigs_management.deleted_at'))
            ->where(fn ($q) => $q->whereNull('health_record_id')->orWhereHas('healthRecord', fn ($c) => $c->whereNull('voided_at')->whereIn('case_status', ['open', 'under_treatment'])))
            ->get()
            ->each(fn (VetExamination $exam) => $reminders->push([
                'type' => 'health.follow_up_due', 'keys' => ["follow_up:exam:{$exam->id}:{$exam->follow_up_on->toDateString()}"],
                'title' => 'Follow-up due '.$this->day($exam->follow_up_on, $now).': '.$exam->pig?->code,
                'message' => "The vet asked to re-check {$exam->pig?->code}".($exam->healthRecord ? " ({$exam->healthRecord->condition})" : '').'.',
                'link' => $exam->healthRecord ? route('health-cases.show', $exam->healthRecord) : route('vet-visits.show', $exam->vet_visit_id),
                'severity' => 'info', 'responsible' => array_filter([(int) $exam->healthRecord?->responsible_user_id]),
            ]));

        return $reminders;
    }

    /** One reminder per end date, naming the animals that clear then. */
    private function withdrawalEnding(CarbonImmutable $now): Collection
    {
        $until = $now->addDays((int) config('health.reminders.withdrawal_days_ahead', 2))->toDateString();

        return MedicationAdministration::with('pig')->notVoided()->whereNotNull('withdrawal_ends_on')
            ->whereDate('withdrawal_ends_on', '>=', $now->toDateString())->whereDate('withdrawal_ends_on', '<=', $until)
            ->whereHas('pig', fn ($q) => $q->whereNull('pigs_management.deleted_at'))
            ->get()
            // A pig with a later dose stays restricted; only its last end date counts.
            ->reject(fn ($dose) => MedicationAdministration::where('pig_id', $dose->pig_id)->notVoided()->whereDate('withdrawal_ends_on', '>', $dose->withdrawal_ends_on)->exists())
            ->groupBy(fn ($dose) => $dose->withdrawal_ends_on->toDateString())
            ->map(function (Collection $doses, string $date) use ($now) {
                $pigs = $doses->pluck('pig')->unique('id');
                $clears = CarbonImmutable::parse($date)->addDay();

                return [
                    'type' => 'health.withdrawal_ending', 'keys' => $pigs->map(fn ($pig) => "withdrawal_ending:{$pig->id}:{$date}")->values()->all(),
                    'title' => "Withdrawal ends {$this->day(CarbonImmutable::parse($date), $now)} for {$pigs->count()} animal(s)",
                    'message' => $this->codes($pigs->pluck('code'))." can be sold from {$clears->format('D j M')}.",
                    'link' => route('health.reports', ['tab' => 'stock']), 'severity' => 'info', 'responsible' => [],
                ];
            })->values();
    }

    private function batchesExpiring(CarbonImmutable $now): Collection
    {
        $batches = MedicineBatch::with('medicine')->where('is_active', true)
            ->whereDate('expiry_date', '>=', $now->toDateString())->whereDate('expiry_date', '<=', $now->addDays((int) config('health.reminders.batch_expiry_days_ahead', 30))->toDateString())->get();
        $onHand = $this->stock->onHandByBatch($batches->modelKeys());

        return $batches->filter(fn ($b) => bccomp($onHand[$b->id] ?? '0', '0', 4) > 0)->map(fn (MedicineBatch $batch) => [
            'type' => 'health.batch_expiring', 'keys' => ["batch_expiring:{$batch->id}"],
            'title' => "{$batch->medicine?->name} batch {$batch->batch_number} expires {$batch->expiry_date->format('j M Y')}",
            'message' => $this->qty($onHand[$batch->id])." {$batch->medicine?->stock_unit} left. Use it first; it cannot be used after the expiry date.",
            'link' => route('medicines.show', $batch->medicine_id), 'severity' => 'warning', 'responsible' => [],
        ])->values();
    }

    /** Keyed on the latest batch received, so it reminds again after the next delivery runs low. */
    private function lowStock(): Collection
    {
        $medicines = Medicine::where('is_active', true)->whereNotNull('reorder_level')->get();
        $onHand = $this->stock->onHandByMedicine($medicines->modelKeys());

        return $medicines->filter(fn ($m) => bccomp($onHand[$m->id] ?? '0', '0', 4) > 0 && bccomp($onHand[$m->id] ?? '0', (string) $m->reorder_level, 4) <= 0)->map(fn (Medicine $medicine) => [
            'type' => 'health.medication_low_stock', 'keys' => ["low_stock:{$medicine->id}:".((int) $medicine->batches()->max('id'))],
            'title' => "{$medicine->name} is running low",
            'message' => $this->qty($onHand[$medicine->id] ?? '0')." {$medicine->stock_unit} left (reorder level ".$this->qty((string) $medicine->reorder_level).').',
            'link' => route('medicines.show', $medicine), 'severity' => 'warning', 'responsible' => [],
        ])->values();
    }

    /** A medicine in use that has run out (whether or not it has a reorder level). Again after the next delivery runs out. */
    private function outOfStock(): Collection
    {
        $medicines = Medicine::where('is_active', true)->whereHas('batches')->get();
        $onHand = $this->stock->onHandByMedicine($medicines->modelKeys());

        return $medicines->filter(fn ($m) => bccomp($onHand[$m->id] ?? '0', '0', 4) <= 0)->map(fn (Medicine $medicine) => [
            'type' => 'health.medicine_out_of_stock', 'keys' => ["out_of_stock:{$medicine->id}:".((int) $medicine->batches()->max('id'))],
            'title' => "{$medicine->name} has run out",
            'message' => "No {$medicine->name} is left in stock. Receive a batch before the next dose is due.",
            'link' => route('medicines.show', $medicine), 'severity' => 'critical', 'responsible' => [],
        ])->values();
    }

    /** Scheduled doses in the next two days need more than the usable (unexpired) stock. Once a day per medicine. */
    private function stockShortForDoses(CarbonImmutable $now): Collection
    {
        return app(HealthGlanceService::class)->shortForScheduledDoses($now->subDays(1), $now->endOfDay()->addDays(2), $now)
            ->map(function (array $short) use ($now) {
            ['medicine' => $medicine, 'needed' => $needed, 'usable' => $usable] = $short;

            return [
                'type' => 'health.stock_short', 'keys' => ["stock_short:{$medicine->id}:{$now->toDateString()}"],
                'title' => "Not enough {$medicine->name} for the doses due",
                'message' => "Doses due in the next two days need {$this->qty($needed)} {$medicine->stock_unit}; usable stock is {$this->qty($usable)}.",
                'link' => route('medicines.show', $medicine), 'severity' => 'critical', 'responsible' => [],
            ];
        })->values();
    }

    /** A batch past its expiry that still shows stock: it cannot be used, so dispose of it and adjust the stock. */
    private function batchesExpired(CarbonImmutable $now): Collection
    {
        $batches = MedicineBatch::with('medicine')->where('is_active', true)->whereDate('expiry_date', '<', $now->toDateString())->get();
        $onHand = $this->stock->onHandByBatch($batches->modelKeys());

        return $batches->filter(fn ($b) => bccomp($onHand[$b->id] ?? '0', '0', 4) > 0)->map(fn (MedicineBatch $batch) => [
            'type' => 'health.batch_expired', 'keys' => ["batch_expired:{$batch->id}"],
            'title' => "{$batch->medicine?->name} batch {$batch->batch_number} has expired",
            'message' => $this->qty($onHand[$batch->id])." {$batch->medicine?->stock_unit} expired on {$batch->expiry_date->format('j M Y')}. It cannot be used; dispose of it and record a stock adjustment.",
            'link' => route('medicines.show', $batch->medicine_id), 'severity' => 'critical', 'responsible' => [],
        ])->values();
    }

    /** A course has no doses left. Finishing a course does not close the case (D1), so ask someone to check the animals. */
    private function coursesFinished(CarbonImmutable $now): Collection
    {
        return TreatmentCourse::with(['healthRecord', 'animals.pig', 'medicines.scheduledDoses', 'medicines.medicine'])
            ->where('status', 'completed')->where('updated_at', '>=', $now->subDays(7))->get()
            ->map(function (TreatmentCourse $course) {
                $doses = $course->medicines->flatMap->scheduledDoses;
                $case = $course->healthRecord;
                $caseOpen = $case && ! $case->voided_at && in_array($case->case_status, ['open', 'under_treatment'], true);

                return [
                    'type' => 'health.course_finished', 'keys' => ["course_finished:{$course->id}"],
                    'title' => ucfirst($course->purpose).' course #'.$course->id.' finished',
                    'message' => $doses->where('status', ScheduledDose::ADMINISTERED)->count().' dose(s) given, '
                        .$doses->whereIn('status', [ScheduledDose::MISSED, ScheduledDose::CANCELLED])->count().' not given ('
                        .$course->medicines->map(fn ($m) => $m->medicine?->name)->implode(', ').'). '
                        .($caseOpen ? "Check {$this->codes($course->animals->pluck('pig.code'))} and follow up or close the case \"{$case->condition}\"." : 'No further doses are scheduled.'),
                    'link' => $caseOpen ? route('health-cases.show', $case) : route('treatment-courses.show', $course),
                    'severity' => 'info', 'responsible' => array_filter([(int) $course->responsible_user_id, (int) $case?->responsible_user_id]),
                ];
            })->values();
    }

    /** A follow-up date has passed and nothing shows it was done. Sent once per follow-up. */
    private function followUpsOverdue(CarbonImmutable $now): Collection
    {
        $from = $now->subDays(HealthWorklistService::OVERDUE_DAYS)->toDateString();
        $reminders = collect();

        TreatmentCourse::pendingFollowUp()
            ->whereDate('follow_up_on', '>=', $from)->whereDate('follow_up_on', '<', $now->toDateString())->get()
            ->each(fn (TreatmentCourse $course) => $reminders->push([
                'type' => 'health.follow_up_overdue', 'keys' => ["follow_up_overdue:course:{$course->id}:{$course->follow_up_on->toDateString()}"],
                'title' => 'Follow-up overdue: course #'.$course->id,
                'message' => 'The follow-up check was due '.$course->follow_up_on->format('j M').'. Record how the animals are doing.',
                'link' => route('treatment-courses.show', $course), 'severity' => 'warning',
                'responsible' => array_filter([(int) $course->responsible_user_id]),
            ]));

        VetExamination::with(['pig', 'healthRecord'])->whereNull('voided_at')->whereNotNull('follow_up_on')
            ->whereDate('follow_up_on', '>=', $from)->whereDate('follow_up_on', '<', $now->toDateString())
            ->whereHas('pig', fn ($q) => $q->whereNull('pigs_management.deleted_at'))
            ->where(fn ($q) => $q->whereNull('health_record_id')->orWhereHas('healthRecord', fn ($c) => $c->whereNull('voided_at')->whereIn('case_status', ['open', 'under_treatment'])))
            ->whereNotExists(fn ($later) => $later->selectRaw('1')->from('vet_examinations as later')
                ->whereColumn('later.pig_id', 'vet_examinations.pig_id')->whereColumn('later.examined_at', '>', 'vet_examinations.examined_at')->whereNull('later.voided_at'))
            ->get()
            ->reject(fn (VetExamination $exam) => $exam->healthRecord && $exam->healthRecord->followUps()->where('observed_at', '>=', $exam->follow_up_on)->exists())
            ->each(fn (VetExamination $exam) => $reminders->push([
                'type' => 'health.follow_up_overdue', 'keys' => ["follow_up_overdue:exam:{$exam->id}:{$exam->follow_up_on->toDateString()}"],
                'title' => 'Re-check overdue: '.$exam->pig?->code,
                'message' => 'The vet asked for a re-check by '.$exam->follow_up_on->format('j M').($exam->healthRecord ? " ({$exam->healthRecord->condition})" : '').'. Nothing has been recorded since.',
                'link' => $exam->healthRecord ? route('health-cases.show', $exam->healthRecord) : route('vet-visits.show', $exam->vet_visit_id),
                'severity' => 'warning', 'responsible' => array_filter([(int) $exam->healthRecord?->responsible_user_id]),
            ]));

        return $reminders;
    }

    /**
     * Open cases with nothing happening: opened over a week ago, no follow-up
     * in the last week and no doses still scheduled. At most once a week each.
     */
    private function casesNeedingReview(CarbonImmutable $now): Collection
    {
        $cases = app(HealthWorklistService::class)->casesNeedingCheck($now)->with('animals.pig', 'pig')->orderBy('observed_at')->get();
        if ($cases->isEmpty()) {
            return collect();
        }

        // One reminder a week listing them all, so a backlog does not fill the bell.
        $week = $now->format('o-W');

        return collect([[
            'type' => 'health.case_needs_review', 'keys' => $cases->map(fn ($case) => "case_review:{$case->id}:{$week}")->values()->all(),
            'title' => $cases->count() === 1 ? 'Case needs a check: '.$cases->first()->condition : $cases->count().' open cases need a check',
            'message' => 'No follow-up in the last week: '.$cases->take(5)->map(fn (HealthRecord $case) => $case->condition.' ('
                .$this->codes($case->animals->pluck('pig.code')->whenEmpty(fn () => collect([$case->pig?->code]))).')')->implode('; ')
                .($cases->count() > 5 ? ' and '.($cases->count() - 5).' more' : '').'. Record a follow-up, or resolve or close them.',
            'link' => $cases->count() === 1 ? route('health-cases.show', $cases->first()) : route('health.reports', ['tab' => 'cases', 'status' => 'active']),
            'severity' => 'warning', 'responsible' => $cases->pluck('responsible_user_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all(),
        ]]);
    }

    private function liveDoses()
    {
        return ScheduledDose::with(['pig', 'courseMedicine.medicine', 'courseMedicine.course'])
            ->where('status', ScheduledDose::SCHEDULED)
            ->whereHas('courseMedicine.course', fn ($q) => $q->where('status', 'active'))
            ->whereHas('pig', fn ($q) => $q->whereNull('pigs_management.deleted_at'));
    }

    /** One reminder per course medicine and due time, keyed per dose. */
    private function doseSlots(Collection $doses, string $type, string $prefix, \Closure $text): Collection
    {
        return $doses->groupBy(fn ($d) => $d->treatment_course_medicine_id.'|'.$d->due_at->format('Y-m-d H:i'))
            ->map(function (Collection $slot) use ($type, $prefix, $text) {
                $line = $slot->first()->courseMedicine;
                $at = CarbonImmutable::parse($slot->first()->due_at);
                [$title, $message, $severity] = $text($slot, $line, $at);

                return [
                    'type' => $type, 'keys' => $slot->map(fn ($d) => "{$prefix}:{$d->id}")->values()->all(),
                    'title' => $title, 'message' => $message, 'severity' => $severity,
                    'link' => route('health.worklist', ['date' => $at->toDateString()]),
                    'responsible' => array_filter([(int) $line->course?->responsible_user_id]),
                ];
            })->values();
    }

    /** Farm managers, plus whoever is responsible for the item. */
    private function recipientIds(int $organizationId, array $responsible): array
    {
        return User::where('organization_id', $organizationId)->where('active_status', true)->get()
            ->filter(fn (User $u) => $u->isAdmin() || $u->isManager() || $u->hasRoleName('manager') || in_array((int) $u->id, $responsible, true))
            ->modelKeys();
    }

    private function codes(Collection $codes): string
    {
        $codes = $codes->filter()->values();

        return $codes->take(8)->implode(', ').($codes->count() > 8 ? ' and '.($codes->count() - 8).' more' : '');
    }

    private function day(\DateTimeInterface $date, CarbonImmutable $now): string
    {
        $date = CarbonImmutable::parse($date)->startOfDay();

        return match (true) {
            $date->equalTo($now->startOfDay()) => 'today',
            $date->equalTo($now->startOfDay()->addDay()) => 'tomorrow',
            default => $date->format('D j M'),
        };
    }

    private function qty(?string $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.') ?: '0';
    }
}
