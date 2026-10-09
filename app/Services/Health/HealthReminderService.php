<?php

namespace App\Services\Health;

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
            ->merge($this->followUps($now))
            ->merge($this->withdrawalEnding($now))
            ->merge($this->batchesExpiring($now))
            ->merge($this->lowStock());

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
            fn ($slot, $line, $at) => ["Doses due {$at->format('H:i')}: {$line->medicine?->name}",
                "{$slot->count()} animal(s) on course #{$line->treatment_course_id} are due {$line->medicine?->display_name} at {$at->format('H:i')}: ".$this->codes($slot->pluck('pig.code')).'.', 'info']);
    }

    private function overdueDoses(CarbonImmutable $now): Collection
    {
        $cutoff = $now->subHours((int) config('health.reminders.overdue_after_hours', 2));
        $since = $now->subDays((int) config('health.reminders.overdue_lookback_days', 7));

        return $this->doseSlots($this->liveDoses()->whereBetween('due_at', [$since, $cutoff])->get(), 'health.dose_overdue', 'dose_overdue',
            fn ($slot, $line, $at) => ["Overdue: {$line->medicine?->name} due {$at->format('D j M H:i')}",
                "{$slot->count()} dose(s) of {$line->medicine?->display_name} on course #{$line->treatment_course_id} have not been recorded: "
                .$this->codes($slot->pluck('pig.code')).'. Record them as given, or mark them missed with a reason.', 'warning']);
    }

    private function followUps(CarbonImmutable $now): Collection
    {
        $until = $now->addDays((int) config('health.reminders.follow_up_days_ahead', 1))->toDateString();
        $reminders = collect();

        TreatmentCourse::with('medicines.medicine')->where('status', 'active')->whereNotNull('follow_up_on')
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

        return $medicines->filter(fn ($m) => bccomp($onHand[$m->id] ?? '0', (string) $m->reorder_level, 4) <= 0)->map(fn (Medicine $medicine) => [
            'type' => 'health.medication_low_stock', 'keys' => ["low_stock:{$medicine->id}:".((int) $medicine->batches()->max('id'))],
            'title' => "{$medicine->name} is running low",
            'message' => $this->qty($onHand[$medicine->id] ?? '0')." {$medicine->stock_unit} left (reorder level ".$this->qty((string) $medicine->reorder_level).').',
            'link' => route('medicines.show', $medicine), 'severity' => 'warning', 'responsible' => [],
        ])->values();
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
