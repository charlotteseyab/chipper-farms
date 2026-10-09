<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Storefront\StorefrontListing;
use App\Storefront\Services\Exceptions\InvalidListingTransitionException;
use App\Storefront\Services\ListingAvailabilityService;
use App\Storefront\Services\ListingService;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Back-office storefront workspace: dashboard + listings management.
 * All routes are gated by storefront permissions (see routes/web.php).
 */
class AdminStorefrontController extends Controller
{
    public function __construct(
        protected CurrentOrganization $currentOrganization,
        protected ListingService $listingService,
        protected ListingAvailabilityService $availability,
    ) {
    }

    public function dashboard(): View
    {
        $orgId = $this->currentOrganization->requireId();

        $base = StorefrontListing::query()->where('organization_id', $orgId);

        $stats = [
            'total'        => (clone $base)->count(),
            'published'    => (clone $base)->where('status', StorefrontListing::STATUS_PUBLISHED)->count(),
            'draft'        => (clone $base)->where('status', StorefrontListing::STATUS_DRAFT)->count(),
            'unpublished'  => (clone $base)->where('status', StorefrontListing::STATUS_UNPUBLISHED)->count(),
            'sold_out'     => (clone $base)->where('status', StorefrontListing::STATUS_SOLD_OUT)->count(),
        ];

        $recent = (clone $base)->latest()->limit(10)->get();

        return view('storefront.admin.dashboard', compact('stats', 'recent'));
    }

    public function index(Request $request): View
    {
        $orgId = $this->currentOrganization->requireId();

        $query = StorefrontListing::query()
            ->where('organization_id', $orgId)
            ->with(['category', 'media']);

        if ($status = $request->string('status')->toString()) {
            if (in_array($status, StorefrontListing::STATUSES, true)) {
                $query->where('status', $status);
            }
        }

        if ($kind = $request->string('kind')->toString()) {
            if (in_array($kind, StorefrontListing::KINDS, true)) {
                $query->ofKind($kind);
            }
        }

        if ($search = trim($request->string('q')->toString())) {
            $query->where(fn ($q) => $q
                ->where('title', 'like', "%{$search}%")
                ->orWhere('reference', 'like', "%{$search}%"));
        }

        $listings = $query->latest()->paginate(20)->withQueryString();

        return view('storefront.admin.listings.index', [
            'listings' => $listings,
            'filters'  => [
                'status' => $request->string('status')->toString(),
                'kind'   => $request->string('kind')->toString(),
                'q'      => $request->string('q')->toString(),
            ],
        ]);
    }

    public function show(StorefrontListing $listing): View
    {
        $this->authorize('view', $listing);
        $listing->load(['category', 'media', 'placements']);

        return view('storefront.admin.listings.show', [
            'listing'         => $listing,
            'sourceAvailable' => $this->availability->isSourceAvailable($listing),
            // The pig pool behind this listing, if it has one. Ordered so the
            // animals still on offer come first and the ones that have left —
            // sold through the shop, sold at the gate, pulled out — read as
            // history below them.
            // Sorted in PHP, not SQL: MySQL's FIELD() does not exist on
            // SQLite, and the default test suite runs there. A pool is a
            // handful of rows, so ordering them in memory costs nothing and
            // behaves identically on both engines.
            'listingPigs'     => \App\Models\Storefront\StorefrontListingPig::query()
                ->where('storefront_listing_id', $listing->getKey())
                ->with(['pig.earTagAssignments.tag.color', 'allocatedOrder'])
                ->orderBy('id')
                ->get()
                ->sortBy(fn ($row) => array_search(
                    $row->status,
                    ['available', 'held', 'allocated', 'sold', 'removed'],
                    true,
                ) ?: 0)
                ->values(),
        ]);
    }

    public function publish(StorefrontListing $listing): RedirectResponse
    {
        $this->authorize('publish', $listing);

        try {
            $this->listingService->publish($listing, $this->request()->user());
        } catch (InvalidListingTransitionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Listing {$listing->reference} published.");
    }

    public function unpublish(StorefrontListing $listing): RedirectResponse
    {
        $this->authorize('publish', $listing);

        try {
            $this->listingService->unpublish($listing, $this->request()->user());
        } catch (InvalidListingTransitionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Listing {$listing->reference} unpublished.");
    }

    public function archive(StorefrontListing $listing): RedirectResponse
    {
        $this->authorize('update', $listing);

        try {
            $this->listingService->archive($listing, $this->request()->user());
        } catch (InvalidListingTransitionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Listing {$listing->reference} archived.");
    }

    public function destroy(StorefrontListing $listing): RedirectResponse
    {
        $this->authorize('delete', $listing);

        $reference = $listing->reference;
        $this->listingService->delete($listing, $this->request()->user());

        return redirect()
            ->route('storefront.admin.listings.index')
            ->with('success', "Listing {$reference} deleted. Unsold selected pigs are available for other listings.");
    }

    protected function request(): Request
    {
        return request();
    }
}
