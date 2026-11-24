<?php

namespace App\Http\Controllers;

use App\Http\Requests\Health\CorrectMedicationRequest;
use App\Http\Requests\Health\UpdateHealthRecordRequest;
use App\Http\Requests\Health\VoidHealthEntryRequest;
use App\Models\PigManagement;
use App\Models\HealthRecord;
use App\Models\MedicationAdministration;
use App\Services\Health\HealthEntryDateGuard;
use App\Services\Health\HealthRecordService;
use App\Services\Health\MedicationRecordService;
use App\Services\Health\WithdrawalStatusService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PigHealthController extends Controller
{
    public function index(Request $request)
    {
        // Sold and dead pigs keep their history; a filter brings them back in.
        $includeDeparted = $request->boolean('include_departed');

        $query = PigManagement::with(['healthRecords' => function($q) {
            $q->notVoided()->latest('record_date')->latest('id')->limit(1);
        }, 'groupHealthRecords' => function ($q) {
            $q->notVoided()->latest('record_date')->latest('health_records.id')->limit(1);
        }, 'medicationAdministrations' => function($q) {
            $q->notVoided()->latest('administration_date')->latest('id')->limit(1);
        }])
            ->where(fn ($q) => $this->whereHealthRecords($q, fn ($hr) => $hr->notVoided()))
            ->when($includeDeparted, fn ($q) => $q->withTrashed(), fn ($q) => $q->whereDoesntHave('deaths')->whereDoesntHave('sales'));

        if ($search = $request->get('q')) {
            $query->where(function($q) use ($search) {
                $q->where('ear_tag_number', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('breed', 'like', "%{$search}%");
            });
        }

        // Health filters: condition, severity, date range
        $condition = $request->string('condition')->toString();
        $severity = $request->string('severity')->toString();
        $from = $request->date('from');
        $to = $request->date('to');

        if ($request->string('attention')->toString() === 'open_critical') {
            $query->where(fn ($q) => $this->whereHealthRecords($q, function ($health) {
                $health->notVoided()->whereNull('resolved_at')
                    ->where(function ($status) {
                        $status->whereNull('treatment_status')
                            ->orWhereIn('treatment_status', ['planned', 'ongoing']);
                    })
                    ->where('severity', 'severe');
            }));
        }

        if (($condition || $severity || $from || $to) && $request->string('attention')->toString() !== 'open_critical') {
            $query->where(fn ($q) => $this->whereHealthRecords($q, function($hr) use ($condition, $severity, $from, $to) {
                $hr->notVoided();
                if ($condition) {
                    $hr->where('condition', 'like', "%{$condition}%");
                }
                if ($severity) {
                    $hr->where('severity', $severity);
                }
                if ($from) {
                    $hr->whereDate('record_date', '>=', $from);
                }
                if ($to) {
                    $hr->whereDate('record_date', '<=', $to);
                }
            }));
        }

        $pigs = $query->orderBy('code')->paginate(15)->withQueryString();
        $pigs->each(function ($pig) {
            $pig->setRelation('healthRecords', $pig->healthRecords->merge($pig->groupHealthRecords)
                ->sortByDesc(fn ($record) => $record->record_date->format('Y-m-d').sprintf('%020d', $record->id))->take(1));
        });
        $counts = app(\App\Services\Health\HealthWorklistService::class)->counts(\Carbon\CarbonImmutable::today());

        return view('health.index', compact('pigs', 'counts'));
    }

    public function show(Request $request, PigManagement $healthPig, WithdrawalStatusService $withdrawals, HealthEntryDateGuard $dates)
    {
        $this->authorize('viewHealth', $healthPig);
        $pig = $healthPig;
        $pig->loadMissing('earTagAssignments.tag.color');

        // Voided and corrected entries stay in the lists, marked, so the
        // history is complete; the summary uses only entries that still count.
        // Its own cases and any group case it was part of.
        $healthRecords = HealthRecord::query()
            ->where(fn ($q) => $q->where('pig_id', $pig->id)->orWhereHas('animals', fn ($a) => $a->where('pig_id', $pig->id)))
            ->with('voidedBy')
            ->latest('record_date')
            ->latest('id')
            ->paginate(10, ['*'], 'health_page')
            ->withQueryString();

        $medicationRecords = $pig->medicationAdministrations()
            ->with(['voidedBy', 'correctedBy'])
            ->latest('administration_date')
            ->latest('id')
            ->paginate(10, ['*'], 'medication_page')
            ->withQueryString();

        $lastRecord = $this->recordsForPig($pig)->notVoided()->latest('record_date')->latest('id')->first();
        $lastMedication = $pig->medicationAdministrations()->notVoided()->latest('administration_date')->latest('id')->first();
        $withdrawal = $withdrawals->forPig($pig);
        $hasLeft = $dates->hasLeft($pig);
        $timeline = app(\App\Services\Health\HealthTimelineService::class)->forPig($pig);

        return view('health.show', compact(
            'pig',
            'healthRecords',
            'medicationRecords',
            'lastRecord',
            'lastMedication',
            'withdrawal',
            'hasLeft',
            'timeline'
        ) + [
            'glance' => app(\App\Services\Health\HealthGlanceService::class)->pig($pig, $withdrawal),
            'healthAlerts' => app(\App\Services\Health\HealthAlertService::class)->alerts(\Carbon\CarbonImmutable::now(), pigId: $pig->id),
        ]);
    }

    private function recordsForPig(PigManagement $pig)
    {
        return HealthRecord::where(fn ($q) => $q->where('pig_id', $pig->id)
            ->orWhereHas('animals', fn ($a) => $a->where('pig_id', $pig->id)));
    }

    private function whereHealthRecords($query, \Closure $filter)
    {
        return $query->whereHas('healthRecords', $filter)->orWhereHas('groupHealthRecords', $filter);
    }

    public function updateRecordStatus(UpdateHealthRecordRequest $request, HealthRecord $healthRecord, HealthRecordService $service)
    {
        return $this->amendRecord($request, $healthRecord, $service, 'health_status_updated', 'Health status updated successfully.');
    }

    public function exportPigHealthPdf(PigManagement $healthPig)
    {
        $this->authorize('viewHealth', $healthPig);
        $pig = $healthPig;
        $pig->loadMissing('earTagAssignments.tag.color');

        $healthRecords = $this->recordsForPig($pig)
            ->with('recordedBy')
            ->latest('record_date')
            ->latest('id')
            ->get();
        $medicationRecords = $pig->medicationAdministrations()
            ->with('administeredBy')
            ->latest('administration_date')
            ->latest('id')
            ->get();
        $countedRecords = $healthRecords->whereNull('voided_at');
        $lastRecord = $countedRecords->first();
        $lastMedication = $medicationRecords->whereNull('voided_at')->first();
        $summary = [
            'health_records' => $countedRecords->count(),
            'medications' => $medicationRecords->whereNull('voided_at')->count(),
            'resolved' => $countedRecords->whereNotNull('resolved_at')->count(),
            'active' => $countedRecords->whereNull('resolved_at')->count(),
        ];

        $pdf = Pdf::loadView('health.pdf', compact(
            'pig',
            'healthRecords',
            'medicationRecords',
            'lastRecord',
            'lastMedication',
            'summary'
        ))->setPaper('a4', 'portrait');

        $identifier = $pig->code ?: ($pig->ear_tag_number ?: $pig->id);

        return $pdf->download('health-report-'.$identifier.'-'.now()->format('Y-m-d').'.pdf');
    }

    public function editRecord(HealthRecord $healthRecord)
    {
        $this->authorize('update', $healthRecord);
        $healthRecord->load('pig');

        return view('health.edit', ['record' => $healthRecord, 'pig' => $healthRecord->pig]);
    }

    public function updateRecord(UpdateHealthRecordRequest $request, HealthRecord $healthRecord, HealthRecordService $service)
    {
        return $this->amendRecord($request, $healthRecord, $service, 'health_record_amended', 'Health record updated successfully.');
    }

    public function voidRecord(VoidHealthEntryRequest $request, HealthRecord $healthRecord, HealthRecordService $service)
    {
        try {
            $service->void($request->user(), $healthRecord, $request->validated('void_reason'));
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('health.show', $healthRecord->pig_id)
            ->with('success', 'Health record voided. It stays on the history, marked as void.');
    }

    private function amendRecord(UpdateHealthRecordRequest $request, HealthRecord $record, HealthRecordService $service, string $action, string $success)
    {
        try {
            $service->amend($request->user(), $record, $request->validated(), $action);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('health.show', $record->pig_id)->with('success', $success);
    }

    public function medicationsIndex(Request $request)
    {
        $query = MedicationAdministration::with('pig.earTagAssignments.tag.color')
            ->whereRaw(
                'medication_administrations.id = (select latest_medication.id from medication_administrations as latest_medication where latest_medication.pig_id = medication_administrations.pig_id and latest_medication.voided_at is null order by latest_medication.administration_date desc, latest_medication.id desc limit 1)'
            );
        if ($pigSearch = $request->get('pig_q')) {
            $query->whereHas('pig', function ($pig) use ($pigSearch) {
                $pig->where(function ($match) use ($pigSearch) {
                    $match->where('ear_tag_number', 'like', "%{$pigSearch}%")
                        ->orWhere('code', 'like', "%{$pigSearch}%");
                });
            });
        }
        if ($search = $request->get('q')) {
            $query->where('medication_name', 'like', "%{$search}%");
        }
        $route = $request->string('route')->toString();
        $purpose = $request->string('purpose')->toString();
        $from = $request->date('from');
        $to = $request->date('to');
        if ($route) {
            $query->where('route', 'like', "%{$route}%");
        }
        if ($purpose) {
            $query->where('purpose', 'like', "%{$purpose}%");
        }
        if ($from) {
            $query->whereDate('administration_date', '>=', $from);
        }
        if ($to) {
            $query->whereDate('administration_date', '<=', $to);
        }
        $medications = $query
            ->orderByDesc('administration_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();
        // For the Medication Name dropdown: the farm's medicine catalogue plus
        // any other name already recorded on a dose (older records were typed).
        $medicationNames = \App\Models\Medicine::query()->orderBy('name')->pluck('name')
            ->merge(MedicationAdministration::query()->whereNull('voided_at')->whereNotNull('medication_name')
                ->distinct()->orderBy('medication_name')->limit(500)->pluck('medication_name'))
            ->map(fn ($name) => trim((string) $name))->filter()
            ->unique(fn ($name) => mb_strtolower($name))->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all();

        return view('health.medications-index', compact('medications', 'medicationNames') + ['glance' => app(\App\Services\Health\HealthGlanceService::class)->medicationLog()]);
    }

    public function showMedication(MedicationAdministration $medication)
    {
        $this->authorize('view', $medication);
        $medication->load(['pig', 'voidedBy', 'corrects', 'correctedBy', 'administeredBy']);

        return view('medications.show', compact('medication'));
    }

    /** The "edit" form saves a correction: the original stays, voided and linked. */
    public function editMedication(MedicationAdministration $medication)
    {
        $this->authorize('correct', $medication);

        return view('medications.edit', compact('medication'));
    }

    public function correctMedication(CorrectMedicationRequest $request, MedicationAdministration $medication, MedicationRecordService $service)
    {
        $data = $request->validated();
        try {
            $replacement = $service->correct($request->user(), $medication, $data, $data['correction_reason']);
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('medications.show', $replacement)
            ->with('success', 'Medication record corrected. The original is kept, marked as corrected.');
    }

    public function voidMedication(VoidHealthEntryRequest $request, MedicationAdministration $medication, MedicationRecordService $service)
    {
        try {
            $service->void($request->user(), $medication, $request->validated('void_reason'));
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('medications.show', $medication)
            ->with('success', 'Medication record voided. Its withdrawal no longer applies.');
    }
}
