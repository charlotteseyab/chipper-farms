<?php

namespace App\Services\Health;

use App\Models\Finance\InventoryBalance;
use App\Models\Finance\InventoryMovement;
use App\Models\HealthRecord;
use App\Models\MedicationAdministration;
use App\Models\MedicationDosingAllocation;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\PigManagement;
use App\Models\ScheduledDose;
use App\Models\TreatmentCourse;
use App\Models\VetExamination;
use App\Models\VetVisit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The at-a-glance cards at the top of each health and medicine page. Each
 * method returns cards (label, value, hint, url, tone) for one page, built
 * from the same rules the lists use, so a card and the list behind it agree.
 */
class HealthGlanceService
{
    public function __construct(
        protected MedicineStockService $stock,
        protected WithdrawalStatusService $withdrawal,
    ) {
    }

    /** Medication log. */
    public function medicationLog(): array
    {
        $today = CarbonImmutable::today();
        $week = MedicationAdministration::notVoided()->whereDate('administration_date', '>=', $today->subDays(6)->toDateString());
        $restricted = $this->withdrawal->restrictedCount();
        $notGiven = ScheduledDose::whereIn('status', [ScheduledDose::MISSED, ScheduledDose::CANCELLED])->where('due_at', '>=', $today->subDays(6))->count();

        return [
            $this->card('Doses given today', MedicationAdministration::notVoided()->whereDate('administration_date', $today->toDateString())->count(),
                'one per animal', route('health.reports', ['tab' => 'doses', 'from' => $today->toDateString(), 'status' => 'given'])),
            $this->card('Last 7 days', (clone $week)->count(), (clone $week)->distinct()->count('pig_id').' animals',
                route('health.reports', ['tab' => 'doses', 'from' => $today->subDays(6)->toDateString(), 'status' => 'given'])),
            $this->card('Under withdrawal', $restricted, 'animals not to sell yet', route('health.reports', ['tab' => 'stock']).'#withdrawal', $restricted ? 'warn' : 'good'),
            $this->card('Not given, 7 days', $notGiven, 'missed or cancelled', route('health.reports', ['tab' => 'doses', 'from' => $today->subDays(6)->toDateString()]), $notGiven ? 'warn' : 'neutral'),
        ];
    }

    /** Medicine catalogue. */
    public function catalogue(): array
    {
        $medicines = Medicine::with('batches')->get();
        $active = $medicines->where('is_active', true);
        $onHand = $this->stock->onHandByMedicine($active->modelKeys());
        $out = $active->filter(fn ($m) => $m->batches->isNotEmpty() && bccomp($onHand[$m->id] ?? '0', '0', 4) <= 0);
        $low = $active->filter(fn ($m) => $m->reorder_level !== null && bccomp($onHand[$m->id] ?? '0', '0', 4) > 0
            && bccomp($onHand[$m->id] ?? '0', (string) $m->reorder_level, 4) <= 0);
        [$expiring, $expired] = $this->expiryCounts();
        $value = InventoryBalance::where('item_kind', InventoryMovement::ITEM_KIND_MEDICINE_BATCH)->sum('on_hand_value');

        return [
            $this->card('Medicines in use', $active->count(), ($medicines->count() - $active->count()).' not in use'),
            $this->card('Out of stock or low', $out->count() + $low->count(), $out->count().' out · '.$low->count().' low',
                route('health.reports', ['tab' => 'stock']), $out->isNotEmpty() ? 'alert' : ($low->isNotEmpty() ? 'warn' : 'good')),
            $this->card('Batches expiring', $expiring + $expired, $expired ? "{$expired} already expired" : 'within 30 days',
                route('health.reports', ['tab' => 'stock']), $expired ? 'alert' : ($expiring ? 'warn' : 'good')),
            $this->card('Medicine stock value', number_format((float) $value, 2), 'at cost, not posted to accounts'),
        ];
    }

    /** One medicine's page. */
    public function medicine(Medicine $medicine): array
    {
        $today = CarbonImmutable::today();
        $batches = $medicine->batches()->where('is_active', true)->get();
        $onHand = $this->stock->onHandByBatch($batches->modelKeys());
        $total = $batches->reduce(fn ($s, $b) => bcadd($s, $onHand[$b->id] ?? '0', 4), '0');
        $usable = $batches->reject(fn ($b) => $b->isExpiredOn($today))->reduce(fn ($s, $b) => bcadd($s, $onHand[$b->id] ?? '0', 4), '0');
        $soon = $batches->filter(fn ($b) => bccomp($onHand[$b->id] ?? '0', '0', 4) > 0 && $b->expiry_date->lte($today->addDays(30)));
        $used = MedicationDosingAllocation::whereIn('medicine_batch_id', $medicine->batches()->pluck('id'))
            ->whereHas('event', fn ($q) => $q->where('status', 'recorded')->where('administered_at', '>=', $today->subDays(29)))->get();
        $usedQty = $used->reduce(fn ($s, $a) => bcadd($s, (string) $a->stock_quantity, 4), '0');
        $usedCost = $used->reduce(fn ($s, $a) => bcadd($s, bcmul((string) $a->stock_quantity, (string) $a->unit_cost_snapshot, 4), 4), '0');
        $needed = $this->neededForScheduledDoses($medicine, $today->endOfDay()->addDays(7));
        $low = $medicine->reorder_level !== null && bccomp($total, (string) $medicine->reorder_level, 4) <= 0;
        $unit = $medicine->stock_unit;

        return [
            $this->card('On hand', $this->qty($total).' '.$unit, $medicine->reorder_level !== null ? 'reorder at '.$this->qty((string) $medicine->reorder_level) : null, null,
                bccomp($total, '0', 4) <= 0 ? 'alert' : ($low ? 'warn' : 'good')),
            $this->card('Expiring within 30 days', $soon->count(), $soon->isNotEmpty() ? 'use '.$soon->sortBy('expiry_date')->first()->batch_number.' first' : 'batches', null, $soon->isNotEmpty() ? 'warn' : 'neutral'),
            $this->card('Used, last 30 days', $this->qty($usedQty).' '.$unit, 'cost '.number_format((float) $usedCost, 2)),
            $this->card('Needed, next 7 days', $this->qty($needed).' '.$unit, 'for scheduled doses', route('health.worklist'),
                bccomp($needed, $usable, 4) > 0 ? 'alert' : 'neutral'),
        ];
    }

    /** Vet visits list. */
    public function visits(): array
    {
        $today = CarbonImmutable::today();
        $recent = VetExamination::whereNull('voided_at')->where('examined_at', '>=', $today->subDays(29));
        $followUps = VetExamination::whereNull('voided_at')->whereNotNull('follow_up_on')
            ->whereDate('follow_up_on', '>=', $today->subDays(HealthWorklistService::OVERDUE_DAYS)->toDateString())
            ->whereDate('follow_up_on', '<=', $today->addDays(7)->toDateString())
            ->where(fn ($q) => $q->whereNull('health_record_id')->orWhereHas('healthRecord', fn ($c) => $c->whereNull('voided_at')->whereIn('case_status', ['open', 'under_treatment'])));
        $overdue = (clone $followUps)->whereDate('follow_up_on', '<', $today->toDateString())->count();
        $abnormal = (clone $recent)->where('assessment', 'abnormal_findings')->count();

        return [
            $this->card('Visits this month', VetVisit::where('visited_at', '>=', $today->startOfMonth())->count(), $today->format('F')),
            $this->card('Animals examined', (clone $recent)->distinct()->count('pig_id'), 'last 30 days'),
            $this->card('Abnormal findings', $abnormal, (clone $recent)->where('assessment', 'pending_assessment')->count().' awaiting assessment', null, $abnormal ? 'warn' : 'neutral'),
            $this->card('Re-checks due', $followUps->count(), $overdue ? "{$overdue} overdue" : 'next 7 days', route('health.worklist').'#follow-ups', $overdue ? 'alert' : ($followUps->count() ? 'warn' : 'neutral')),
        ];
    }

    /** One vet visit. */
    public function visit(VetVisit $visit): array
    {
        $exams = $visit->examinations()->whereNull('voided_at')->get();
        $planned = $visit->plannedAnimals()->count();
        $notExamined = $visit->plannedAnimals()->whereNotIn('pig_id', $exams->pluck('pig_id'))->count();
        $pending = $exams->where('assessment', 'pending_assessment')->count();

        return [
            $this->card('Examined', $exams->pluck('pig_id')->unique()->count(), $planned ? "{$planned} planned" : 'animals'),
            $this->card('No issue found', $exams->where('assessment', 'no_issue_found')->count(), 'animals', null, 'good'),
            $this->card('Abnormal findings', $exams->where('assessment', 'abnormal_findings')->count(), $exams->whereNotNull('health_record_id')->count().' cases linked', null,
                $exams->where('assessment', 'abnormal_findings')->isNotEmpty() ? 'warn' : 'neutral'),
            $this->card('Still to do', $pending + $notExamined, "{$pending} pending · {$notExamined} not examined", null, ($pending + $notExamined) ? 'warn' : 'good'),
        ];
    }

    /** The worklist for one day. */
    public function worklist(Collection $slots, Collection $overdue, Collection $followUps, CarbonImmutable $day): array
    {
        $doses = $slots->flatMap(fn ($s) => $s['doses']);
        $remaining = $doses->where('status', ScheduledDose::SCHEDULED)->count();
        $late = $overdue->sum(fn ($s) => $s['doses']->count());
        $lateFollowUps = $followUps->filter(fn ($f) => $f['due_on']->lt($day))->count();

        return [
            $this->card('Still to give', $remaining, 'of '.$doses->count().' due '.($day->isToday() ? 'today' : $day->format('j M')), null, $remaining ? 'warn' : 'good'),
            $this->card('Given', $doses->where('status', ScheduledDose::ADMINISTERED)->count(), $doses->whereIn('status', [ScheduledDose::MISSED, ScheduledDose::CANCELLED])->count().' not given', null, 'good'),
            $this->card('Overdue from before', $late, 'never recorded', null, $late ? 'alert' : 'neutral'),
            $this->card('Follow-ups due', $followUps->count(), $lateFollowUps ? "{$lateFollowUps} overdue" : 'courses and vet re-checks', '#follow-ups', $lateFollowUps ? 'alert' : ($followUps->count() ? 'warn' : 'neutral')),
        ];
    }

    /** One health case. */
    public function healthCase(HealthRecord $case, int $dosesGiven, Collection $notGiven): array
    {
        $opened = CarbonImmutable::parse($case->observed_at ?? $case->record_date);
        $ended = $case->closed_at ? CarbonImmutable::parse($case->closed_at) : CarbonImmutable::now();
        $next = ScheduledDose::with('courseMedicine.medicine')->where('status', ScheduledDose::SCHEDULED)
            ->whereHas('courseMedicine.course', fn ($q) => $q->where('health_record_id', $case->id)->where('status', 'active'))->orderBy('due_at')->first();
        $animals = $case->animals->count() ?: 1;
        $closed = in_array($case->case_status, ['resolved', 'closed'], true);

        return [
            $this->card('Status', HealthRecord::CASE_STATUSES[$case->case_status] ?? ucfirst((string) $case->case_status),
                ($closed ? 'after ' : 'open ').$opened->diffInDays($ended).' days', null, $closed ? 'good' : 'warn'),
            $this->card('Animals', $animals, $case->group_label_snapshot ?: ($case->scope === 'group' ? 'group' : 'single animal')),
            $this->card('Doses given', $dosesGiven, $notGiven->count().' not given', null, $notGiven->isNotEmpty() ? 'warn' : 'neutral'),
            $this->card('Next dose', $next ? $next->due_at->format('D j M H:i') : '—', $next?->courseMedicine?->medicine?->name ?? ($closed ? 'case finished' : 'none scheduled'),
                $next ? route('health.worklist', ['date' => $next->due_at->toDateString()]) : null, $next && $next->due_at->isPast() ? 'alert' : 'neutral'),
        ];
    }

    /** One treatment course. */
    public function course(TreatmentCourse $course): array
    {
        $doses = $course->medicines->flatMap->scheduledDoses;
        $next = $doses->where('status', ScheduledDose::SCHEDULED)->sortBy('due_at')->first();
        $notGiven = $doses->whereIn('status', [ScheduledDose::MISSED, ScheduledDose::CANCELLED])->count();

        return [
            $this->card('Animals', $course->animals->count(), ucfirst($course->purpose)),
            $this->card('Given', $doses->where('status', ScheduledDose::ADMINISTERED)->count(), 'of '.$doses->count().' doses', null, 'good'),
            $this->card('Still due', $doses->where('status', ScheduledDose::SCHEDULED)->count(), $next ? 'next '.$next->due_at->format('D j M H:i') : 'none left',
                $next ? route('health.worklist', ['date' => $next->due_at->toDateString()]) : null, $next && $next->due_at->isPast() ? 'alert' : 'neutral'),
            $this->card('Not given', $notGiven, 'missed or cancelled', null, $notGiven ? 'warn' : 'neutral'),
        ];
    }

    /** One pig's health page. */
    public function pig(PigManagement $pig, array $withdrawal): array
    {
        $open = HealthRecord::notVoided()->whereIn('case_status', ['open', 'under_treatment'])
            ->where(fn ($q) => $q->where('pig_id', $pig->id)->orWhereHas('animals', fn ($a) => $a->where('pig_id', $pig->id)))->get();
        $next = ScheduledDose::with('courseMedicine.medicine')->where('pig_id', $pig->id)->where('status', ScheduledDose::SCHEDULED)
            ->whereHas('courseMedicine.course', fn ($q) => $q->where('status', 'active'))->orderBy('due_at')->first();
        $exam = VetExamination::where('pig_id', $pig->id)->whereNull('voided_at')->latest('examined_at')->first();
        $sellable = match (true) {
            $withdrawal['restricted'] => ['From '.$withdrawal['until']->addDay()->format('j M'), 'under withdrawal', 'alert'],
            $withdrawal['cautions']->isNotEmpty() => ['Check label', 'a recent dose has no withdrawal recorded', 'warn'],
            default => ['Yes', 'no withdrawal applies', 'good'],
        };

        return [
            $this->card('Open cases', $open->count(), $open->pluck('condition')->take(2)->implode(', ') ?: 'none', null, $open->isNotEmpty() ? 'warn' : 'good'),
            $this->card('Next dose', $next ? $next->due_at->format('D j M H:i') : '—', $next?->courseMedicine?->medicine?->name ?? 'none scheduled',
                $next ? route('health.worklist', ['date' => $next->due_at->toDateString()]) : null, $next && $next->due_at->isPast() ? 'alert' : 'neutral'),
            $this->card('Can be sold', $sellable[0], $sellable[1], null, $sellable[2]),
            $this->card('Last vet check', $exam ? $exam->examined_at->format('j M Y') : '—', $exam ? (VetExamination::ASSESSMENTS[$exam->assessment] ?? $exam->assessment) : 'never examined',
                $exam ? route('vet-visits.show', $exam->vet_visit_id).'#exam-'.$exam->id : null),
        ];
    }

    /**
     * Medicines whose scheduled doses between two times need more than the
     * stock that is still in date.
     *
     * @return Collection<int, array{medicine: Medicine, needed: string, usable: string}>
     */
    public function shortForScheduledDoses(CarbonImmutable $from, CarbonImmutable $until, CarbonImmutable $now): Collection
    {
        $medicineIds = ScheduledDose::where('status', ScheduledDose::SCHEDULED)->whereBetween('due_at', [$from, $until])
            ->whereHas('courseMedicine.course', fn ($q) => $q->where('status', 'active'))
            ->join('treatment_course_medicines', 'treatment_course_medicines.id', '=', 'scheduled_doses.treatment_course_medicine_id')
            ->distinct()->pluck('treatment_course_medicines.medicine_id');

        return Medicine::whereKey($medicineIds)->get()->map(function (Medicine $medicine) use ($from, $until, $now) {
            $needed = $this->neededForScheduledDoses($medicine, $until, $from);
            $usable = $this->stock->usableBatches($medicine, $now->toDateString())->reduce(fn ($s, $b) => bcadd($s, $b->on_hand, 4), '0');

            return bccomp($needed, $usable, 4) > 0 ? ['medicine' => $medicine, 'needed' => $needed, 'usable' => $usable] : null;
        })->filter()->values();
    }

    /** Stock needed for this medicine's scheduled doses up to a time, in stock units. */
    public function neededForScheduledDoses(Medicine $medicine, CarbonImmutable $until, ?CarbonImmutable $from = null): string
    {
        $doses = ScheduledDose::with('courseMedicine')->where('status', ScheduledDose::SCHEDULED)->where('due_at', '<=', $until)
            ->when($from, fn ($q) => $q->where('due_at', '>=', $from))
            ->whereHas('courseMedicine', fn ($q) => $q->where('medicine_id', $medicine->id))
            ->whereHas('courseMedicine.course', fn ($q) => $q->where('status', 'active'))
            ->whereHas('pig', fn ($q) => $q->whereNull('pigs_management.deleted_at'))->get();
        $doseUnits = $doses->reduce(fn ($s, $d) => bcadd($s, (string) $d->courseMedicine->dose_amount, 8), '0');

        return bcdiv($doseUnits, (string) $medicine->dose_units_per_stock_unit, 4);
    }

    /** @return array{0: int, 1: int} batches with stock expiring within 30 days, and already expired */
    public function expiryCounts(): array
    {
        $today = CarbonImmutable::today();
        $batches = MedicineBatch::where('is_active', true)->whereDate('expiry_date', '<=', $today->addDays(30)->toDateString())->get();
        $onHand = $this->stock->onHandByBatch($batches->modelKeys());
        $withStock = $batches->filter(fn ($b) => bccomp($onHand[$b->id] ?? '0', '0', 4) > 0);
        $expired = $withStock->filter(fn ($b) => $b->isExpiredOn($today))->count();

        return [$withStock->count() - $expired, $expired];
    }

    private function card(string $label, mixed $value, ?string $hint = null, ?string $url = null, string $tone = 'neutral'): array
    {
        return ['label' => $label, 'value' => (string) $value, 'hint' => $hint, 'url' => $url, 'tone' => $tone];
    }

    private function qty(string $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.') ?: '0';
    }
}
