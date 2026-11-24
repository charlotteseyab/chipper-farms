<?php

namespace App\Http\Controllers\Health;

use App\Http\Controllers\Controller;
use App\Http\Requests\Health\CloseHealthCaseRequest;
use App\Http\Requests\Health\HealthFollowUpRequest;
use App\Http\Requests\Health\StoreHealthCaseRequest;
use App\Models\HealthRecord;
use App\Models\MedicationAdministration;
use App\Models\PigManagement;
use App\Models\Room;
use App\Models\User;
use App\Services\Health\HealthEntryDateGuard;
use App\Services\Health\HealthPigFinder;
use App\Services\Health\HealthRecordService;
use Illuminate\Http\Request;

/** Health cases: open (one animal or a group), follow up, resolve or close, reopen. */
class HealthCaseController extends Controller
{
    public function create(Request $request, HealthPigFinder $finder, HealthEntryDateGuard $dates, ?PigManagement $healthPig = null)
    {
        abort_unless($request->user()->hasPermission('manage_health_records'), 403);

        return view('health.cases.create', [
            'pig' => $healthPig,
            'selected' => $healthPig ? [$finder->row($healthPig)] : [],
            'hasLeft' => $healthPig ? $dates->hasLeft($healthPig) : false,
            'mayRecordForDeparted' => $dates->mayRecordForDeparted($request->user()),
            'rooms' => Room::orderBy('name')->get(['id', 'name']),
            'staff' => User::where('organization_id', $request->user()->organization_id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreHealthCaseRequest $request, HealthRecordService $service, ?PigManagement $healthPig = null)
    {
        $data = $request->validated();
        $pigs = PigManagement::withTrashed()->whereKey($data['pig_ids'])->get()->sortBy(fn ($p) => array_search($p->id, $data['pig_ids']))->values()->all();
        $case = $service->openCase($request->user(), $pigs, $data, $data['late_entry_reason'] ?? null);

        // The per-pig form returns to that pig's health page, where the case now appears.
        return $healthPig
            ? redirect()->route('health.show', $healthPig)->with('success', 'Health record added')
            : redirect()->route('health-cases.show', $case)->with('success', 'Health case opened.');
    }

    public function show(HealthRecord $healthRecord)
    {
        $this->authorize('view', $healthRecord);
        $case = $healthRecord->load(['animals.pig', 'followUps.recorder', 'treatmentCourses.medicines.medicine', 'treatmentCourses.animals',
            'vetExamination.visit', 'responsibleUser', 'recordedBy']);
        $animalIds = $case->animals->pluck('pig_id')->push($case->pig_id)->unique();
        $doseQuery = MedicationAdministration::whereIn('pig_id', $animalIds)
            ->where(fn ($q) => $q->where('health_record_id', $case->id)
                ->orWhereDate('administration_date', '>=', ($case->observed_at ?? $case->record_date)->toDateString()))
            ->with('pig')->orderByDesc('administration_date')->orderByDesc('id');
        $doses = (clone $doseQuery)->paginate(10, ['*'], 'doses_page')->withQueryString();
        $dosesGiven = (clone $doseQuery)->notVoided()->count();

        // Scheduled doses from this case's courses that were not given, with the reason.
        $notGiven = \App\Models\ScheduledDose::with(['pig', 'courseMedicine.medicine', 'courseMedicine.course'])
            ->whereIn('status', [\App\Models\ScheduledDose::MISSED, \App\Models\ScheduledDose::CANCELLED])
            ->whereHas('courseMedicine.course', fn ($q) => $q->where('health_record_id', $case->id))
            ->orderByDesc('due_at')->get();

        return view('health.cases.show', compact('case', 'doses', 'notGiven') + ['glance' => app(\App\Services\Health\HealthGlanceService::class)->healthCase($case, $dosesGiven, $notGiven)]);
    }

    public function storeFollowUp(HealthFollowUpRequest $request, HealthRecord $healthRecord, HealthRecordService $service)
    {
        return $this->act(fn () => $service->addFollowUp($request->user(), $healthRecord, $request->validated()), $healthRecord, 'Follow-up recorded.');
    }

    public function close(CloseHealthCaseRequest $request, HealthRecord $healthRecord, HealthRecordService $service)
    {
        $data = $request->validated();

        return $this->act(fn () => $service->close($request->user(), $healthRecord, $data), $healthRecord,
            $data['final_status'] === 'closed' ? 'Case closed.' : 'Case resolved.');
    }

    public function reopen(Request $request, HealthRecord $healthRecord, HealthRecordService $service)
    {
        $this->authorize('reopen', $healthRecord);
        $data = $request->validate(['reopen_reason' => ['required', 'string', 'min:5', 'max:2000']]);

        return $this->act(fn () => $service->reopen($request->user(), $healthRecord, $data['reopen_reason']), $healthRecord, 'Case reopened.');
    }

    private function act(\Closure $action, HealthRecord $case, string $success)
    {
        try {
            $action();
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('health-cases.show', $case)->with('success', $success);
    }
}
