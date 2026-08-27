<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontPlacement;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        // Only listings that a guest can see right now are eligible.
        $publishedListings = StorefrontListing::query()
            ->where('organization_id', $orgId)
            ->visible()
            ->orderBy('title')
            ->get(['id', 'title', 'reference', 'kind', 'sale_mode']);

        return view('storefront.admin.placements.index', [
            'placements'        => $placements,
            'codes'             => StorefrontPlacement::CODES,
            'labels'            => StorefrontPlacement::LABELS,
            'descriptions'      => StorefrontPlacement::DESCRIPTIONS,
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

        if (! StorefrontListing::query()->whereKey($listing->id)->visible()->exists()) {
            return back()->with('error', 'Only currently visible listings can be placed on the homepage. Check its status, sale mode, and availability dates.');
        }

        $duplicate = StorefrontPlacement::query()
            ->where('organization_id', $orgId)
            ->where('code', $validated['code'])
            ->where('listing_id', $listing->id)
            ->exists();

        if ($duplicate) {
            return back()->with('error', 'That listing is already pinned in the selected homepage section.');
        }

        DB::transaction(function () use ($orgId, $validated, $listing, $request): void {
            if ($validated['code'] === StorefrontPlacement::CODE_HOME_HERO) {
                StorefrontPlacement::query()
                    ->where('organization_id', $orgId)
                    ->where('code', StorefrontPlacement::CODE_HOME_HERO)
                    ->whereNotNull('listing_id')
                    ->delete();
            }

            $position = array_key_exists('position', $validated) && $validated['position'] !== null
                ? (int) $validated['position']
                : (int) StorefrontPlacement::query()
                    ->where('organization_id', $orgId)
                    ->where('code', $validated['code'])
                    ->whereNotNull('listing_id')
                    ->max('position') + 1;

            StorefrontPlacement::create([
                'organization_id'    => $orgId,
                'code'               => $validated['code'],
                'listing_id'         => $listing->id,
                'position'           => $validated['code'] === StorefrontPlacement::CODE_HOME_HERO ? 0 : $position,
                'is_active'          => true,
                'note'               => $validated['note'] ?? null,
                'created_by_user_id' => $request->user()->id,
            ]);
        });

        return back()->with('success', "{$listing->title} is now pinned to " . StorefrontPlacement::LABELS[$validated['code']] . '.');
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
