<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Storefront\StorefrontQuote;
use App\Storefront\Services\Exceptions\InvalidQuoteTransitionException;
use App\Storefront\Services\QuoteService;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Back-office quote workspace: triage, price, accept/decline. Gated by
 * storefront quote permissions.
 */
class AdminQuoteController extends Controller
{
    public function __construct(
        protected CurrentOrganization $currentOrganization,
        protected QuoteService $quotes,
    ) {
    }

    public function index(Request $request): View
    {
        $orgId = $this->currentOrganization->requireId();

        $query = StorefrontQuote::query()
            ->where('organization_id', $orgId)
            ->withCount('items');

        if ($status = $request->string('status')->toString()) {
            if (in_array($status, StorefrontQuote::STATUSES, true)) {
                $query->where('status', $status);
            }
        } else {
            // Default view focuses on open quotes.
            $query->open();
        }

        $quotes = $query->latest()->paginate(20)->withQueryString();

        return view('storefront.admin.quotes.index', [
            'quotes' => $quotes,
            'status' => $request->string('status')->toString(),
        ]);
    }

    public function show(StorefrontQuote $quote): View
    {
        $this->assertSameOrg($quote);
        $quote->load(['items.listing', 'counterparty', 'assignedTo', 'customer']);

        return view('storefront.admin.quotes.show', compact('quote'));
    }

    public function review(StorefrontQuote $quote): RedirectResponse
    {
        $this->assertSameOrg($quote);

        return $this->guarded(fn () => $this->quotes->markReviewing($quote, request()->user()),
            "Quote {$quote->reference} marked as reviewing.");
    }

    public function quote(Request $request, StorefrontQuote $quote): RedirectResponse
    {
        $this->assertSameOrg($quote);

        $validated = $request->validate([
            'quoted_total' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'valid_until'  => ['nullable', 'date', 'after_or_equal:today'],
            'staff_notes'  => ['nullable', 'string', 'max:5000'],
        ]);

        return $this->guarded(fn () => $this->quotes->provideQuote(
            $quote,
            $request->user(),
            (string) $validated['quoted_total'],
            $validated['valid_until'] ?? null,
            $validated['staff_notes'] ?? null,
        ), "Quote {$quote->reference} priced and sent.");
    }

    public function accept(StorefrontQuote $quote): RedirectResponse
    {
        $this->assertSameOrg($quote);

        return $this->guarded(fn () => $this->quotes->accept($quote, request()->user()),
            "Quote {$quote->reference} accepted.");
    }

    public function decline(Request $request, StorefrontQuote $quote): RedirectResponse
    {
        $this->assertSameOrg($quote);

        $reason = $request->string('reason')->toString() ?: null;

        return $this->guarded(fn () => $this->quotes->decline($quote, $request->user(), $reason),
            "Quote {$quote->reference} declined.");
    }

    protected function guarded(\Closure $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (InvalidQuoteTransitionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $success);
    }

    protected function assertSameOrg(StorefrontQuote $quote): void
    {
        abort_unless(
            (int) $quote->organization_id === $this->currentOrganization->requireId(),
            403,
        );
    }
}
