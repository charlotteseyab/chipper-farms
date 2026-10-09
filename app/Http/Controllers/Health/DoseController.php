<?php

namespace App\Http\Controllers\Health;

use App\Http\Controllers\Controller;
use App\Http\Requests\Health\RecordDoseRequest;
use App\Models\HealthRecord;
use App\Models\MedicationDosingEvent;
use App\Models\Medicine;
use App\Models\PigManagement;
use App\Models\Room;
use App\Models\ScheduledDose;
use App\Services\Health\CorrectDoseService;
use App\Services\Health\HealthEntryDateGuard;
use App\Services\Health\HealthPigFinder;
use App\Services\Health\MedicineStockService;
use App\Services\Health\RecordDoseService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Record a dose given (taking stock once), view it, and correct or void it. */
class DoseController extends Controller
{
    public function create(Request $request, HealthPigFinder $finder, HealthEntryDateGuard $dates, MedicineStockService $stock, ?PigManagement $healthPig = null)
    {
        $this->authorize('create', MedicationDosingEvent::class);
        $prefill = ['administered_at' => now()->format('Y-m-d\TH:i'), 'amount_basis' => 'per_animal', 'scheduled_dose_ids' => [], 'administered_by' => $request->user()->id];
        $selected = [];
        $case = null;

        $scheduled = ScheduledDose::with(['courseMedicine.course', 'pig'])->whereKey((array) $request->query('scheduled', []))
            ->where('status', ScheduledDose::SCHEDULED)->get();
        if ($scheduled->isNotEmpty()) {
            $line = $scheduled->first()->courseMedicine;
            $scheduled = $scheduled->where('treatment_course_medicine_id', $line->id)->unique('pig_id');
            $prefill = array_merge($prefill, ['medicine_id' => $line->medicine_id, 'dose_amount' => $this->trim($line->dose_amount),
                'route' => $line->route, 'purpose' => ucfirst($line->course->purpose), 'scheduled_dose_ids' => $scheduled->modelKeys()]);
            $selected = $scheduled->map(fn ($d) => $finder->row($d->pig))->values()->all();
            $case = $line->course->healthRecord;
        } elseif ($request->filled('case')) {
            $case = HealthRecord::with('animals.pig')->findOrFail($request->integer('case'));
            $selected = $case->animals->pluck('pig')->filter(fn ($p) => $p && ! $p->trashed())->map(fn ($p) => $finder->row($p))->values()->all();
            $prefill['purpose'] = 'Treatment: '.$case->condition;
        } elseif ($pig = $healthPig ?? ($request->filled('pig') ? PigManagement::withTrashed()->find($request->integer('pig')) : null)) {
            $selected = [$finder->row($pig)];
        }

        return view('health.doses.form', $this->formData($request, $stock) + [
            'event' => null, 'pig' => $healthPig, 'case' => $case, 'selected' => $selected, 'prefill' => $prefill,
            'scheduled' => $scheduled, 'hasLeft' => $healthPig ? $dates->hasLeft($healthPig) : false,
            'mayRecordForDeparted' => $dates->mayRecordForDeparted($request->user()),
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function store(RecordDoseRequest $request, RecordDoseService $service, ?PigManagement $healthPig = null)
    {
        $data = $request->validated();
        $event = $service->record($request->user(), $data, $this->pigs($data['pig_ids']));

        return $healthPig
            ? redirect()->route('health.show', $healthPig)->with('success', 'Dose recorded and taken from stock.')
            : redirect()->route('doses.show', $event)->with('success', 'Dose recorded and taken from stock.');
    }

    public function show(MedicationDosingEvent $dosingEvent)
    {
        $this->authorize('view', $dosingEvent);
        $event = $dosingEvent->load(['medicine', 'givenBy', 'recordedBy', 'allocations.batch', 'administrations.pig', 'administrations.healthRecord',
            'administrations.treatmentCourse', 'administrations.scheduledDose', 'correctsEvent']);
        $replacement = MedicationDosingEvent::where('corrects_event_id', $event->id)->first();
        return view('health.doses.show', compact('event', 'replacement'));
    }

    /** The edit form saves a correction: the original is kept and its stock put back. */
    public function edit(Request $request, MedicationDosingEvent $dosingEvent, HealthPigFinder $finder, MedicineStockService $stock)
    {
        $this->authorize('correct', $dosingEvent);
        $event = $dosingEvent->load(['administrations.pig', 'administrations.healthRecord']);
        $first = $event->administrations->first();

        return view('health.doses.form', $this->formData($request, $stock, $event) + [
            'event' => $event, 'pig' => null, 'case' => $first?->healthRecord, 'scheduled' => collect(),
            'selected' => $event->administrations->pluck('pig')->filter()->map(fn ($p) => $finder->row($p))->values()->all(),
            'prefill' => ['medicine_id' => $event->medicine_id, 'administered_at' => $event->administered_at->format('Y-m-d\TH:i'),
                'amount_basis' => 'per_animal', 'dose_amount' => $this->trim($first?->dose_amount), 'route' => $event->route,
                'purpose' => $first?->purpose, 'notes' => $event->notes, 'scheduled_dose_ids' => [], 'administered_by' => $event->administered_by],
            'hasLeft' => false, 'mayRecordForDeparted' => true, 'idempotencyKey' => null,
        ]);
    }

    public function update(RecordDoseRequest $request, MedicationDosingEvent $dosingEvent, CorrectDoseService $service)
    {
        $data = $request->validated();
        try {
            $replacement = $service->correct($request->user(), $dosingEvent, $data, $this->pigs($data['pig_ids']), $data['correction_reason']);
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('doses.show', $replacement)->with('success', 'Dose corrected. The original is kept, its stock was put back and the corrected amount taken.');
    }

    public function void(Request $request, MedicationDosingEvent $dosingEvent, CorrectDoseService $service)
    {
        $this->authorize('correct', $dosingEvent);
        $data = $request->validate(['void_reason' => ['required', 'string', 'min:5', 'max:2000']]);
        try {
            $service->void($request->user(), $dosingEvent, $data['void_reason']);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('doses.show', $dosingEvent)->with('success', 'Dose voided. Its stock was put back and its withdrawal no longer applies.');
    }

    private function formData(Request $request, MedicineStockService $stock, ?MedicationDosingEvent $event = null): array
    {
        $medicines = Medicine::where(fn ($q) => $q->where('is_active', true)->when($event, fn ($q) => $q->orWhere('id', $event->medicine_id)))
            ->orderBy('name')->get();
        $today = now()->toDateString();
        $catalogue = $medicines->mapWithKeys(fn (Medicine $m) => [$m->id => [
            'unit' => $m->dose_unit, 'stock_unit' => $m->stock_unit, 'factor' => (string) $m->dose_units_per_stock_unit,
            'routes' => $m->allowed_routes ?? [], 'withdrawal' => $m->meat_withdrawal_days,
            'batches' => $stock->usableBatches($m, $today)->map(fn ($b) => ['id' => $b->id, 'number' => $b->batch_number,
                'expiry' => $b->expiry_date->format('d M Y'), 'on_hand' => $this->trim($b->on_hand)])->values(),
        ]]);

        $staff = \App\Models\User::where('organization_id', $request->user()->organization_id)->where('active_status', true)
            ->when($event, fn ($q) => $q->orWhere('id', $event->administered_by))->orderBy('name')->get(['id', 'name']);

        return ['medicines' => $medicines, 'catalogue' => $catalogue, 'rooms' => Room::orderBy('name')->get(['id', 'name']), 'staff' => $staff];
    }

    private function pigs(array $ids): array
    {
        return PigManagement::withTrashed()->whereKey($ids)->get()->sortBy(fn ($p) => array_search($p->id, $ids))->values()->all();
    }

    private function trim(mixed $value): string
    {
        return $value === null ? '' : (rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.') ?: '0');
    }
}
