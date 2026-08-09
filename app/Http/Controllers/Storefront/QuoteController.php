<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\QuoteRequestRequest;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontQuote;
use App\Storefront\Services\QuoteService;
use App\Storefront\Support\QuoteRequestData;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public quote-request flow. Reachable by guests and customers. Supports two
 * entry points:
 *   - From a listing: ?listing={id} pre-seeds a single line.
 *   - Bulk/standalone: an empty multi-line request.
 *
 * PHASE 8 — QUOTE REQUESTS ARE OPTIONAL
 * -------------------------------------
 * `storefront.quotes` decides whether this farm takes them. Enforced in the
 * controller rather than with the `feature:` middleware because that middleware
 * resolves the organisation from `CurrentOrganization`, which is null for the
 * unauthenticated visitors this flow exists to serve; `StorefrontSettings`
 * resolves the public organisation the same way every other public page does.
 *
 * The check covers `store()` as well as `create()` on purpose. Hiding the link
 * and leaving the endpoint is how a switched-off surface keeps accepting work.
 */
class QuoteController extends Controller
{
    public function __construct(
        protected QuoteService $quotes,
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    protected function assertQuotesOffered(): void
    {
        abort_unless(\App\Storefront\Support\StorefrontSettings::quotesEnabled(), 404);
    }

    public function create(Request $request): View
    {
        $this->assertQuotesOffered();

        $listing = null;

        if ($listingId = $request->integer('listing')) {
            $listing = StorefrontListing::query()
                ->visible()
                ->find($listingId);
        }

        return view('storefront.public.quote', [
            'listing' => $listing,
            'source'  => $listing ? StorefrontQuote::SOURCE_LISTING : StorefrontQuote::SOURCE_BULK,
            'user'    => $request->user(),
        ]);
    }

    public function store(QuoteRequestRequest $request): RedirectResponse
    {
        $this->assertQuotesOffered();

        $data = QuoteRequestData::fromArray($request->validated());

        $quote = $this->quotes->request($data, $request->user());

        return redirect()
            ->route('storefront.quote.thanks', ['reference' => $quote->reference])
            ->with('success', "Quote request {$quote->reference} submitted. We'll be in touch.");
    }

    public function thanks(Request $request, string $reference): View
    {
        return view('storefront.public.quote-thanks', [
            'reference' => $reference,
        ]);
    }
}
