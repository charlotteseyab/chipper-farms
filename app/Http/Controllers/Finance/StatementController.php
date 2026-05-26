<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Counterparty;
use App\Services\Finance\Statements\StatementPdfRenderer;
use App\Services\Finance\Statements\StatementService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Customer statement controller.
 *
 * Two endpoints:
 *   show()     — HTML render of the activity statement
 *   download() — PDF download of the same data
 *
 * Both run on view_finance permission. The PDF download passes
 * through the same StatementService — single source of truth.
 */
class StatementController extends Controller
{
    public function show(
        Counterparty $customer,
        Request $request,
        StatementService $service,
        CurrentOrganization $currentOrg,
    ) {
        $this->ensureInOrg($customer, $currentOrg);

        [$startDate, $endDate] = $this->resolveDateRange($request);
        $statement = $service->build($customer, $startDate, $endDate);

        return view('finance.statements.show', compact('statement', 'customer'));
    }

    public function download(
        Counterparty $customer,
        Request $request,
        StatementService $service,
        StatementPdfRenderer $renderer,
        CurrentOrganization $currentOrg,
    ) {
        $this->ensureInOrg($customer, $currentOrg);

        [$startDate, $endDate] = $this->resolveDateRange($request);
        $statement = $service->build($customer, $startDate, $endDate);

        $inline = (bool) $request->get('inline');
        return $inline
            ? $renderer->inlineResponse($statement)
            : $renderer->downloadResponse($statement);
    }

    /* internals */

    protected function resolveDateRange(Request $request): array
    {
        $endDate = $request->filled('end_date')
            ? CarbonImmutable::parse($request->get('end_date'))
            : CarbonImmutable::today();

        if ($request->filled('start_date')) {
            $startDate = CarbonImmutable::parse($request->get('start_date'));
        } else {
            // Default period from config.
            $defaultPeriod = config('finance.statements.default_period', 'current_month');
            $startDate = match ($defaultPeriod) {
                'last_30_days' => $endDate->subDays(30),
                'last_60_days' => $endDate->subDays(60),
                'last_90_days' => $endDate->subDays(90),
                default        => $endDate->startOfMonth(),
            };
        }

        if ($startDate->greaterThan($endDate)) {
            // Defensive — swap rather than 422.
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        return [$startDate, $endDate];
    }

    protected function ensureInOrg(Counterparty $customer, CurrentOrganization $currentOrg): void
    {
        $orgId = $currentOrg->resolve()?->id;
        if ($orgId !== null && $customer->organization_id !== null && (int) $customer->organization_id !== (int) $orgId) {
            abort(403, 'Customer belongs to a different organisation.');
        }
    }
}
