<?php

namespace App\Http\Controllers\Health;

use App\Http\Controllers\Controller;
use App\Http\Requests\Health\TreatmentCourseRequest;
use App\Models\HealthRecord;
use App\Models\Medicine;
use App\Models\PigManagement;
use App\Models\Room;
use App\Models\TreatmentCourse;
use App\Models\User;
use App\Services\Health\HealthPigFinder;
use App\Services\Health\TreatmentCourseService;
use Illuminate\Http\Request;

/** Prescribe, view, amend and stop treatment and preventive courses. Never moves stock. */
class TreatmentCourseController extends Controller
{
    public function create(Request $request, HealthPigFinder $finder)
    {
        $this->authorize('create', TreatmentCourse::class);
        $case = $request->filled('case') ? HealthRecord::with('animals.pig')->findOrFail($request->query('case')) : null;
        $pig = $request->filled('pig') ? PigManagement::findOrFail($request->query('pig')) : null;
        $selected = $case
            ? $case->animals->pluck('pig')->filter(fn ($p) => $p && ! $p->trashed())->map(fn ($p) => $finder->row($p))->values()->all()
            : ($pig ? [$finder->row($pig)] : []);

        return view('health.courses.form', $this->formData($request) + ['case' => $case, 'course' => null, 'selected' => $selected]);
    }

    public function store(TreatmentCourseRequest $request, TreatmentCourseService $service)
    {
        $data = $request->validated();
        $course = $service->prescribe($request->user(), $data, $this->pigs($data['pig_ids']), $data['medicines']);

        return redirect()->route('treatment-courses.show', $course)->with('success', 'Course prescribed. Its doses are scheduled; nothing is taken from stock until a dose is recorded.');
    }

    public function show(TreatmentCourse $treatmentCourse)
    {
        $this->authorize('view', $treatmentCourse);
        $course = $treatmentCourse->load(['medicines.medicine', 'medicines.scheduledDoses.pig', 'medicines.scheduledDoses.administration', 'animals.pig', 'healthRecord', 'amendedFrom', 'responsibleUser']);
        $amendedBy = TreatmentCourse::where('amended_from_course_id', $course->id)->first();

        return view('health.courses.show', compact('course', 'amendedBy'));
    }

    public function edit(Request $request, TreatmentCourse $treatmentCourse, HealthPigFinder $finder)
    {
        $this->authorize('change', $treatmentCourse);
        $course = $treatmentCourse->load(['medicines', 'animals.pig', 'healthRecord']);

        return view('health.courses.form', $this->formData($request) + [
            'case' => $course->healthRecord, 'course' => $course,
            'selected' => $course->animals->pluck('pig')->filter(fn ($p) => $p && ! $p->trashed())->map(fn ($p) => $finder->row($p))->values()->all(),
        ]);
    }

    public function update(TreatmentCourseRequest $request, TreatmentCourse $treatmentCourse, TreatmentCourseService $service)
    {
        $data = $request->validated();
        try {
            $new = $service->amend($request->user(), $treatmentCourse, $data, $this->pigs($data['pig_ids']), $data['medicines'], $data['amendment_reason']);
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('treatment-courses.show', $new)->with('success', 'Course amended. The earlier version is kept and its remaining doses were cancelled.');
    }

    public function stop(Request $request, TreatmentCourse $treatmentCourse, TreatmentCourseService $service)
    {
        $this->authorize('change', $treatmentCourse);
        $data = $request->validate(['stop_reason' => ['required', 'string', 'min:5', 'max:2000']]);
        try {
            $service->stop($request->user(), $treatmentCourse, $data['stop_reason']);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('treatment-courses.show', $treatmentCourse)->with('success', 'Course stopped. Its remaining doses were cancelled.');
    }

    private function pigs(array $ids): array
    {
        return PigManagement::withTrashed()->whereKey($ids)->get()->all();
    }

    private function formData(Request $request): array
    {
        return [
            'medicines' => Medicine::where('is_active', true)->orderBy('name')->get(),
            'rooms' => Room::orderBy('name')->get(['id', 'name']),
            'staff' => User::where('organization_id', $request->user()->organization_id)->orderBy('name')->get(['id', 'name']),
        ];
    }
}
