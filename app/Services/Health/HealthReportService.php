<?php

namespace App\Services\Health;

use App\Models\Finance\InventoryMovement;
use App\Models\HealthRecord;
use App\Models\MedicationAdministration;
use App\Models\MedicationDosingAllocation;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\ScheduledDose;
use App\Models\VetExamination;
use App\Models\VetVisit;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The health reports. Each report is a query plus a row mapper, so the page
 * and the CSV export list exactly the same rows; exports stream with lazy()
 * instead of loading everything. All queries are scoped to the organisation
 * by the models' global scope. Mortality and morbidity rates are not built
 * until their definitions are agreed (D11).
 */
class HealthReportService
{
    /** Longest date range a report covers, in days. */
    public const MAX_RANGE_DAYS = 366;

    public const TABS = [
        'cases' => 'Cases and outcomes',
        'doses' => 'Doses given and missed',
        'usage' => 'Medicine usage and cost',
        'stock' => 'Stock, expiry and withdrawal',
        'visits' => 'Vet visits',
    ];

    public const CASE_STATUSES = ['active' => 'Open or under treatment', 'open' => 'Open', 'under_treatment' => 'Under treatment',
        'resolved' => 'Resolved', 'closed' => 'Closed', 'voided' => 'Voided'];

    public function __construct(protected MedicineStockService $stock)
    {
    }

    /**
     * From/to from the request, defaulting to the last 30 days. A range over a
     * year is shortened from the start, and the old "days" parameter is capped.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: bool}  from, to, whether it was shortened
     */
    public function range(array $filters): array
    {
        $to = ! empty($filters['to']) ? CarbonImmutable::parse($filters['to'])->endOfDay() : CarbonImmutable::today()->endOfDay();
        $days = isset($filters['days']) ? max(1, min(self::MAX_RANGE_DAYS, (int) $filters['days'])) : 30;
        $from = ! empty($filters['from']) ? CarbonImmutable::parse($filters['from'])->startOfDay() : $to->subDays($days)->startOfDay();
        if ($from->gt($to)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }
        $earliest = $to->subDays(self::MAX_RANGE_DAYS)->startOfDay();

        return $from->lt($earliest) ? [$earliest, $to, true] : [$from, $to, false];
    }

    // --- 1. cases ---------------------------------------------------------------

    public function cases(array $f): Builder
    {
        [$from, $to] = $this->range($f);

        return HealthRecord::query()->withTrashed()
            ->with(['pig' => fn ($q) => $q->withTrashed(), 'animals.pig', 'responsibleUser'])
            ->withCount('treatmentCourses')
            ->where(fn ($q) => $q->whereBetween('observed_at', [$from, $to])
                ->orWhere(fn ($q) => $q->whereNull('observed_at')->whereDate('record_date', '>=', $from->toDateString())->whereDate('record_date', '<=', $to->toDateString())))
            ->when($f['condition'] ?? null, fn ($q, $v) => $q->where('condition', 'like', "%{$v}%"))
            ->when($f['severity'] ?? null, fn ($q, $v) => $q->where('severity', $v))
            ->when($f['scope'] ?? null, fn ($q, $v) => $q->where('scope', $v))
            ->when($f['group'] ?? null, fn ($q, $v) => $q->where('group_label_snapshot', 'like', "%{$v}%"))
            ->when($f['pig'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q->whereHas('pig', fn ($p) => $p->withTrashed()->where('code', 'like', "%{$v}%"))
                ->orWhereHas('animals.pig', fn ($p) => $p->withTrashed()->where('code', 'like', "%{$v}%"))))
            ->when($f['status'] ?? null, fn ($q, $v) => match ($v) {
                'voided' => $q->whereNotNull('voided_at'),
                'active' => $q->whereNull('voided_at')->whereIn('case_status', ['open', 'under_treatment']),
                default => $q->whereNull('voided_at')->where('case_status', $v),
            })
            ->orderByDesc('observed_at')->orderByDesc('id');
    }

    public function caseRow(HealthRecord $case): array
    {
        $animals = $case->animals->pluck('pig.code')->filter();

        return [
            'Opened' => ($case->observed_at ?? $case->record_date)?->format('Y-m-d H:i'),
            'Animals' => ($animals->isNotEmpty() ? $animals : collect([$case->pig?->code]))->implode(', '),
            'Group' => $case->group_label_snapshot,
            'Condition' => $case->condition,
            'Severity' => HealthRecord::SEVERITIES[$case->severity] ?? $case->severity,
            'Diagnosis' => $case->diagnosis_status === 'confirmed' ? 'Confirmed' : 'Unconfirmed',
            'Status' => $case->voided_at ? 'Voided' : (HealthRecord::CASE_STATUSES[$case->case_status] ?? $case->case_status),
            'Outcome' => HealthRecord::OUTCOMES[$case->outcome] ?? $case->outcome,
            'Closed' => $case->closed_at?->format('Y-m-d'),
            'Courses' => $case->treatment_courses_count,
            'Responsible' => $case->responsibleUser?->name,
        ];
    }

    // --- 2. doses given, missed and cancelled -------------------------------------

    public function doses(array $f): Builder
    {
        [$from, $to] = $this->range($f);

        return MedicationAdministration::query()->withTrashed()
            ->with(['pig' => fn ($q) => $q->withTrashed(), 'administeredBy', 'dosingEvent.allocations.batch'])
            ->where(fn ($q) => $q->whereBetween('administered_at', [$from, $to])
                ->orWhere(fn ($q) => $q->whereNull('administered_at')->whereDate('administration_date', '>=', $from->toDateString())->whereDate('administration_date', '<=', $to->toDateString())))
            ->when($f['medicine'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q->where('medicine_name_snapshot', 'like', "%{$v}%")->orWhere('medication_name', 'like', "%{$v}%")))
            ->when($f['purpose'] ?? null, fn ($q, $v) => $q->where('purpose', 'like', "%{$v}%"))
            ->when($f['route'] ?? null, fn ($q, $v) => $q->where('route', 'like', "%{$v}%"))
            ->when($f['pig'] ?? null, fn ($q, $v) => $q->whereHas('pig', fn ($p) => $p->withTrashed()->where('code', 'like', "%{$v}%")))
            ->when(($f['status'] ?? null) === 'given', fn ($q) => $q->whereNull('voided_at'))
            ->when(in_array($f['status'] ?? null, ['corrected', 'voided'], true), fn ($q) => $q->where('status', $f['status']))
            ->orderByDesc('administration_date')->orderByDesc('id');
    }

    public function doseRow(MedicationAdministration $dose): array
    {
        return [
            'Given' => ($dose->administered_at ?? $dose->administration_date)?->format('Y-m-d H:i'),
            'Animal' => $dose->pig?->code,
            'Tag that day' => $dose->pig?->tagLabelAt($dose->administered_at ?? $dose->administration_date),
            'Medicine' => $dose->medicine_name_snapshot ?? $dose->medication_name,
            'Dose' => $dose->dose_amount !== null ? $this->qty($dose->dose_amount).' '.$dose->dose_unit : $dose->dosage,
            'Route' => $dose->route,
            'Purpose' => $dose->purpose,
            'Batch' => $dose->dosingEvent?->allocations->pluck('batch.batch_number')->implode(', '),
            'Withdrawal days' => $dose->withdrawal_period_days,
            'Sellable from' => $dose->withdrawal_ends_on?->copy()->addDay()->format('Y-m-d'),
            'Given by' => $dose->administeredBy?->name,
            'Status' => $dose->is_legacy ? ucfirst($dose->status).' (before upgrade, no stock)' : ucfirst($dose->status),
            'Reason' => $dose->void_reason,
        ];
    }

    public function notGiven(array $f): Builder
    {
        [$from, $to] = $this->range($f);

        return ScheduledDose::query()->with(['pig', 'courseMedicine.medicine'])
            ->whereIn('status', in_array($f['status'] ?? null, ['missed', 'cancelled'], true) ? [$f['status']] : [ScheduledDose::MISSED, ScheduledDose::CANCELLED])
            ->whereBetween('due_at', [$from, $to])
            ->when($f['medicine'] ?? null, fn ($q, $v) => $q->whereHas('courseMedicine.medicine', fn ($m) => $m->where('name', 'like', "%{$v}%")))
            ->when($f['pig'] ?? null, fn ($q, $v) => $q->whereHas('pig', fn ($p) => $p->where('code', 'like', "%{$v}%")))
            ->orderByDesc('due_at');
    }

    public function notGivenRow(ScheduledDose $dose): array
    {
        return [
            'Due' => $dose->due_at->format('Y-m-d H:i'), 'Animal' => $dose->pig?->code, 'Medicine' => $dose->courseMedicine?->medicine?->display_name,
            'Course' => '#'.$dose->courseMedicine?->treatment_course_id, 'Status' => ucfirst($dose->status), 'Reason' => $dose->status_reason,
        ];
    }

    // --- 3. medicine usage and cost --------------------------------------------------

    /**
     * Stock taken by doses still on record, grouped by medicine, batch or
     * animal, with cost at the batch's cost when used. The ledger check
     * compares the total with the ledger movements for the same allocations.
     *
     * @return array{rows: Collection, total_cost: string, ledger_matches: bool, allocations: int}
     */
    public function usage(array $f): array
    {
        [$from, $to] = $this->range($f);
        $by = in_array($f['by'] ?? null, ['batch', 'animal', 'group'], true) ? $f['by'] : 'medicine';

        $allocations = MedicationDosingAllocation::with(['batch.medicine', 'event.administrations.pig', 'event.administrations.healthRecord', 'event.administrations.treatmentCourse'])
            ->whereHas('event', fn ($q) => $q->where('status', 'recorded')->whereBetween('administered_at', [$from, $to])
                ->when($f['medicine'] ?? null, fn ($q, $v) => $q->whereHas('medicine', fn ($m) => $m->where('name', 'like', "%{$v}%"))))
            ->get();

        $lines = collect();
        foreach ($allocations as $a) {
            $cost = bcmul((string) $a->stock_quantity, (string) $a->unit_cost_snapshot, 4);
            $medicine = $a->batch?->medicine;
            if ($by !== 'animal') {
                $label = match ($by) {
                    'batch' => $medicine?->display_name.' · batch '.$a->batch?->batch_number,
                    'group' => $this->groupOf($a->event).' · '.$medicine?->display_name,
                    default => $medicine?->display_name,
                };
                $lines->push(['key' => $by.'|'.$label, 'label' => $label,
                    'unit' => $medicine?->stock_unit, 'qty' => (string) $a->stock_quantity, 'cost' => $cost, 'event' => $a->medication_dosing_event_id,
                    'animals' => $a->event->administrations->pluck('pig_id')->all()]);
                continue;
            }
            // Each animal's share of this batch's part of the dose.
            $total = (string) $a->event->total_stock_quantity;
            foreach ($a->event->administrations as $dose) {
                $share = bccomp($total, '0', 4) > 0 ? bcdiv(bcmul((string) $dose->stock_quantity, (string) $a->stock_quantity, 8), $total, 8) : '0';
                $lines->push(['key' => 'p'.$dose->pig_id.'|'.$medicine?->id, 'label' => $dose->pig?->code.' · '.$medicine?->display_name, 'unit' => $medicine?->stock_unit,
                    'qty' => $share, 'cost' => bcmul($share, (string) $a->unit_cost_snapshot, 8), 'event' => $a->medication_dosing_event_id, 'animals' => [$dose->pig_id]]);
            }
        }

        $rows = $lines->groupBy('key')->map(fn (Collection $g) => [
            'Item' => $g->first()['label'],
            'Doses' => $g->pluck('event')->unique()->count(),
            'Animals' => $g->pluck('animals')->flatten()->unique()->count(),
            'Quantity' => $this->qty($g->reduce(fn ($s, $l) => bcadd($s, $l['qty'], 8), '0')).' '.$g->first()['unit'],
            'Cost' => number_format((float) $g->reduce(fn ($s, $l) => bcadd($s, $l['cost'], 8), '0'), 2, '.', ''),
        ])->sortBy('Item')->values();

        $totalQty = $allocations->reduce(fn ($s, $a) => bcadd($s, (string) $a->stock_quantity, 4), '0');
        $ledger = InventoryMovement::where('source_type', InventoryMovement::SOURCE_MEDICATION_DOSING_ALLOCATION)
            ->whereIn('source_id', $allocations->modelKeys())->get()
            ->reduce(fn ($s, $m) => bcsub($s, (string) $m->quantity_delta, 4), '0');

        return [
            'rows' => $rows,
            'total_cost' => number_format((float) $allocations->reduce(fn ($s, $a) => bcadd($s, bcmul((string) $a->stock_quantity, (string) $a->unit_cost_snapshot, 8), 8), '0'), 2, '.', ''),
            'ledger_matches' => bccomp($totalQty, $ledger, 4) === 0,
            'allocations' => $allocations->count(),
        ];
    }

    /**
     * "Group" for usage: what the dose was given for. A case's group name (or
     * its condition for one animal), else the course for preventive doses.
     */
    private function groupOf(\App\Models\MedicationDosingEvent $event): string
    {
        $dose = $event->administrations->first();
        if ($case = $dose?->healthRecord) {
            return $case->group_label_snapshot ?: 'Case: '.$case->condition;
        }
        if ($course = $dose?->treatmentCourse) {
            return ucfirst($course->purpose).' course #'.$course->id;
        }

        return 'Not linked to a case or course';
    }

    // --- 4. stock, expiry and withdrawal ------------------------------------------------

    /** Batches with stock, soonest expiry first, flagged expired or expiring within 30 days. */
    public function batches(): Collection
    {
        $batches = MedicineBatch::with('medicine')->where('is_active', true)->orderBy('expiry_date')->get();
        $onHand = $this->stock->onHandByBatch($batches->modelKeys());
        $today = CarbonImmutable::today();

        return $batches->filter(fn ($b) => bccomp($onHand[$b->id] ?? '0', '0', 4) > 0)->map(fn (MedicineBatch $b) => [
            'Medicine' => $b->medicine?->display_name, 'Batch' => $b->batch_number, 'Expiry' => $b->expiry_date->format('Y-m-d'),
            'On hand' => $this->qty($onHand[$b->id]).' '.$b->medicine?->stock_unit,
            'State' => $b->isExpiredOn($today) ? 'Expired' : ($b->expiry_date->lte($today->addDays(30)) ? 'Expires within 30 days' : 'OK'),
            'medicine_id' => $b->medicine_id,
        ])->values();
    }

    public function lowStock(): Collection
    {
        $medicines = Medicine::where('is_active', true)->whereNotNull('reorder_level')->orderBy('name')->get();
        $onHand = $this->stock->onHandByMedicine($medicines->modelKeys());

        return $medicines->filter(fn ($m) => bccomp($onHand[$m->id] ?? '0', (string) $m->reorder_level, 4) <= 0)->map(fn (Medicine $m) => [
            'Medicine' => $m->display_name, 'On hand' => $this->qty($onHand[$m->id] ?? '0').' '.$m->stock_unit,
            'Reorder level' => $this->qty((string) $m->reorder_level).' '.$m->stock_unit, 'medicine_id' => $m->id,
        ])->values();
    }

    /** Animals on the farm that are inside a withdrawal period today, with the dose that restricts them longest. */
    public function underWithdrawal(): Builder
    {
        return app(WithdrawalStatusService::class)->restrictedAnimals();
    }

    public function withdrawalRow(MedicationAdministration $dose): array
    {
        return [
            'Animal' => $dose->pig?->code, 'Tag' => $dose->pig?->identityAt(), 'Medicine' => $dose->medicine_name_snapshot ?? $dose->medication_name,
            'Given' => $dose->administration_date?->format('Y-m-d'), 'Restricted through' => $dose->withdrawal_ends_on?->format('Y-m-d'),
            'Sellable from' => $dose->withdrawal_ends_on?->copy()->addDay()->format('Y-m-d'), 'pig_id' => $dose->pig_id,
        ];
    }

    // --- 5. vet visits ---------------------------------------------------------------

    public function visits(array $f): Builder
    {
        [$from, $to] = $this->range($f);

        return VetVisit::query()
            ->withCount(['plannedAnimals', 'examinations as examined_count' => fn ($q) => $q->whereNull('voided_at')])
            ->with(['examinations' => fn ($q) => $q->whereNull('voided_at')])
            ->whereBetween('visited_at', [$from, $to])
            ->when($f['vet'] ?? null, fn ($q, $v) => $q->where('veterinarian_name', 'like', "%{$v}%"))
            ->when($f['assessment'] ?? null, fn ($q, $v) => $q->whereHas('examinations', fn ($e) => $e->whereNull('voided_at')->where('assessment', $v)))
            ->orderByDesc('visited_at');
    }

    public function visitRow(VetVisit $visit): array
    {
        $exams = $visit->examinations;

        return [
            'Date' => $visit->visited_at->format('Y-m-d H:i'), 'Vet' => $visit->veterinarian_name, 'Reason' => $visit->reason,
            // Planned animals are listed separately and never counted as examined.
            'Planned' => $visit->planned_animals_count, 'Examined' => $visit->examined_count,
            'No issue found' => $exams->where('assessment', 'no_issue_found')->count(),
            'Abnormal findings' => $exams->where('assessment', 'abnormal_findings')->count(),
            'Pending' => $exams->where('assessment', 'pending_assessment')->count(),
            'Follow-ups pending' => $exams->filter(fn ($e) => $e->follow_up_on && $e->follow_up_on->gte(CarbonImmutable::today()))->count(),
            'Status' => ucfirst($visit->status),
        ];
    }

    /** One row per animal examined, for the CSV. */
    public function examinations(array $f): Builder
    {
        [$from, $to] = $this->range($f);

        return VetExamination::query()->with(['visit', 'pig' => fn ($q) => $q->withTrashed(), 'healthRecord'])
            ->whereNull('voided_at')
            ->whereHas('visit', fn ($q) => $q->whereBetween('visited_at', [$from, $to])
                ->when($f['vet'] ?? null, fn ($q, $v) => $q->where('veterinarian_name', 'like', "%{$v}%")))
            ->when($f['assessment'] ?? null, fn ($q, $v) => $q->where('assessment', $v))
            ->orderByDesc('examined_at');
    }

    public function examinationRow(VetExamination $exam): array
    {
        return [
            'Visit date' => $exam->visit?->visited_at?->format('Y-m-d'), 'Vet' => $exam->visit?->veterinarian_name, 'Animal' => $exam->pig?->code,
            'Tag at visit' => $exam->tag_label_at_visit, 'Assessment' => VetExamination::ASSESSMENTS[$exam->assessment] ?? $exam->assessment,
            'Findings' => $exam->findings, 'Assessment notes' => $exam->assessment_text, 'Vet notes' => $exam->vet_notes,
            'Recommendations' => $exam->recommendations, 'Tests requested' => $exam->tests_requested, 'Test results' => $exam->test_results,
            'Follow-up' => $exam->follow_up_on?->format('Y-m-d') ?? ($exam->follow_up_not_needed ? 'Not needed' : null),
            'Case' => $exam->healthRecord?->condition, 'Repeat' => $exam->repeat_number > 0 ? 'Yes' : 'No',
        ];
    }

    private function qty(string|float|null $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.') ?: '0';
    }
}
