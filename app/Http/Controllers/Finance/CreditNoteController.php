<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\CreditNote;
use App\Services\Finance\Documents\CustomerDocumentRenderer;
use App\Support\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Finance → Credits (Phase 7, blueprint §29).
 *
 * WHAT WAS MISSING
 * ----------------
 * `CreditNoteService` and the `credit_notes` tables have existed since the
 * sales-return work, and `SalesReturnRevenueSubscriber` creates a numbered
 * credit note every time a return is recorded. Nothing in the application ever
 * showed one. A farm could reverse revenue, credit a customer's account and
 * have no way to see that it had happened, let alone give the customer the
 * document §29 requires.
 *
 * READ-ONLY, DELIBERATELY
 * -----------------------
 * There is no "create credit note" action here, and that is the design rather
 * than an omission. §29's first requirement is "credit note created from sales
 * return" and its second is "idempotency key per sales return" — the numbered
 * document is an *effect* of a return, and its uniqueness guarantee is anchored
 * to the return that caused it. A screen that minted credit notes directly
 * would step around both: revenue reversed with no return to explain it, and a
 * second reversal available for the same goods.
 *
 * The sales-return UX that creates them is blueprint §43, which the plan assigns
 * to Phase 13. This phase makes what already exists visible and hands the
 * customer their copy.
 */
class CreditNoteController extends Controller
{
    public function __construct(
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    public function index(Request $request): View
    {
        $organizationId = $this->currentOrganization->requireId();

        $creditNotes = CreditNote::query()
            ->where('organization_id', $organizationId)
            ->with(['customer', 'originalInvoice'])
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(25);

        // Total credited, from the rows on the page's own query rather than a
        // second aggregate that could drift from what is listed.
        $issued = CreditNote::query()
            ->where('organization_id', $organizationId)
            ->whereIn('status', [
                CreditNote::STATUS_POSTED,
                CreditNote::STATUS_PARTIALLY_APPLIED,
                CreditNote::STATUS_APPLIED,
            ])
            ->pluck('total');

        $totalCredited = '0.00';
        foreach ($issued as $amount) {
            $totalCredited = bcadd($totalCredited, (string) $amount, 2);
        }

        return view('finance.credit-notes.index', [
            'creditNotes'   => $creditNotes,
            'totalCredited' => $totalCredited,
        ]);
    }

    public function show(CreditNote $creditNote): View
    {
        $this->assertInOrganization($creditNote);

        $creditNote->load(['customer', 'originalInvoice', 'lines', 'journalBatch']);

        return view('finance.credit-notes.show', ['creditNote' => $creditNote]);
    }

    /**
     * The customer's copy.
     */
    public function document(CreditNote $creditNote, CustomerDocumentRenderer $renderer): Response
    {
        $this->assertInOrganization($creditNote);

        return $renderer->creditNote($creditNote);
    }

    /**
     * Route-model binding resolves by id alone, so this is the only thing
     * between one farm and another farm's customer credits. 404 rather than
     * 403: another tenant's document should not be discoverable.
     */
    protected function assertInOrganization(CreditNote $creditNote): void
    {
        abort_unless(
            (int) $creditNote->organization_id === $this->currentOrganization->requireId(),
            404,
        );
    }
}
