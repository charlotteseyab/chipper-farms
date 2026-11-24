<?php

namespace App\Http\Controllers\Health;

use App\Http\Controllers\Controller;
use App\Http\Requests\Health\HealthProgrammeRequest;
use App\Models\HealthProgramme;
use App\Models\Medicine;
use App\Models\ScheduledDose;
use App\Models\User;
use App\Services\Health\HealthProgrammeService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/** Herd health programmes: routine doses set up once and scheduled for each animal as it qualifies. */
class HealthProgrammeController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('view_health_records'), 403);

        $programmes = HealthProgramme::with('medicine', 'responsibleUser')->orderByDesc('is_active')->orderBy('name')->get();

        // What each programme has waiting, for the list.
        $upcoming = ScheduledDose::query()
            ->where('scheduled_doses.status', ScheduledDose::SCHEDULED)
            ->join('treatment_course_medicines as tcm', 'tcm.id', '=', 'scheduled_doses.treatment_course_medicine_id')
            ->join('treatment_courses as tc', 'tc.id', '=', 'tcm.treatment_course_id')
            ->whereNotNull('tc.health_programme_id')
            ->where('tc.status', 'active')
            ->groupBy('tc.health_programme_id')
            ->selectRaw('tc.health_programme_id as programme_id, count(*) as doses, min(scheduled_doses.due_at) as next_due')
            ->get()
            ->keyBy('programme_id');

        return view('health.programmes.index', compact('programmes', 'upcoming'));
    }

    public function create(Request $request)
    {
        abort_unless($request->user()->hasPermission('manage_treatment_courses'), 403);

        return view('health.programmes.form', $this->formData($request) + ['programme' => null]);
    }

    public function store(HealthProgrammeRequest $request, HealthProgrammeService $service)
    {
        $programme = HealthProgramme::create($request->programme() + [
            'is_active' => true, 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id,
        ]);
        $scheduled = $service->schedule($programme->load('medicine'), CarbonImmutable::now());

        return redirect()->route('health-programmes.index')->with('success', $this->scheduledMessage('Programme saved.', $scheduled));
    }

    public function edit(Request $request, HealthProgramme $healthProgramme)
    {
        abort_unless($request->user()->hasPermission('manage_treatment_courses'), 403);

        return view('health.programmes.form', $this->formData($request) + ['programme' => $healthProgramme]);
    }

    public function update(HealthProgrammeRequest $request, HealthProgramme $healthProgramme, HealthProgrammeService $service)
    {
        $healthProgramme->update($request->programme() + ['updated_by' => $request->user()->id]);
        $scheduled = $service->schedule($healthProgramme->fresh('medicine'), CarbonImmutable::now());

        return redirect()->route('health-programmes.index')->with('success', $this->scheduledMessage(
            'Programme updated. Doses already scheduled keep their date and dose; the change applies to doses scheduled from now on.', $scheduled));
    }

    public function pause(Request $request, HealthProgramme $healthProgramme, HealthProgrammeService $service)
    {
        abort_unless($request->user()->hasPermission('manage_treatment_courses'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']]);

        $cancelled = $service->pause($healthProgramme, $request->user(), $data['reason']);

        return redirect()->route('health-programmes.index')
            ->with('success', "Programme paused. {$cancelled} dose(s) not yet given were cancelled; doses already given stay on record.");
    }

    public function resume(Request $request, HealthProgramme $healthProgramme, HealthProgrammeService $service)
    {
        abort_unless($request->user()->hasPermission('manage_treatment_courses'), 403);

        $scheduled = $service->resume($healthProgramme, $request->user(), CarbonImmutable::now());

        return redirect()->route('health-programmes.index')->with('success', $this->scheduledMessage('Programme resumed.', $scheduled));
    }

    private function scheduledMessage(string $lead, int $scheduled): string
    {
        return $lead.' '.($scheduled > 0
            ? "{$scheduled} dose(s) scheduled for the next ".HealthProgrammeService::daysAhead().' days.'
            : 'Nothing is due in the next '.HealthProgrammeService::daysAhead().' days; doses are scheduled automatically as animals qualify.');
    }

    private function formData(Request $request): array
    {
        return [
            'medicines' => Medicine::where('is_active', true)->orderBy('name')->get(),
            'staff' => User::where('organization_id', $request->user()->organization_id)->where('active_status', true)->orderBy('name')->get(['id', 'name']),
        ];
    }
}
