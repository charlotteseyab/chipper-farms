<?php

namespace App\Services\Health;

use App\Models\HealthRecord;
use App\Models\MedicationAdministration;
use App\Models\ScheduledDose;
use App\Models\TreatmentCourse;
use App\Models\VetExamination;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * What needs doing: doses due by day (grouped by course medicine and time),
 * doses overdue from earlier days, and follow-ups due. Also the counts on the
 * health overview cards, so the cards and the lists behind them agree.
 * Only live work is listed: active courses, animals still on the farm.
 */
class HealthWorklistService
{
    /** How far back overdue doses are listed. */
    public const OVERDUE_DAYS = 14;

    /**
     * Doses due on a day, any status, so progress through the day shows.
     *
     * @return Collection<int, array{line: \App\Models\TreatmentCourseMedicine, due_at: CarbonImmutable, doses: Collection}>
     */
    public function dueOn(CarbonImmutable $day, ?int $responsibleUserId = null): Collection
    {
        return $this->slots($this->doses($responsibleUserId)->whereBetween('due_at', [$day->startOfDay(), $day->endOfDay()])->get());
    }

    /** Scheduled doses from earlier days that were never given, missed or cancelled. */
    public function overdueBefore(CarbonImmutable $day, ?int $responsibleUserId = null): Collection
    {
        return $this->slots($this->doses($responsibleUserId)->where('status', ScheduledDose::SCHEDULED)
            ->whereBetween('due_at', [$day->startOfDay()->subDays(self::OVERDUE_DAYS), $day->startOfDay()->subSecond()])->get());
    }

    /** @return Collection<int, array{kind: string, due_on: CarbonImmutable, title: string, detail: ?string, url: string}> */
    public function followUpsDue(CarbonImmutable $day, ?int $responsibleUserId = null): Collection
    {
        return $this->followUpsBetween($day->subDays(self::OVERDUE_DAYS), $day, $responsibleUserId);
    }

    /**
     * Follow-ups still to do with a date in a range, optionally for one pig.
     *
     * @return Collection<int, array{kind: string, due_on: CarbonImmutable, title: string, detail: ?string, url: string}>
     */
    public function followUpsBetween(CarbonImmutable $from, CarbonImmutable $to, ?int $responsibleUserId = null, ?int $pigId = null): Collection
    {
        $courses = $this->courseFollowUps($from, $to)->when($responsibleUserId, fn ($q, $id) => $q->where('responsible_user_id', $id))
            ->when($pigId, fn ($q, $id) => $q->whereHas('animals', fn ($a) => $a->where('pig_id', $id)))
            ->with('medicines.medicine', 'animals.pig')->get()
            ->map(fn (TreatmentCourse $c) => ['kind' => 'course', 'due_on' => CarbonImmutable::parse($c->follow_up_on),
                'title' => ucfirst($c->purpose).' course #'.$c->id.': '.$c->medicines->map(fn ($m) => $m->medicine?->name)->implode(', '),
                'detail' => $c->animals->pluck('pig.code')->take(6)->implode(', '), 'url' => route('treatment-courses.show', $c)]);

        $exams = $this->examFollowUps($from, $to)
            ->when($pigId, fn ($q, $id) => $q->where('pig_id', $id))
            ->when($responsibleUserId, fn ($q, $id) => $q->whereHas('healthRecord', fn ($c) => $c->where('responsible_user_id', $id)))
            ->with(['pig', 'healthRecord', 'visit'])->get()
            ->map(fn (VetExamination $e) => ['kind' => 'examination', 'due_on' => CarbonImmutable::parse($e->follow_up_on),
                'title' => 'Vet re-check: '.$e->pig?->code.($e->healthRecord ? ' ('.$e->healthRecord->condition.')' : ''),
                'detail' => $e->visit?->veterinarian_name ? 'Asked by '.$e->visit->veterinarian_name : null,
                'url' => $e->healthRecord ? route('health-cases.show', $e->healthRecord) : route('vet-visits.show', $e->vet_visit_id)]);

        $checks = $this->caseChecks($from, $to)
            ->when($responsibleUserId, fn ($q, $id) => $q->where('responsible_user_id', $id))
            ->when($pigId, fn ($q, $id) => $q->where(fn ($c) => $c->where('pig_id', $id)->orWhereHas('animals', fn ($a) => $a->where('pig_id', $id))))
            ->with(['pig', 'animals.pig'])->get()
            ->map(fn (HealthRecord $c) => ['kind' => 'case_check', 'due_on' => CarbonImmutable::parse($c->check_on),
                'title' => 'Check recovery: '.($c->scope === HealthRecord::SCOPE_GROUP ? ($c->group_label_snapshot ?: 'group') : $c->pig?->code).' ('.$c->condition.')',
                'detail' => 'Resolved case #'.$c->id.'. Still well: close it. Unwell: reopen it.',
                'url' => route('health-cases.show', $c)]);

        return $courses->concat($exams)->concat($checks)->sortBy('due_on')->values();
    }

    /**
     * Open cases nobody has looked at for a week: no follow-up recorded, and
     * no course with doses still to give.
     */
    public function casesNeedingCheck(CarbonImmutable $now): Builder
    {
        $weekAgo = $now->subDays(7);

        return HealthRecord::query()->notVoided()->whereIn('case_status', ['open', 'under_treatment'])
            ->where(fn ($q) => $q->where('observed_at', '<', $weekAgo)->orWhere(fn ($q) => $q->whereNull('observed_at')->whereDate('record_date', '<', $weekAgo->toDateString())))
            ->whereDoesntHave('followUps', fn ($q) => $q->where('observed_at', '>=', $weekAgo))
            ->whereDoesntHave('treatmentCourses', fn ($q) => $q->where('status', 'active')->whereHas('medicines.scheduledDoses', fn ($d) => $d->where('status', ScheduledDose::SCHEDULED)))
            ->where(fn ($q) => $q->whereHas('pig', fn ($p) => $p->whereNull('pigs_management.deleted_at'))->orWhereHas('animals.pig', fn ($p) => $p->whereNull('pigs_management.deleted_at')));
    }

    /** @return array{open_cases: int, doses_due: int, follow_ups_due: int, under_withdrawal: int} */
    public function counts(CarbonImmutable $today): array
    {
        return [
            'open_cases' => HealthRecord::notVoided()->whereIn('case_status', ['open', 'under_treatment'])->count(),
            'doses_due' => $this->doses()->where('status', ScheduledDose::SCHEDULED)
                ->whereBetween('due_at', [$today->startOfDay()->subDays(self::OVERDUE_DAYS), $today->endOfDay()])->count(),
            'follow_ups_due' => $this->courseFollowUps($today->subDays(self::OVERDUE_DAYS), $today)->count()
                + $this->examFollowUps($today->subDays(self::OVERDUE_DAYS), $today)->count()
                + $this->caseChecks($today->subDays(self::OVERDUE_DAYS), $today)->count(),
            'under_withdrawal' => app(WithdrawalStatusService::class)->restrictedCount($today->toDateString()),
        ];
    }

    private function doses(?int $responsibleUserId = null): Builder
    {
        return ScheduledDose::query()
            ->with(['pig', 'courseMedicine.medicine', 'courseMedicine.course.responsibleUser', 'administration'])
            ->whereHas('courseMedicine.course', fn ($q) => $q->where('status', 'active')
                ->when($responsibleUserId, fn ($q, $id) => $q->where('responsible_user_id', $id)))
            ->whereHas('pig', fn ($q) => $q->whereNull('pigs_management.deleted_at'))
            ->orderBy('due_at');
    }

    private function slots(Collection $doses): Collection
    {
        return $doses->groupBy(fn ($d) => $d->due_at->format('Y-m-d H:i').'|'.$d->treatment_course_medicine_id)
            ->map(fn (Collection $slot) => ['line' => $slot->first()->courseMedicine, 'due_at' => CarbonImmutable::parse($slot->first()->due_at), 'doses' => $slot->sortBy('pig.code')->values()])
            ->values();
    }

    private function courseFollowUps(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return TreatmentCourse::pendingFollowUp()
            ->whereDate('follow_up_on', '>=', $from->toDateString())->whereDate('follow_up_on', '<=', $to->toDateString());
    }

    /**
     * Resolved cases ("recovered, keep watching") whose check date falls in
     * the range. Done once the case is closed or reopened (it is then no
     * longer resolved), or a follow-up is recorded on or after the date.
     */
    private function caseChecks(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return HealthRecord::query()->notVoided()->where('case_status', 'resolved')->whereNotNull('check_on')
            ->whereDate('check_on', '>=', $from->toDateString())->whereDate('check_on', '<=', $to->toDateString())
            ->whereDoesntHave('followUps', fn ($q) => $q->where('is_closing', false)->whereColumn('health_follow_ups.observed_at', '>=', 'health_records.check_on'))
            ->where(fn ($q) => $q->whereHas('pig', fn ($p) => $p->whereNull('pigs_management.deleted_at'))->orWhereHas('animals.pig', fn ($p) => $p->whereNull('pigs_management.deleted_at')));
    }

    private function examFollowUps(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return VetExamination::whereNull('voided_at')->whereNotNull('follow_up_on')
            ->whereDate('follow_up_on', '>=', $from->toDateString())->whereDate('follow_up_on', '<=', $to->toDateString())
            ->whereHas('pig', fn ($q) => $q->whereNull('pigs_management.deleted_at'))
            // Done once the case is resolved or closed, or a later examination of the same pig exists.
            ->where(fn ($q) => $q->whereNull('health_record_id')->orWhereHas('healthRecord', fn ($c) => $c->whereNull('voided_at')->whereIn('case_status', ['open', 'under_treatment'])))
            ->whereNotExists(fn ($later) => $later->selectRaw('1')->from('vet_examinations as later')
                ->whereColumn('later.pig_id', 'vet_examinations.pig_id')->whereColumn('later.examined_at', '>', 'vet_examinations.examined_at')
                ->whereNull('later.voided_at'));
    }
}
