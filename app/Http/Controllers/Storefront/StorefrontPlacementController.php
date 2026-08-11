<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontPlacement;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

/**
 * Back-office homepage / featured placement manager. Lets a salesperson/admin
 * pin published listings into the named placement slots that the public
 * homepage renders.
 */
class StorefrontPlacementController extends Controller
{
    public function __construct(protected CurrentOrganization $currentOrganization)
    {
    }

    public function index(): View
    {
        $orgId = $this->currentOrganization->requireId();

        $placements = StorefrontPlacement::query()
            ->where('organization_id', $orgId)
            ->with('listing')
            ->orderBy('code')->orderBy('position')
            ->get()
            ->groupBy('code');

        // Listings eligible to be pinned (published).
        $publishedListings = StorefrontListing::query()
            ->where('organization_id', $orgId)
            ->published()
            ->orderBy('title')
            ->get(['id', 'title', 'reference', 'kind']);

        return view('storefront.admin.placements.index', [
            'placements'        => $placements,
            'codes'             => StorefrontPlacement::CODES,
            'publishedListings' => $publishedListings,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $orgId = $this->currentOrganization->requireId();

        $validated = $request->validate([
            'code'       => ['required', Rule::in(StorefrontPlacement::CODES)],
            'listing_id' => ['required', 'integer', 'exists:storefront_listings,id'],
            'position'   => ['nullable', 'integer', 'min:0', 'max:9999'],
            'note'       => ['nullable', 'string', 'max:200'],
        ]);

        // Listing must belong to this org and be published.
        $listing = StorefrontListing::query()
            ->where('organization_id', $orgId)
            ->where('id', $validated['listing_id'])
            ->first();

        if (! $listing) {
            return back()->with('error', 'Listing not found in your organization.');
        }

        if ($listing->status !== StorefrontListing::STATUS_PUBLISHED) {
            return back()->with('error', 'Only published listings can be featured.');
        }

        StorefrontPlacement::create([
            'organization_id'    => $orgId,
            'code'               => $validated['code'],
            'listing_id'         => $listing->id,
            'position'           => $validated['position'] ?? 0,
            'is_active'          => true,
            'note'               => $validated['note'] ?? null,
            'created_by_user_id' => $request->user()->id,
        ]);

        return back()->with('success', "Listing {$listing->reference} pinned to {$validated['code']}.");
    }

    public function destroy(StorefrontPlacement $placement): RedirectResponse
    {
        $orgId = $this->currentOrganization->requireId();

        // 404, not 403 (Phase 16, IMP-112): another farm's rows should be
        // indistinguishable from rows that never existed.
        abort_unless((int) $placement->organization_id === $orgId, 404);

        // Keep an empty slot row so the slot remains listed in the manager;
        // only clear the pinned listing if this was an empty seed row delete.
        if ($placement->listing_id === null) {
            return back()->with('error', 'This placement slot is already empty.');
        }

        $placement->delete();

        return back()->with('success', 'Placement removed.');
    }
}
