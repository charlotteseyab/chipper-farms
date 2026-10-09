<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\EarTag;
use App\Models\EarTagColor;
use App\Services\Audit\AuditTrailService;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EarTagReconciliationController extends Controller
{
    public function index(Request $request, CurrentOrganization $organization)
    {
        $this->adminOnly($request);
        $organizationId = $organization->requireId();

        $query = EarTag::query()->with(['color', 'currentAssignment.pig', 'assignments.pig'])
            ->withCount('assignments')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('color_id'), fn ($q) => $q->where('ear_tag_color_id', $request->integer('color_id')))
            ->when($request->filled('q'), function ($q) use ($request): void {
                $term = trim((string) $request->q);
                $q->where(function ($inner) use ($term): void {
                    $inner->where('tag_number', 'like', "%{$term}%")
                        ->orWhereHas('color', fn ($colors) => $colors->where('name', 'like', "%{$term}%"))
                        ->orWhereHas('assignments.pig', fn ($pigs) => $pigs
                            ->where('code', 'like', "%{$term}%"));
                });
            });

        $stats = collect(EarTag::STATUSES)->mapWithKeys(fn ($status) => [
            $status => EarTag::where('status', $status)->count(),
        ])->all();
        $colors = EarTagColor::orderBy('name')->get();
        $tags = $query->orderByRaw("CASE WHEN status = 'pending_recovery' THEN 0 ELSE 1 END")
            ->orderBy('normalized_tag_number')->paginate(25)->withQueryString();

        return view('settings.ear-tags.reconciliation', compact('tags', 'stats', 'colors', 'organizationId'));
    }

    public function storeColor(Request $request, CurrentOrganization $organization): RedirectResponse
    {
        $this->adminOnly($request);
        $organizationId = $organization->requireId();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'short_code' => ['nullable', 'string', 'max:20'],
            'hex_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);
        $normalized = EarTagColor::normalizeName($data['name']);
        if (EarTagColor::where('normalized_name', $normalized)->exists()) {
            return back()->withErrors(['name' => 'This tag colour already exists for the farm.'])->withInput();
        }

        EarTagColor::create([
            ...$data, 'organization_id' => $organizationId,
            'normalized_name' => $normalized, 'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Tag colour added. It can now be used during reconciliation.');
    }

    public function update(Request $request, EarTag $earTag, AuditTrailService $audit): RedirectResponse
    {
        $this->adminOnly($request);
        abort_unless((int) $earTag->organization_id === (int) $request->user()->organization_id, 404);
        $hasCurrentAssignment = $earTag->currentAssignment()->exists();

        $data = $request->validate([
            'ear_tag_color_id' => ['required', 'integer', Rule::exists('ear_tag_colors', 'id')->where(
                fn ($query) => $query->where('organization_id', $earTag->organization_id)->where('is_active', true)
            )],
            'status' => ['required', Rule::in($hasCurrentAssignment
                ? [EarTag::IN_USE]
                : [EarTag::AVAILABLE, EarTag::PENDING_RECOVERY, EarTag::LOST, EarTag::DAMAGED, EarTag::RESERVED, EarTag::RETIRED])],
            'condition_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $color = EarTagColor::whereKey($data['ear_tag_color_id'])->where('is_active', true)->firstOrFail();

        app(\App\Services\Stock\Tags\EarTagAdministration::class)->reconcile($earTag, $request->user(), $data);

        return back()->with('success', 'Tag reconciliation saved.');
    }

    public function history(Request $request, EarTag $earTag)
    {
        \Illuminate\Support\Facades\Gate::authorize('viewAny', \App\Models\PigManagement::class);
        abort_unless((int) $earTag->organization_id === (int) $request->user()->organization_id, 404);
        $earTag->load(['color', 'assignments' => fn ($q) => $q->orderByRaw('released_at IS NULL DESC')->orderBy('assigned_at')->orderBy('id'), 'assignments.pig']);
        return view('settings.ear-tags.history', compact('earTag'));
    }

    public function correct(Request $request, \App\Models\EarTagAssignment $assignment)
    {
        $this->adminOnly($request);
        abort_unless((int) $assignment->organization_id === (int) $request->user()->organization_id, 404);
        $data = $request->validate([
            'assigned_at' => ['required', 'date'],
            'released_at' => ['nullable', 'date'],
            'release_reason' => ['nullable', 'required_with:released_at', 'string', 'max:30'],
            'correction_reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        app(\App\Services\Stock\Tags\EarTagAdministration::class)->correct($assignment, $request->user(), $data);
        return back()->with('success', 'Assignment correction saved with its audit history.');
    }

    private function adminOnly(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403);
    }
}
