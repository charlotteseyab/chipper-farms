<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\FeedSale;
use App\Models\PigSale;
use App\Models\SalesReturn;
use App\Services\Sales\RecordSalesReturnService;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Finance → Returns (IMP-098, blueprint §43 step 1).
 *
 * WHAT WAS MISSING
 * ----------------
 * §43's lifecycle begins "user requests return/refund with reason and
 * quantity". Every step after that was built —
 * `RecordSalesReturnService` validates and records, the COGS subscribers
 * reverse inventory, the revenue subscriber issues the credit note — but the
 * first step had no surface. The service was reachable only from the storefront
 * refund path and from tests, so a farm could not record that a customer had
 * brought feed back.
 *
 * THIS SCREEN RECORDS, IT DOES NOT POST
 * -------------------------------------
 * Everything here goes through `RecordSalesReturnService`. The over-return
 * guard, the proportion arithmetic, the domain event and the credit note stay
 * where they are. Blueprint §41's rule about not posting from a controller
 * applies just as much on the way back as on the way out.
 *
 * WHY PIG QUANTITY IS NOT ASKED FOR
 * ---------------------------------
 * §45: pig returns are indivisible — "quantity always one, partial return not
 * allowed". The form does not offer a quantity for a pig sale rather than
 * offering one and rejecting it, because a field that only ever accepts one
 * value is a trap, not a choice.
 *
 * RESTOCK IS A DECISION, NOT A DEFAULT
 * ------------------------------------
 * §44 makes it a flag: true when the goods go back into inventory, false when
 * they are spoiled or kept. Getting it wrong overstates stock the farm cannot
 * sell, so the form asks rather than assuming, and §45 adds that a returned
 * animal needs a staff health decision before it is relisted — which is why a
 * pig return never restocks automatically here.
 */
class SalesReturnController extends Controller
{
    public function __construct(
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    public function index(Request $request): View
    {
        $organizationId = $this->currentOrganization->requireId();

        return view('finance.returns.index', [
            'returns' => SalesReturn::query()
                ->where('organization_id', $organizationId)
                ->with('creditNote')
                ->orderByDesc('id')
                ->paginate(25),

            // Credit note documents are the Advanced Accounting add-on; without
            // it the number is shown but not linked.
            'creditNotesOpen' => \App\Support\Features\FinanceHubs::canOpen('finance.credit-notes.show', $request->user()),

            // Sales still open to return, newest first. Deliberately limited:
            // this is a "what came back today" screen, not a sales register.
            'feedSales' => FeedSale::query()
                ->where('organization_id', $organizationId)
                ->with('feedType')
                ->orderByDesc('sale_date')->orderByDesc('id')
                ->limit(50)->get(),

            'pigSales' => PigSale::query()
                ->where('organization_id', $organizationId)
                ->with('pig.earTagAssignments.tag.color')
                ->orderByDesc('sale_date')->orderByDesc('id')
                ->limit(50)->get(),
        ]);
    }

    public function store(Request $request, RecordSalesReturnService $returns): RedirectResponse
    {
        $organizationId = $this->currentOrganization->requireId();

        $validated = $request->validate([
            'sale_type' => ['required', Rule::in(['feed', 'pig'])],
            'sale_id'   => ['required', 'integer'],
            // Quantity is required for feed and ignored for pig — §45 makes a
            // pig return indivisible, so the form does not offer the field.
            'quantity'  => ['nullable', 'numeric', 'gt:0', 'required_if:sale_type,feed'],
            'reason'    => ['required', 'string', 'max:500'],
            'restock'   => ['nullable', 'boolean'],
        ], [
            'reason.required' => 'Say why the goods came back — it goes on the customer’s credit note.',
        ]);

        $sale = $validated['sale_type'] === 'feed'
            ? FeedSale::find($validated['sale_id'])
            : PigSale::find($validated['sale_id']);

        // Scoped rather than a bare exists: rule — a posted id from another
        // farm's sale list would reverse their revenue, not this farm's.
        if ($sale === null || (int) $sale->organization_id !== $organizationId) {
            abort(404);
        }

        try {
            $return = $returns->record(
                sale: $sale,
                actor: $request->user(),
                // §45: a pig is one animal. Not read from the request at all,
                // so a hand-posted quantity cannot make it divisible.
                quantity: $validated['sale_type'] === 'pig'
                    ? '1'
                    : (string) $validated['quantity'],
                reason: $validated['reason'],
                // A returned animal needs a staff health decision before it is
                // sellable again (§45), so a pig return never restocks from
                // this screen. Feed is the farm's call.
                restock: $validated['sale_type'] === 'pig'
                    ? false
                    : $request->boolean('restock'),
            );
        } catch (\InvalidArgumentException $e) {
            // The service's own refusals — over-return, non-positive quantity —
            // are the useful message here. It knows how much has already come
            // back; this controller does not.
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('finance.returns.index')
            ->with('success', "Return {$return->return_number} recorded. The customer's credit note follows automatically.");
    }
}
