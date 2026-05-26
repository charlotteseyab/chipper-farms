<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ClosePeriodRequest;
use App\Http\Requests\Finance\ReopenPeriodRequest;
use App\Models\FiscalPeriod;
use App\Models\Finance\PeriodCloseRun;
use App\Services\Finance\Close\Exceptions\PeriodCloseException;
use App\Services\Finance\Close\PeriodCloseService;
use App\Support\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Phase 6 — period close workflow UI.
 *
 *   index   — list periods with their close status + latest run.
 *   show    — checklist preview for a period (live validation) + history.
 *   close   — run the close (with optional overrides).
 *   reopen  — reopen a closed/locked period (privileged).
 *
 * Permissions:
 *   view_period_close      — index, show
 *   manage_period_close    — close (soft)
 *   override_period_close  — supply overrides; hard close; reopen
 */
class PeriodCloseController extends Controller
{
    public function __construct(
        protected CurrentOrganization $currentOrg,
        protected PeriodCloseService $closeService,
    ) {
        $this->middleware('auth');
        $this->middleware('permission:view_period_close')->only(['index', 'show']);
        $this->middleware('permission:manage_period_close')->only(['close']);
        $this->middleware('permission:override_period_close')->only(['reopen']);
    }

    public function index(Request $request)
    {
        $orgId = $this->currentOrg->id();

        $periods = FiscalPeriod::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->with('fiscalYear')
            ->orderByDesc('start_date')
            ->paginate(24);

        // Latest completed run per period (for quick status display).
        $runs = PeriodCloseRun::query()
            ->whereIn('fiscal_period_id', $periods->pluck('id'))
            ->orderByDesc('id')
            ->get()
            ->groupBy('fiscal_period_id');

        return view('finance.close.index', [
            'periods' => $periods,
            'runs'    => $runs,
        ]);
    }

    public function show(FiscalPeriod $period)
    {
        $this->ensureInOrg($period);

        $checks = $this->closeService->preview($period);

        $runs = PeriodCloseRun::query()
            ->where('fiscal_period_id', $period->id)
            ->with(['checks', 'initiatedBy', 'completedBy'])
            ->orderByDesc('id')
            ->get();

        $canClose = collect($checks)->every(
            fn ($c) => in_array($c['status'], ['pass', 'skipped'], true)
                || ($c['is_overridable'] && $c['status'] === 'fail')
        );

        return view('finance.close.show', [
            'period'   => $period,
            'checks'   => $checks,
            'runs'     => $runs,
            'canClose' => $canClose,
        ]);
    }

    public function close(ClosePeriodRequest $request, FiscalPeriod $period)
    {
        $this->ensureInOrg($period);

        $validated = $request->validated();
        $closeLevel = $validated['close_level'] ?? PeriodCloseRun::LEVEL_SOFT;
        $overrides = $validated['overrides'] ?? [];
        $notes = $validated['notes'] ?? null;

        // Hard close + any override requires the elevated permission.
        $needsOverridePerm = $closeLevel === PeriodCloseRun::LEVEL_HARD || ! empty($overrides);
        if ($needsOverridePerm && ! $request->user()?->hasPermission('override_period_close')) {
            return back()->with('error', 'You do not have permission to hard-close or override checks.');
        }

        try {
            $run = $this->closeService->close(
                period:     $period,
                actor:      $request->user(),
                closeLevel: $closeLevel,
                overrides:  $overrides,
                notes:      $notes,
            );

            return redirect()
                ->route('finance.close.show', $period)
                ->with('success', "Period closed ({$run->close_level}). Run #{$run->id} recorded with {$run->override_count} override(s).");
        } catch (PeriodCloseException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        } catch (\Throwable $e) {
            Log::error('PeriodCloseController::close failed', ['error' => $e->getMessage(), 'period' => $period->id]);
            return back()->with('error', 'Close failed: ' . $e->getMessage())->withInput();
        }
    }

    public function reopen(ReopenPeriodRequest $request, FiscalPeriod $period)
    {
        $this->ensureInOrg($period);

        $validated = $request->validated();

        try {
            $this->closeService->reopen(
                period:            $period,
                actor:             $request->user(),
                reason:            $validated['reason'],
                allowLockedReopen: true, // gated by override_period_close permission on the route
            );

            return redirect()
                ->route('finance.close.show', $period)
                ->with('success', 'Period reopened.');
        } catch (PeriodCloseException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    protected function ensureInOrg(FiscalPeriod $period): void
    {
        $orgId = $this->currentOrg->id();
        if ($orgId !== null && $period->organization_id !== null && $period->organization_id !== $orgId) {
            abort(404);
        }
    }
}
