<?php

namespace App\Http\Controllers;

use App\Domain\Events\Lifecycle\PigBirthRecorded;
use App\Domain\Events\Support\SourceReference;
use App\Models\ActivityLog;
use App\Models\Farrowing;
use App\Models\Mating;
use App\Models\PigManagement;
use App\Services\DomainEvents\DispatchDomainEvent;
use App\Services\Stock\PigRelationshipAnalysisService;
use App\Support\CurrentOrganization;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;

/**
 * Mating planning + management + gestation tracking + farrowing recording.
 *
 * Extracted from PigManagementController — lines 864, 912, 992, 1014, 1021,
 * 1055, 1084, 1112, 1187, 1193, 1296, 1326, 1333.
 *
 * The Mating model owns the state machine (`recordPregnancyCheck`,
 * `markAsCompleted`, `markAsCancelled`), so the controller is mostly a
 * thin HTTP wrapper that validates input and calls model methods.
 *
 * No new services for this batch — existing model-level write methods are
 * the write path. A Phase 1+ ticket may add a RecordFarrowingService when
 * finance subscribes to piglet-birth events; until then the transactional
 * farrowing write stays inline (preserving the existing behaviour exactly).
 *
 * No new domain events. Finance-relevant events were wired in earlier
 * batches; mating/farrowing are operational events that finance does not
 * currently subscribe to.
 */
class MatingController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected PigRelationshipAnalysisService $relationships,
        protected DispatchDomainEvent $dispatcher,
        protected CurrentOrganization $currentOrganization,
    )
    {
    }

    // ------------------------------------------------------------- mating index / manage

    public function matingIndex()
    {
        $this->authorize('viewAny', Mating::class);
        $allMales   = PigManagement::active()->where('gender', 'M')->get();
        $allFemales = PigManagement::active()->where('gender', 'F')->get();

        $availableMales   = $allMales->filter(fn ($p) => $p->availability_status['available']);
        $availableFemales = $allFemales->filter(fn ($p) => $p->availability_status['available']);

        $males   = $availableMales->map(fn ($p) => $this->pigToOption($p))->values();
        $females = $availableFemales->map(fn ($p) => $this->pigToOption($p))->values();

        return view('mating.index', compact(
            'males', 'females', 'allMales', 'allFemales', 'availableMales', 'availableFemales'
        ));
    }

    protected function pigToOption(PigManagement $pig): array
    {
        $this->authorize('view', $pig);
        $readiness = $pig->breeding_readiness;
        return [
            'id'                   => $pig->id,
            'code'                 => $pig->code,
            'ear_tag_number'       => $pig->ear_tag_number,
            'breed'                => $pig->breed,
            'stock_type'           => $pig->stock_type,
            'current_age_in_days'  => $pig->current_age_in_days,
            'breeding_readiness'   => $readiness,
            'readiness_message'    => $pig->breeding_readiness_message,
            'readiness_badge'      => $pig->breeding_readiness_badge,
        ];
    }

    public function matingManage(Request $request)
    {
        $this->authorize('viewAny', Mating::class);
        $attentionFilter = $request->string('attention')->toString();
        $matings = Mating::with(['male', 'female', 'createdBy', 'checkedBy'])
            ->when($attentionFilter === 'pregnancy_checks_overdue', fn ($query) => $query->pregnancyCheckOverdue())
            ->when($attentionFilter === 'pregnancy_followups_overdue', fn ($query) => $query->pregnancyFollowUpOverdue())
            ->orderBy('planned_date', 'desc')
            ->paginate(10)
            ->withQueryString();

        $stats = [
            'total'                     => Mating::count(),
            'planned'                   => Mating::planned()->count(),
            'completed'                 => Mating::completed()->count(),
            'cancelled'                 => Mating::where('status', 'cancelled')->count(),
            'overdue'                   => Mating::overdue()->count(),
            'upcoming'                  => Mating::upcoming()->count(),
            'pregnancy_checks_due'      => Mating::pregnancyCheckDue()->count(),
            'pregnancy_checks_overdue'  => Mating::pregnancyCheckOverdue()->count(),
            'uncertain'                 => Mating::uncertainPregnancy()->count(),
            'follow_ups_due'            => Mating::query()->pregnancyFollowUpDue()->count(),
            'follow_ups_overdue'        => Mating::query()->pregnancyFollowUpOverdue()->count(),
            'pregnant'                  => Mating::pregnant()->count(),
            'not_pregnant'              => Mating::notPregnant()->count(),
        ];

        return view('mating.manage', compact('matings', 'stats', 'attentionFilter'));
    }

    // ------------------------------------------------------------- write paths

    public function planMating(Request $request)
    {
        $this->authorize('create', Mating::class);
        $data = $request->validate([
            'male_id'      => ['required', 'exists:pigs_management,id'],
            'female_id'    => ['required', 'exists:pigs_management,id'],
            'planned_date' => ['required', 'date'],
            'notes'        => ['nullable', 'string', 'max:1000'],
        ]);

        $male   = PigManagement::findOrFail($data['male_id']);
        $female = PigManagement::findOrFail($data['female_id']);

        $maleReadiness   = $male->breeding_readiness;
        $femaleReadiness = $female->breeding_readiness;

        if (in_array($maleReadiness['age_status'], ['too_young', 'too_old'], true)) {
            return back()->withErrors(['mating' => "Male {$male->code} is not ready for breeding: {$maleReadiness['age_message']}"]);
        }
        if (in_array($femaleReadiness['age_status'], ['too_young', 'too_old'], true)) {
            return back()->withErrors(['mating' => "Female {$female->code} is not ready for breeding: {$femaleReadiness['age_message']}"]);
        }

        $warnings = [];
        if ($maleReadiness['factors']['weight']['status']   === 'too_light') {
            $warnings[] = "Male {$male->code}: {$maleReadiness['factors']['weight']['message']}";
        }
        if ($femaleReadiness['factors']['weight']['status'] === 'too_light') {
            $warnings[] = "Female {$female->code}: {$femaleReadiness['factors']['weight']['message']}";
        }

        $relationship = $this->relationships->analyze($data['male_id'], $data['female_id']);
        if ($relationship['risk_level'] === 'critical') {
            return back()->withErrors(['mating' => $relationship['message']]);
        }

        $existingMating = Mating::where('male_id', $data['male_id'])
            ->where('female_id', $data['female_id'])
            ->where('status', 'planned')
            ->first();

        if ($existingMating) {
            return back()->withErrors([
                'mating' => 'A mating between these pigs is already planned for ' . $existingMating->planned_date->format('M d, Y'),
            ]);
        }

        Mating::create([
            'male_id'      => $data['male_id'],
            'female_id'    => $data['female_id'],
            'planned_date' => $data['planned_date'],
            'notes'        => $data['notes'] ?? null,
            'status'       => 'planned',
            'created_by'   => auth()->id(),
        ]);

        $successMessage = "Mating successfully planned for {$male->code} × {$female->code} on "
            . Carbon::parse($data['planned_date'])->format('M d, Y');

        if ($relationship['risk_level'] !== 'none') {
            $successMessage .= ' Warning Note: ' . $relationship['message'];
        }
        if (! empty($warnings)) {
            $successMessage .= ' Weight Weight Warning: ' . implode('; ', $warnings);
        }

        return back()->with('success', $successMessage);
    }

    public function destroyMating($id)
    {
        $mating = Mating::findOrFail($id);
        $this->authorize('delete', $mating);
        $mating->delete();
        return redirect()->route('mating.manage')->with('success', 'Mating record deleted successfully.');
    }

    public function recordPregnancyCheck(Request $request, $id)
    {
        $mating = Mating::findOrFail($id);
        $this->authorize('pregnancyCheck', $mating);

        $data = $request->validate([
            'pregnancy_status'         => ['required', 'in:pregnant,not_pregnant,uncertain'],
            'pregnancy_check_method'   => ['required', 'in:physical_exam,blood_test,ultrasound,behavioral_signs,other'],
            'pregnancy_confidence'     => ['required', 'in:high,medium,low'],
            'pregnancy_check_date'     => ['required', 'date', 'before_or_equal:today'],
            'pregnancy_check_notes'    => ['nullable', 'string', 'max:1000'],
            'pregnancy_follow_up_date' => ['nullable', 'required_if:pregnancy_status,uncertain', 'date', 'after_or_equal:today'],
        ], [
            'pregnancy_follow_up_date.required_if' => 'A follow-up date is required when pregnancy status is uncertain.',
            'pregnancy_follow_up_date.after_or_equal' => 'The follow-up date must be today or a future date.',
        ]);

        $wasUncertain = $mating->pregnancy_status === 'uncertain';

        $success = $mating->recordPregnancyCheck(
            $data['pregnancy_status'],
            $data['pregnancy_check_method'],
            $data['pregnancy_confidence'],
            $data['pregnancy_check_notes'] ?? null,
            $data['pregnancy_check_date'],
            auth()->id(),
            $data['pregnancy_follow_up_date'] ?? null,
        );

        if (! $success) {
            return back()->withErrors(['pregnancy' => 'Failed to record pregnancy check.']);
        }

        $freshMating = $mating->fresh(['male', 'female']);
        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => $data['pregnancy_status'] === 'uncertain'
                ? ($wasUncertain ? 'follow_up_scheduled' : 'uncertain_pregnancy_recorded')
                : 'pregnancy_check_completed',
            'module' => 'breeding',
            'auditable_type' => Mating::class,
            'auditable_id' => $mating->id,
            'record_label' => trim(($freshMating->male->ear_tag_number ?? 'Male') . ' x ' . ($freshMating->female->ear_tag_number ?? 'Female')),
            'description' => $data['pregnancy_status'] === 'uncertain'
                ? 'Pregnancy check was uncertain and a follow-up was scheduled.'
                : 'Pregnancy check result was recorded.',
            'new_values' => [
                'pregnancy_status' => $data['pregnancy_status'],
                'pregnancy_check_date' => $data['pregnancy_check_date'],
                'pregnancy_follow_up_date' => $data['pregnancy_follow_up_date'] ?? null,
            ],
        ]);

        $statusMessage = match ($data['pregnancy_status']) {
            'pregnant'     => 'Pregnancy confirmed. Expected farrowing: ' . $mating->fresh()->expected_farrowing_date->format('M d, Y'),
            'not_pregnant' => 'Not pregnant. Consider re-mating or health check.',
            'uncertain'    => 'Pregnancy uncertain. Follow-up check scheduled for ' . Carbon::parse($data['pregnancy_follow_up_date'])->format('M d, Y') . '.',
        };

        return back()->with('success', 'Pregnancy check recorded successfully. ' . $statusMessage);
    }

    public function completeMating(Request $request, $id)
    {
        $mating = Mating::findOrFail($id);
        $this->authorize('complete', $mating);

        $data = $request->validate([
            'actual_date'   => ['required', 'date', 'before_or_equal:today'],
            'outcome_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $success = $mating->markAsCompleted(
            auth()->id(),
            $data['actual_date'],
            $data['outcome_notes'] ?? null,
        );

        if (! $success) {
            return back()->withErrors(['mating' => 'Failed to complete mating.']);
        }

        $male               = $mating->male;
        $female             = $mating->female;
        $pregnancyCheckDate = Carbon::parse($data['actual_date'])->addDays(14);

        return back()->with('success',
            "Mating completed for {$male->code} × {$female->code}. "
            . "Pregnancy check recommended on {$pregnancyCheckDate->format('M d, Y')} (14 days from mating)."
        );
    }

    public function cancelMating(Request $request, $id)
    {
        $mating = Mating::findOrFail($id);
        $this->authorize('cancel', $mating);

        if ($mating->status !== 'planned') {
            return back()->withErrors(['mating' => 'Only planned matings can be cancelled.']);
        }

        $male        = $mating->male;
        $female      = $mating->female;
        $plannedDate = $mating->planned_date->format('M d, Y');

        if (! $mating->markAsCancelled('Cancelled by user')) {
            return back()->withErrors(['mating' => 'Failed to cancel mating.']);
        }

        return back()->with('success',
            "Mating cancelled for {$male->code} × {$female->code} planned for {$plannedDate}. "
            . 'Both pigs are now available for breeding.'
        );
    }

    // ------------------------------------------------------------- gestation

    public function gestationIndex(Request $request)
    {
        $this->authorize('viewAny', Mating::class);
        $q          = trim((string) $request->input('q'));
        $filterDate = $request->input('filter_date');
        $attentionFilter = $request->string('attention')->toString();

        $averageGestation = Mating::where('pregnancy_status', 'farrowed')
            ->whereNotNull('actual_farrowing_date')
            ->selectRaw('AVG(DATEDIFF(actual_farrowing_date, actual_date)) as avg_days')
            ->value('avg_days')
            ?? config('breeding.cycle.gestation_days', 114);

        $query = Mating::with(['male', 'female', 'createdBy', 'checkedBy'])
            ->whereIn('pregnancy_status', ['pregnant', 'farrowed'])
            ->when($attentionFilter === 'overdue', fn ($query) => $query
                ->where('pregnancy_status', 'pregnant')
                ->where('expected_farrowing_date', '<', now()));

        if ($q) {
            $query->where(function ($subQuery) use ($q) {
                $subQuery->orWhereHas('female', function ($hasQuery) use ($q) {
                    $hasQuery->where('ear_tag_number', 'like', "%{$q}%")
                        ->orWhere('code', 'like', "%{$q}%");
                })
                ->orWhereHas('male', function ($hasQuery) use ($q) {
                    $hasQuery->where('ear_tag_number', 'like', "%{$q}%")
                        ->orWhere('code', 'like', "%{$q}%");
                });
            });
        }

        if ($filterDate) {
            $query->whereDate('actual_date', $filterDate);
        }

        $pregnantMatings = $query
            ->orderByRaw("CASE WHEN pregnancy_status = 'pregnant' THEN 0 ELSE 1 END")
            ->orderBy('expected_farrowing_date', 'asc')
            ->paginate(10)
            ->withQueryString();

        $overdueFilteredCount = (clone $query)
            ->where('pregnancy_status', 'pregnant')
            ->where('expected_farrowing_date', '<', now())
            ->count();

        $stats = [
            'total_pregnant' => Mating::where('pregnancy_status', 'pregnant')->count(),
            'total_farrowed' => Mating::where('pregnancy_status', 'farrowed')->count(),
            'due_this_week' => Mating::where('pregnancy_status', 'pregnant')
                ->whereBetween('expected_farrowing_date', [now(), now()->addWeek()])
                ->count(),
            'due_next_week' => Mating::where('pregnancy_status', 'pregnant')
                ->whereBetween('expected_farrowing_date', [now()->addWeek(), now()->addWeeks(2)])
                ->count(),
            'overdue' => $overdueFilteredCount,
            'farrowed_this_month' => Mating::where('pregnancy_status', 'farrowed')
                ->whereMonth('actual_farrowing_date', now()->month)
                ->whereYear('actual_farrowing_date', now()->year)
                ->count(),
            'average_gestation' => round($averageGestation),
        ];

        return view('gestation.index', compact('pregnantMatings', 'q', 'stats', 'filterDate', 'attentionFilter'));
    }

    public function destroyGestation(Mating $mating)
    {
        $this->authorize('delete', $mating);
        $mating->delete();
        return redirect()->route('gestation.index')->with('success', 'Gestation record deleted successfully.');
    }

    public function editGestation(Mating $mating)
    {
        $this->authorize('update', $mating);
        $mating->load(['male', 'female', 'createdBy', 'checkedBy']);
        return view('gestation.edit', compact('mating'));
    }

    public function updateGestation(Request $request, Mating $mating)
    {
        $this->authorize('update', $mating);
        $data = $request->validate([
            'actual_date'             => ['required', 'date', 'before_or_equal:today'],
            'expected_farrowing_date' => ['required', 'date', 'after:actual_date'],
            'pregnancy_status'        => ['required', 'in:pregnant,not_pregnant,uncertain,pending,lost_pregnancy,aborted,farrowed'],
            'pregnancy_check_date'    => ['nullable', 'date', 'before_or_equal:today'],
            'pregnancy_check_method'  => ['nullable', 'in:ultrasound,manual,blood_test,other'],
            'pregnancy_check_notes'   => ['nullable', 'string', 'max:1000'],
            'pregnancy_confidence'    => ['nullable', 'in:high,medium,low'],
            'pregnancy_status_date'   => ['nullable', 'date', 'before_or_equal:today'],
            'pregnancy_status_notes'  => ['nullable', 'string', 'max:1000'],
            'expected_piglets'        => ['nullable', 'integer', 'min:1', 'max:25'],
            'notes'                   => ['nullable', 'string', 'max:1000'],
            'outcome_notes'           => ['nullable', 'string', 'max:1000'],
        ]);

        $mating->update($data);

        return redirect()->route('gestation.index')->with('success', 'Gestation record updated successfully!');
    }

    public function updateGestationStatus(Request $request, $id)
    {
        $mating = Mating::findOrFail($id);
        $this->authorize('update', $mating);

        $data = $request->validate([
            'status'      => ['required', 'in:pregnant,lost_pregnancy,aborted'],
            'status_date' => ['required', 'date', 'before_or_equal:today'],
            'notes'       => ['nullable', 'string', 'max:1000'],
        ]);

        $success = $mating->update([
            'pregnancy_status'        => $data['status'],
            'pregnancy_status_date'   => $data['status_date'],
            'pregnancy_status_notes'  => $data['notes'] ?? null,
        ]);

        if (! $success) {
            return back()->withErrors(['status' => 'Failed to update gestation status.']);
        }

        $female        = $mating->female;
        $statusMessage = match ($data['status']) {
            'lost_pregnancy' => "Pregnancy loss recorded for {$female->code}. Sow will become available for re-breeding.",
            'aborted'        => "Abortion recorded for {$female->code}. Sow will become available for re-breeding.",
            'pregnant'       => "Pregnancy status updated for {$female->code}.",
        };

        return back()->with('success', $statusMessage);
    }

    // ------------------------------------------------------------- farrowing

    public function recordFarrowing(Request $request, $id)
    {
        $this->authorize('create', Farrowing::class);
        $mating = Mating::findOrFail($id);

        $data = $request->validate([
            'farrowing_date'        => ['required', 'date', 'before_or_equal:today'],
            'farrowing_time'        => ['nullable', 'date_format:H:i'],
            'total_piglets_born'    => ['required', 'integer', 'min:0', 'max:20'],
            'live_births'           => ['required', 'integer', 'min:0', 'max:20'],
            'stillbirths'           => ['required', 'integer', 'min:0', 'max:20'],
            'number_of_males'       => ['nullable', 'integer', 'min:0', 'max:20'],
            'number_of_females'     => ['nullable', 'integer', 'min:0', 'max:20'],
            'farrowing_type'        => ['required', 'in:natural,assisted,cesarean'],
            'farrowing_difficulty'  => ['required', 'in:easy,moderate,difficult,complicated'],
            'average_birth_weight'  => ['nullable', 'numeric', 'min:0', 'max:5'],
            'sow_condition'         => ['required', 'in:excellent,good,fair,poor'],
            'complications'         => ['nullable', 'string', 'max:1000'],
            'notes'                 => ['nullable', 'string', 'max:1000'],
        ]);

        $totalPiglets = (int) $data['total_piglets_born'];
        $liveBirths   = (int) $data['live_births'];
        $stillbirths  = (int) $data['stillbirths'];
        $males        = (int) $data['number_of_males'];
        $females      = (int) $data['number_of_females'];

        if (($liveBirths + $stillbirths) !== $totalPiglets) {
            return back()->withErrors([
                'piglets' => "Live births ({$liveBirths}) + stillbirths ({$stillbirths}) = "
                    . ($liveBirths + $stillbirths) . " must equal total piglets born ({$totalPiglets}).",
            ])->withInput();
        }

        if ($males > 0 || $females > 0) {
            if (($males + $females) !== $liveBirths) {
                return back()->withErrors([
                    'gender' => "Number of males ({$males}) + females ({$females}) = "
                        . ($males + $females) . " must equal live births ({$liveBirths}).",
                ])->withInput();
            }
        }

        $gestationLength = null;
        if ($mating->mating_date) {
            $gestationLength = Carbon::parse($mating->mating_date)->diffInDays($data['farrowing_date']);
        }

        $farrowing = DB::transaction(function () use ($mating, $data, $gestationLength) {
            $farrowing = Farrowing::create([
                'sow_id'               => $mating->female_id,
                'boar_id'              => $mating->male_id,
                'farrowing_date'       => $data['farrowing_date'],
                'farrowing_time'       => $data['farrowing_time'],
                'total_piglets_born'   => $data['total_piglets_born'],
                'live_births'          => $data['live_births'],
                'stillbirths'          => $data['stillbirths'],
                'number_of_males'      => $data['number_of_males'] ?: 0,
                'number_of_females'    => $data['number_of_females'] ?: 0,
                'average_birth_weight' => $data['average_birth_weight'],
                'farrowing_type'       => $data['farrowing_type'],
                'farrowing_difficulty' => $data['farrowing_difficulty'],
                'gestation_length_days'=> $gestationLength,
                'complications'        => $data['complications'],
                'notes'                => $data['notes'],
                'sow_condition'        => $data['sow_condition'],
                'recorded_by'          => auth()->id(),
                'status'               => 'active',
            ]);

            $mating->update([
                'actual_farrowing_date' => $data['farrowing_date'],
                'total_piglets'         => $data['total_piglets_born'],
                'live_piglets'          => $data['live_births'],
                'stillborn_piglets'     => $data['stillbirths'],
                'farrowing_notes'       => $data['notes'],
                'pregnancy_status'      => 'farrowed',
                'status'                => 'farrowed',
            ]);

            $organizationId = $this->currentOrganization->id();
            if ($organizationId !== null) {
                $sow = $mating->female;
                $this->dispatcher->dispatch(new PigBirthRecorded(
                    source: new SourceReference(
                        module: 'lifecycle',
                        sourceType: Farrowing::class,
                        sourceId: (int) $farrowing->id,
                        organizationId: $organizationId,
                    ),
                    occurredAt: \Carbon\CarbonImmutable::parse($data['farrowing_date']),
                    dispatchedByUserId: auth()->id(),
                    farrowingId: (int) $farrowing->id,
                    sowId: (int) $mating->female_id,
                    groupId: $sow?->group_id ? (int) $sow->group_id : null,
                    liveBirths: (int) $data['live_births'],
                    stillbirths: (int) $data['stillbirths'],
                    averageBirthWeightKg: isset($data['average_birth_weight'])
                        ? (float) $data['average_birth_weight']
                        : null,
                ));
            }

            return $farrowing;
        });

        if (! $farrowing) {
            return back()->withErrors(['farrowing' => 'Failed to record farrowing.']);
        }

        $female      = $mating->female;
        $weaningDate = Carbon::parse($data['farrowing_date'])->addDays(config('breeding.cycle.birth_to_weaning_days'));

        return back()->with('success',
            "Farrowing recorded for {$female->code}! "
            . "{$data['live_births']} live piglets born ({$data['number_of_males']} males, {$data['number_of_females']} females). "
            . "Expected weaning date: {$weaningDate->format('M d, Y')}"
        );
    }
}
