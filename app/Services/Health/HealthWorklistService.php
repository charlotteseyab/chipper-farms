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
        $courses = $this->courseFollowUps($day)->when($responsibleUserId, fn ($q, $id) => $q->where('responsible_user_id', $id))
            ->with('medicines.medicine', 'animals.pig')->get()
            ->map(fn (TreatmentCourse $c) => ['kind' => 'course', 'due_on' => CarbonImmutable::parse($c->follow_up_on),
                'title' => ucfirst($c->purpose).' course #'.$c->id.': '.$c->medicines->map(fn ($m) => $m->medicine?->name)->implode(', '),
                'detail' => $c->animals->pluck('pig.code')->take(6)->implode(', '), 'url' => route('treatment-courses.show', $c)]);

        $exams = $this->examFollowUps($day)
            ->when($responsibleUserId, fn ($q, $id) => $q->whereHas('healthRecord', fn ($c) => $c->where('responsible_user_id', $id)))
            ->with(['pig', 'healthRecord', 'visit'])->get()
            ->map(fn (VetExamination $e) => ['kind' => 'examination', 'due_on' => CarbonImmutable::parse($e->follow_up_on),
                'title' => 'Vet re-check: '.$e->pig?->code.($e->healthRecord ? ' ('.$e->healthRecord->condition.')' : ''),
                'detail' => $e->visit?->veterinarian_name ? 'Asked by '.$e->visit->veterinarian_name : null,
                'url' => $e->healthRecord ? route('health-cases.show', $e->healthRecord) : route('vet-visits.show', $e->vet_visit_id)]);

        return $courses->concat($exams)->sortBy('due_on')->values();
    }

    /** @return array{open_cases: int, doses_due: int, follow_ups_due: int, under_withdrawal: int} */
    public function counts(CarbonImmutable $today): array
    {
        return [
            'open_cases' => HealthRecord::notVoided()->whereIn('case_status', ['open', 'under_treatment'])->count(),
            'doses_due' => $this->doses()->where('status', ScheduledDose::SCHEDULED)
                ->whereBetween('due_at', [$today->startOfDay()->subDays(self::OVERDUE_DAYS), $today->endOfDay()])->count(),
            'follow_ups_due' => $this->courseFollowUps($today)->count() + $this->examFollowUps($today)->count(),
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

    private function courseFollowUps(CarbonImmutable $day): Builder
    {
        return TreatmentCourse::where('status', 'active')->whereNotNull('follow_up_on')
            ->whereDate('follow_up_on', '>=', $day->subDays(self::OVERDUE_DAYS)->toDateString())->whereDate('follow_up_on', '<=', $day->toDateString());
    }

    private function examFollowUps(CarbonImmutable $day): Builder
    {
        return VetExamination::whereNull('voided_at')->whereNotNull('follow_up_on')
            ->whereDate('follow_up_on', '>=', $day->subDays(self::OVERDUE_DAYS)->toDateString())->whereDate('follow_up_on', '<=', $day->toDateString())
            ->whereHas('pig', fn ($q) => $q->whereNull('pigs_management.deleted_at'))
            // Done once the case is resolved or closed, or a later examination of the same pig exists.
            ->where(fn ($q) => $q->whereNull('health_record_id')->orWhereHas('healthRecord', fn ($c) => $c->whereNull('voided_at')->whereIn('case_status', ['open', 'under_treatment'])))
            ->whereNotExists(fn ($later) => $later->selectRaw('1')->from('vet_examinations as later')
                ->whereColumn('later.pig_id', 'vet_examinations.pig_id')->whereColumn('later.examined_at', '>', 'vet_examinations.examined_at')
                ->whereNull('later.voided_at'));
    }
}
