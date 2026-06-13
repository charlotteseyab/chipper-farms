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

    protected function request(): Request
    {
        return request();
    }
}
