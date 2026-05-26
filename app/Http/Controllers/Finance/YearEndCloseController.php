<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ReverseYearEndRequest;
use App\Models\FiscalYear;
use App\Models\Finance\YearEndClosing;
use App\Services\Finance\Close\Exceptions\PeriodCloseException;
use App\Services\Finance\Close\YearEndCloseService;
use App\Support\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Phase 6 — year-end close UI.
 *
 *   index   — list fiscal years with their closing status.
 *   run     — execute the year-end RE closing journal.
 *   reverse — reverse a completed year-end close (reopens the year).
 *
 * Permissions:
 *   view_period_close       — index
 *   manage_year_end_close   — run
 *   override_period_close   — reverse
 */
class YearEndCloseController extends Controller
{
    public function __construct(
        protected CurrentOrganization $currentOrg,
        protected YearEndCloseService $service,
    ) {
        $this->middleware('auth');
        $this->middleware('permission:view_period_close')->only(['index']);
        $this->middleware('permission:manage_year_end_close')->only(['run']);
        $this->middleware('permission:override_period_close')->only(['reverse']);
    }

    public function index(Request $request)
    {
        $orgId = $this->currentOrg->id();

        $years = FiscalYear::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->orderByDesc('start_date')
            ->get();

        $closings = YearEndClosing::query()
            ->whereIn('fiscal_year_id', $years->pluck('id'))
            ->get()
            ->keyBy('fiscal_year_id');

        return view('finance.close.year-end', [
            'years'    => $years,
            'closings' => $closings,
        ]);
    }

    public function run(Request $request, FiscalYear $year)
    {
        $this->ensureInOrg($year);

        try {
            $closing = $this->service->close(
                year:  $year,
                actor: $request->user(),
                notes: $request->input('notes'),
            );

            return redirect()
                ->route('finance.year-end.index')
                ->with('success', sprintf(
                    'Year-end close completed. Net %s: GH₵ %s. Closing batch #%d.',
                    bccomp((string) $closing->net_profit, '0', 2) >= 0 ? 'profit' : 'loss',
                    number_format(abs((float) $closing->net_profit), 2),
                    (int) $closing->closing_journal_batch_id,
                ));
        } catch (PeriodCloseException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('YearEndCloseController::run failed', ['error' => $e->getMessage(), 'year' => $year->id]);
            return back()->with('error', 'Year-end close failed: ' . $e->getMessage());
        }
    }

    public function reverse(ReverseYearEndRequest $request, FiscalYear $year)
    {
        $this->ensureInOrg($year);

        try {
            $this->service->reverse(
                year:   $year,
                actor:  $request->user(),
                reason: $request->validated()['reason'],
            );

            return redirect()
                ->route('finance.year-end.index')
                ->with('success', 'Year-end close reversed; fiscal year reopened.');
        } catch (PeriodCloseException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    protected function ensureInOrg(FiscalYear $year): void
    {
        $orgId = $this->currentOrg->id();
        if ($orgId !== null && $year->organization_id !== null && $year->organization_id !== $orgId) {
            abort(404);
        }
    }
}
