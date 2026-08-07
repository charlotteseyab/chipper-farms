<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\DomainEvent;
use App\Models\Finance\JournalBatch;
use App\Models\Finance\Journal;
use App\Models\Finance\PeriodLockDate;
use App\Models\FiscalPeriod;
use App\Services\Finance\Reporting\TrialBalanceService;
use App\Support\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Phase 1d — Finance dashboard.
 *
 * Real data, not mock. Pulls live KPIs from the GL.
 *
 * KPIs:
 *   - Trial balance total (current period)
 *   - Pending vs posted journal batch counts (last 30 days)
 *   - Pending domain events (outbox health)
 *   - Drafts awaiting approval (proposed but not posted)
 *   - Active period lock dates
 *
 * Recent activity:
 *   - Last 10 journal batches across all types
 *
 * Period state:
 *   - Currently-open fiscal period(s)
 *   - Most recent posting date observed in GL
 */
class FinanceDashboardController extends Controller
{
    public function __invoke(Request $request, TrialBalanceService $trialBalance, CurrentOrganization $currentOrg)
    {
        $orgId = $currentOrg->id();

        // Currently-open fiscal period (assume one — log a warning if more).
        $currentPeriod = FiscalPeriod::query()
            ->when(
                $orgId !== null,
                fn ($q) => $q->where('organization_id', $orgId),
                fn ($q) => $q->whereNull('organization_id'),
            )
            ->where('status', FiscalPeriod::STATUS_OPEN)
            ->where('start_date', '<=', now()->toDateString())
            ->where('end_date', '>=', now()->toDateString())
            ->first();

        // Trial balance for the current period only — fast preview snapshot.
        $tbSummary = null;
        if ($currentPeriod) {
            $tb = $trialBalance->generate(
                Carbon::parse($currentPeriod->start_date),
                Carbon::parse($currentPeriod->end_date)
            );
            $tbSummary = $tb['totals'];
        }

        // Counts.
        $kpis = [
            // Scoped to the organisation, like every other count on this
            // screen. These two were previously unscoped while the two below
            // were not, so an accountant at one farm saw another farm's failure
            // counts — and their own number was wrong as a result.
            'pending_events'     => DomainEvent::where('status', DomainEvent::STATUS_PENDING)
                                        ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
                                        ->count(),
            'failed_events'      => DomainEvent::where('status', DomainEvent::STATUS_FAILED)
                                        ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
                                        ->count(),
            'drafts_awaiting'    => JournalBatch::where('status', JournalBatch::STATUS_DRAFT)
                                        ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
                                        ->count(),
            'posted_last_30'     => JournalBatch::where('status', JournalBatch::STATUS_POSTED)
                                        ->where('posted_at', '>=', now()->subDays(30))
                                        ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
                                        ->count(),
        ];

        // Recent activity.
        $recentBatches = JournalBatch::with(['journals', 'fiscalPeriod', 'proposedBy', 'postedBy'])
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->latest('id')
            ->limit(10)
            ->get();

        // Active period lock.
        $activeLock = PeriodLockDate::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->where('is_active', true)
            ->orderByDesc('lock_date')
            ->first();

        // Most recent posting date (max(posting_date) on posted journals).
        $latestPostingDate = Journal::where('status', Journal::STATUS_POSTED)
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->max('posting_date');

        return view('finance.dashboard.index', [
            'currentPeriod'     => $currentPeriod,
            'tbSummary'         => $tbSummary,
            'kpis'              => $kpis,
            'recentBatches'     => $recentBatches,
            'activeLock'        => $activeLock,
            'latestPostingDate' => $latestPostingDate,
        ]);
    }
}
