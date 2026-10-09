<?php

namespace App\Services\Health;

use App\Models\HealthRecord;
use App\Models\MedicationAdministration;
use App\Models\PigManagement;
use App\Models\TreatmentCourse;
use App\Models\VetExamination;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * One pig's health history in date order: vet examinations, cases (its own
 * and group cases it was part of), follow-ups, courses, doses, corrections,
 * withdrawal ends and how it left the farm. Each entry shows the tag the
 * animal wore that day; history follows the animal, never the tag.
 */
class HealthTimelineService
{
    /** @return Collection<int, array{at: CarbonImmutable, kind: string, title: string, detail: ?string, tag: ?string, url: ?string, struck: bool}> */
    public function forPig(PigManagement $pig): Collection
    {
        $events = collect();
        $add = function (mixed $at, string $kind, string $title, ?string $detail = null, ?string $url = null, bool $struck = false) use ($events, $pig) {
            if ($at === null) {
                return;
            }
            $at = CarbonImmutable::parse($at);
            $events->push(['at' => $at, 'kind' => $kind, 'title' => $title, 'detail' => $detail,
                'tag' => $pig->tagLabelAt($at), 'url' => $url, 'struck' => $struck]);
        };

        foreach (VetExamination::with('visit')->where('pig_id', $pig->id)->get() as $exam) {
            $add($exam->examined_at, 'examination', 'Vet examination: '.(VetExamination::ASSESSMENTS[$exam->assessment] ?? $exam->assessment)
                .($exam->repeat_number > 0 ? ' (repeat)' : ''),
                trim(($exam->visit?->veterinarian_name ? 'By '.$exam->visit->veterinarian_name.'. ' : '').($exam->findings ?? '')),
                route('vet-visits.show', $exam->vet_visit_id).'#exam-'.$exam->id, (bool) $exam->voided_at);
        }

        $cases = HealthRecord::with('followUps')
            ->where(fn ($q) => $q->where('pig_id', $pig->id)->orWhereHas('animals', fn ($a) => $a->where('pig_id', $pig->id)))
            ->get();
        foreach ($cases as $case) {
            $url = route('health-cases.show', $case);
            $add($case->observed_at ?? $case->record_date, 'case', ($case->scope === HealthRecord::SCOPE_GROUP ? 'Group case: ' : 'Health case: ').$case->condition,
                trim(($case->severity ? ucfirst($case->severity).'. ' : '').($case->symptoms ?? '')), $url, $case->isVoided());
            foreach ($case->followUps as $followUp) {
                $add($followUp->observed_at, 'follow_up', $followUp->is_closing ? 'Case '.($case->case_status === 'closed' ? 'closed' : 'resolved').': '.(HealthRecord::OUTCOMES[$case->outcome] ?? $case->outcome)
                    : 'Follow-up: '.(\App\Models\HealthFollowUp::RESPONSES[$followUp->response] ?? 'noted'), $followUp->findings, $url, (bool) $followUp->voided_at);
            }
        }

        $courses = TreatmentCourse::with('medicines.medicine')->whereHas('animals', fn ($a) => $a->where('pig_id', $pig->id))->get();
        foreach ($courses as $course) {
            $add($course->medicines->min('start_at') ?? $course->created_at, 'course',
                ucfirst($course->purpose).' course'.($course->amended_from_course_id ? ' (amended)' : '').': '
                .$course->medicines->map(fn ($m) => $m->medicine?->name.' '.rtrim(rtrim((string) $m->dose_amount, '0'), '.').' '.$m->dose_unit)->implode(', '),
                $course->status !== 'active' ? ucfirst($course->status).($course->status_reason ? ': '.$course->status_reason : '') : null,
                route('treatment-courses.show', $course), $course->status === 'stopped');
        }

        foreach (MedicationAdministration::where('pig_id', $pig->id)->get() as $dose) {
            $add($dose->administered_at ?? $dose->administration_date, 'dose', 'Dose: '.($dose->medicine_name_snapshot ?? $dose->medication_name).' '.$dose->dosage,
                $dose->isVoided() ? ucfirst($dose->status).': '.$dose->void_reason : ($dose->withdrawal_period_days === null ? 'No withdrawal recorded' : $dose->withdrawal_period_days.' days withdrawal'),
                $dose->medication_dosing_event_id ? route('doses.show', $dose->medication_dosing_event_id) : route('medications.show', $dose), $dose->isVoided());
            if (! $dose->isVoided() && $dose->withdrawal_ends_on) {
                $add($dose->withdrawal_ends_on->copy()->endOfDay(), 'withdrawal', 'Withdrawal ends ('.($dose->medicine_name_snapshot ?? $dose->medication_name).')',
                    'Can be sold from '.$dose->withdrawal_ends_on->copy()->addDay()->format('d M Y').'.');
            }
        }

        if ($sale = $pig->sales()->first()) {
            $add($sale->sale_date, 'exit', 'Sold');
        }
        if ($death = $pig->deaths()->first()) {
            $add($death->death_date, 'exit', 'Died', $death->death_cause);
        }
        if ($exit = $pig->herdExit()->first()) {
            $add($exit->exit_date, 'exit', $exit->type_label, $exit->reason_label);
        }

        return $events->sortByDesc(fn ($e) => $e['at']->getTimestamp())->values();
    }
}
