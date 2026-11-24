<?php

namespace App\Http\Controllers\Health;

use App\Http\Controllers\Controller;
use App\Http\Requests\Health\VetExaminationRequest;
use App\Http\Requests\Health\VetVisitRequest;
use App\Models\PigManagement;
use App\Models\Room;
use App\Models\VetExamination;
use App\Models\VetVisit;
use App\Services\Health\HealthPigFinder;
use App\Services\Health\RecordVetVisitService;
use Illuminate\Http\Request;

/** Vet visits and the animals examined on them. */
class VetVisitController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', VetVisit::class);
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d'],
            'vet' => ['nullable', 'string', 'max:255'], 'assessment' => ['nullable', 'in:'.implode(',', array_keys(VetExamination::ASSESSMENTS))],
            'animal' => ['nullable', 'string', 'max:100'],
        ]);
        $visits = VetVisit::query()
            ->withCount(['examinations as examined_count' => fn ($q) => $q->whereNull('voided_at')])
            ->withCount('plannedAnimals')
            ->withCount(['examinations as follow_ups_due' => fn ($q) => $q->whereNull('voided_at')->whereNotNull('follow_up_on')])
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->whereDate('visited_at', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->whereDate('visited_at', '<=', $d))
            ->when($filters['vet'] ?? null, fn ($q, $v) => $q->where('veterinarian_name', 'like', "%{$v}%"))
            ->when($filters['assessment'] ?? null, fn ($q, $a) => $q->whereHas('examinations', fn ($e) => $e->whereNull('voided_at')->where('assessment', $a)))
            ->when($filters['animal'] ?? null, fn ($q, $code) => $q->whereHas('examinations.pig', fn ($p) => $p->withTrashed()->where('code', 'like', "%{$code}%")))
            ->latest('visited_at')->paginate(20)->withQueryString();

        return view('health.visits.index', compact('visits') + ['glance' => app(\App\Services\Health\HealthGlanceService::class)->visits()]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', VetVisit::class);

        return view('health.visits.create', ['rooms' => Room::orderBy('name')->get(['id', 'name'])]);
    }

    public function store(VetVisitRequest $request, RecordVetVisitService $service)
    {
        $data = $request->validated();
        $visit = $service->create($request->user(), $data, $data['planned_pig_ids'] ?? []);

        return redirect()->route('vet-visits.show', $visit)->with('success', 'Visit recorded. Now add each animal the vet examined.');
    }

    public function show(VetVisit $vetVisit)
    {
        $this->authorize('view', $vetVisit);
        $visit = $vetVisit->load(['examinations.pig', 'examinations.healthRecord', 'plannedAnimals.pig', 'room', 'enteredBy']);
        $examinedIds = $visit->examinations->whereNull('voided_at')->pluck('pig_id');
        $notYetExamined = $visit->plannedAnimals->reject(fn ($p) => $examinedIds->contains($p->pig_id));

        $openCases = \App\Models\HealthRecord::with('pig')->whereNull('voided_at')->whereIn('case_status', ['open', 'under_treatment'])
            ->latest('id')->limit(100)->get();
        $rooms = Room::orderBy('name')->get(['id', 'name']);

        return view('health.visits.show', compact('visit', 'notYetExamined', 'openCases', 'rooms') + ['glance' => app(\App\Services\Health\HealthGlanceService::class)->visit($visit)]);
    }

    public function update(VetVisitRequest $request, VetVisit $vetVisit, RecordVetVisitService $service)
    {
        $data = $request->validated();
        $service->update($request->user(), $vetVisit, $data, $data['amendment_reason'] ?? null);

        return redirect()->route('vet-visits.show', $vetVisit)->with('success', 'Visit updated.');
    }

    public function finalise(Request $request, VetVisit $vetVisit, RecordVetVisitService $service)
    {
        $this->authorize('finalise', $vetVisit);
        try {
            $service->finalise($request->user(), $vetVisit);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('vet-visits.show', $vetVisit)->with('success', 'Visit finalised. Later changes need a reason.');
    }

    public function storeExamination(VetExaminationRequest $request, VetVisit $vetVisit, RecordVetVisitService $service)
    {
        $data = $request->validated();
        $pig = PigManagement::withTrashed()->findOrFail($data['pig_id']);
        $service->examine($request->user(), $vetVisit, $pig, $data, $data['amendment_reason'] ?? null);

        return redirect()->route('vet-visits.show', $vetVisit)->with('success', "Examination of {$pig->code} saved.");
    }

    public function voidExamination(Request $request, VetVisit $vetVisit, VetExamination $examination, RecordVetVisitService $service)
    {
        $this->authorize('update', $vetVisit);
        abort_unless((int) $examination->vet_visit_id === (int) $vetVisit->id, 404);
        $data = $request->validate(['void_reason' => ['required', 'string', 'min:5', 'max:2000']]);
        try {
            $service->voidExamination($request->user(), $examination, $data['void_reason']);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('vet-visits.show', $vetVisit)->with('success', 'Examination voided. It stays on the visit, marked void.');
    }
}
