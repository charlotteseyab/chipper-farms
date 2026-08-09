<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Storefront\StorefrontCategory;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontPlacement;
use App\Storefront\Support\StorefrontCatalog;
use App\Storefront\Support\StorefrontSettings;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public-facing storefront. These routes are intentionally OUTSIDE the
 * operational auth/permission middleware — the shop is browsable by anyone.
 * Org context: for unauthenticated visitors we resolve the default/first
 * organization so the catalogue renders; an authenticated user's org wins.
 *
 * PHASE 8 — CATEGORIES THIS FARM HAS SWITCHED OFF DO NOT APPEAR
 * -------------------------------------------------------------
 * Every query here is constrained to `StorefrontCatalog::enabledKinds()`.
 * Before Phase 8 the thirteen `storefront.*` feature flags were enforced
 * nowhere, so a farm that switched Manure Sales off carried on selling manure
 * publicly. Filtering here rather than in the views means the same rule covers
 * the catalogue, search, category cards, placements and the detail page, and a
 * new view cannot forget it.
 */
class StorefrontController extends Controller
{
    public function home(): View
    {
        $orgId = $this->resolveOrganizationId();

        $categories = StorefrontCategory::query()
            ->where('organization_id', $orgId)
            ->tap(fn ($q) => StorefrontCatalog::constrainCategoriesToEnabledKinds($q, $orgId))
            ->active()->ordered()->get();

        // Hero + featured placements, resolved to their published listings.
        $hero = $this->placementListings($orgId, StorefrontPlacement::CODE_HOME_HERO, 1);

        $featured = [
            'live_pigs'       => $this->placementListings($orgId, StorefrontPlacement::CODE_LIVE_PIGS, 4),
            'breeding_stock'  => $this->placementListings($orgId, StorefrontPlacement::CODE_BREEDING_STOCK, 4),
            'feed'            => $this->placementListings($orgId, StorefrontPlacement::CODE_FEED, 4),
            'manure'          => $this->placementListings($orgId, StorefrontPlacement::CODE_MANURE, 4),
            'new_arrivals'    => $this->placementListings($orgId, StorefrontPlacement::CODE_NEW_ARRIVALS, 4),
        ];

        // Fallback: if no placements configured yet, surface latest published.
        if ($hero->isEmpty() && collect($featured)->every->isEmpty()) {
            $featured['new_arrivals'] = StorefrontListing::query()
                ->where('organization_id', $orgId)
                ->tap(fn ($q) => StorefrontCatalog::constrainToEnabledKinds($q, $orgId))
                ->visible()->latest('published_at')->limit(8)->get();
        }

        return view('storefront.public.home', [
            'settings'   => StorefrontSettings::class,
            'categories' => $categories,
            'hero'       => $hero->first(),
            'featured'   => $featured,
        ]);
    }

    public function shop(Request $request): View
    {
        $orgId = $this->resolveOrganizationId();

        $enabledKinds = StorefrontCatalog::enabledKinds($orgId);

        $query = StorefrontListing::query()
            ->where('organization_id', $orgId)
            ->whereIn('kind', $enabledKinds)
            ->visible()
            ->with(['media', 'category']);

        // Filters. `?kind=` narrows; it cannot widen past the constraint above,
        // so a URL naming a switched-off category returns nothing rather than
        // reopening it.
        if ($kind = $request->string('kind')->toString()) {
            if (in_array($kind, StorefrontListing::KINDS, true)) {
                $query->ofKind($kind);
            }
        }

        if ($categorySlug = $request->string('category')->toString()) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $categorySlug));
        }

        if ($search = trim($request->string('q')->toString())) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('public_description', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%");
            });
        }

        // Price range (applies to whichever of fixed_price/price_per_kg is set).
        if (($min = $request->input('min_price')) !== null && $min !== '') {
            $query->whereRaw('COALESCE(fixed_price, price_per_kg) >= ?', [(float) $min]);
        }
        if (($max = $request->input('max_price')) !== null && $max !== '') {
            $query->whereRaw('COALESCE(fixed_price, price_per_kg) <= ?', [(float) $max]);
        }

        // Fulfilment filter.
        if ($request->boolean('delivery')) {
            $query->where('delivery_allowed', true);
        }

        // Sorting (whitelist).
        $sort = $request->string('sort')->toString();
        match ($sort) {
            'price_asc'  => $query->orderByRaw('COALESCE(fixed_price, price_per_kg) asc'),
            'price_desc' => $query->orderByRaw('COALESCE(fixed_price, price_per_kg) desc'),
            'oldest'     => $query->oldest('published_at'),
            default      => $query->latest('published_at'),
        };

        $listings = $query->paginate(12)->withQueryString();

        $categories = StorefrontCategory::query()
            ->where('organization_id', $orgId)
            ->tap(fn ($q) => StorefrontCatalog::constrainCategoriesToEnabledKinds($q, $orgId))
            ->active()->ordered()->get();

        return view('storefront.public.shop', [
            'listings'   => $listings,
            'categories' => $categories,
            'filters'    => [
                'kind'      => $request->string('kind')->toString(),
                'category'  => $request->string('category')->toString(),
                'q'         => $request->string('q')->toString(),
                'sort'      => $sort,
                'min_price' => $request->input('min_price'),
                'max_price' => $request->input('max_price'),
                'delivery'  => $request->boolean('delivery'),
            ],
        ]);
    }

    /**
     * Public listing detail page. Only published, non-hidden listings are
     * viewable. View count is incremented atomically.
     */
    public function detail(Request $request, string $slug): View
    {
        $orgId = $this->resolveOrganizationId();

        $listing = StorefrontListing::query()
            ->where('organization_id', $orgId)
            ->where('slug', $slug)
            ->with(['media', 'category'])
            ->firstOrFail();

        // Only publicly-visible listings render on the public detail page.
        // The kind check is Phase 8: without it a switched-off category stayed
        // reachable by its direct URL, which is how a "hidden" surface stops
        // being hidden — the links go, the pages stay, and the first search
        // result finds one.
        abort_unless(
            $listing->status === StorefrontListing::STATUS_PUBLISHED
            && $listing->sale_mode !== StorefrontListing::MODE_HIDDEN
            && StorefrontCatalog::isKindEnabled($listing->kind, $orgId),
            404,
        );

        // Atomic, non-blocking view counter (no race, no stale read).
        StorefrontListing::query()->whereKey($listing->id)->increment('view_count');

        $presenter = app(\App\Storefront\Support\ListingDetailPresenter::class);

        $favourited = false;
        if ($request->user() !== null) {
            $favourited = $listing->isFavouritedBy($request->user()->id);
        }

        return view('storefront.public.detail', [
            'listing'    => $listing,
            'detail'     => $presenter->present($listing),
            'favourited' => $favourited,
        ]);
    }

    /**
     * Resolve published listings pinned into a placement slot.
     */
    protected function placementListings(?int $orgId, string $code, int $limit)
    {
        return StorefrontPlacement::query()
            ->where('organization_id', $orgId)
            ->forCode($code)
            ->activeNow()
            ->with(['listing.media'])
            ->limit($limit)
            ->get()
            ->map(fn (StorefrontPlacement $p) => $p->listing)
            ->filter(fn ($l) => $l
                && $l->status === StorefrontListing::STATUS_PUBLISHED
                && StorefrontCatalog::isKindEnabled($l->kind, $orgId))
            ->values();
    }

    /**
     * The organisation this public request is serving.
     *
     * Delegated to the storefront settings resolver so the fallback rule for an
     * unauthenticated visitor — first organisation, single-tenant in practice
     * for Chipper — is defined once. It previously lived here and the settings
     * accessor resolved separately, which meant a page and the settings it read
     * could describe different farms.
     */
    protected function resolveOrganizationId(): ?int
    {
        return StorefrontSettings::organizationId();
    }
}
