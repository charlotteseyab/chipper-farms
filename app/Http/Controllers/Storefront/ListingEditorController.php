<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\UpdateListingRequest;
use App\Models\Storefront\StorefrontCategory;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontListingMedia;
use App\Models\Storefront\StorefrontListingPig;
use App\Storefront\Services\ListingEditorService;
use App\Storefront\Services\StorefrontPigPoolService;
use App\Storefront\Services\StorefrontPigPricingService;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Back-office listing editor (content + media). Lifecycle actions
 * (publish/unpublish/archive) live on AdminStorefrontController.
 */
class ListingEditorController extends Controller
{
    public function __construct(
        protected CurrentOrganization $currentOrganization,
        protected ListingEditorService $editor,
    ) {
    }

    public function edit(StorefrontListing $listing): View
    {
        $this->authorize('update', $listing);

        $listing->load('media');

        $categories = StorefrontCategory::query()
            ->where('organization_id', $listing->organization_id)
            ->active()->ordered()->get();

        $pricing = app(StorefrontPigPricingService::class);
        $pool    = app(StorefrontPigPoolService::class);

        $sourceCategory = $listing->source_category ?: $this->sourceCategoryForListing($listing);
        $categoryOptions = $this->categoryOptions();
        $stockSearch = request()->string('stock_search')->toString() ?: null;

        $isEditablePool = in_array($sourceCategory, [
            StorefrontPigPricingService::CATEGORY_BREEDING,
            StorefrontPigPricingService::CATEGORY_STANDARD,
        ], true);

        $protectedPoolPigIds = $listing->listingPigs()
            ->whereIn('status', array_diff(
                StorefrontListingPig::ACTIVE_STATUSES,
                StorefrontListingPig::SELLABLE_STATUSES,
            ))
            ->pluck('pig_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $poolCandidates = $isEditablePool
            ? $pool->candidatesForCategory((int) $listing->organization_id, $sourceCategory, $listing, $stockSearch)
                ->reject(fn ($pig) => in_array((int) $pig->getKey(), $protectedPoolPigIds, true))
                ->values()
            : collect();

        $selectedPoolPigIds = old('pool_pig_ids') !== null
            ? array_map('strval', (array) old('pool_pig_ids', []))
            : $listing->listingPigs()
                ->whereIn('status', StorefrontListingPig::SELLABLE_STATUSES)
                ->pluck('pig_id')
                ->map(fn ($id) => (string) $id)
                ->all();

        return view('storefront.admin.listings.edit', [
            'listing'            => $listing,
            'categories'         => $categories,
            'kinds'              => StorefrontListing::KINDS,
            'modes'              => StorefrontListing::MODES,
            'bases'              => StorefrontListing::BASES,
            'sourceCategory'     => $sourceCategory,
            'categoryOptions'    => $categoryOptions,
            'stockSearch'        => $stockSearch,
            'poolCandidates'     => $poolCandidates,
            'selectedPoolPigIds' => $selectedPoolPigIds,
            'livePricePerKg'     => $listing->live_price_per_kg_snapshot ?: $pricing->livePricePerKg(),
            'carcassPricePerKg'  => $listing->carcass_price_per_kg_snapshot ?: $pricing->carcassPricePerKg(),
            'poolSummary'        => $pricing->summarise($poolCandidates),
            'poolRows'           => $poolCandidates->mapWithKeys(fn ($pig) => [
                $pig->getKey() => [
                    'weight' => $pricing->latestWeightKg($pig),
                    'value'  => $pricing->liveValueOf($pig, $listing->live_price_per_kg_snapshot ?: null),
                ],
            ]),
        ]);
    }

    public function update(UpdateListingRequest $request, StorefrontListing $listing): RedirectResponse
    {
        if (($request->hasFile('image') || $request->filled('media_url'))
            && ! $request->user()->hasPermission('manage_storefront_media')) {
            abort(403);
        }

        try {
            $this->editor->update($listing, $request->validated(), $request->user());
        } catch (\App\Storefront\Services\Exceptions\InventoryConflictException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        if ($request->hasFile('image')) {
            $this->editor->addUploadedMedia($listing, $request->file('image'), $request->input('alt_text'));
        } elseif ($url = $request->input('media_url')) {
            $this->editor->addUrlMedia($listing, $url, StorefrontListingMedia::TYPE_IMAGE, $request->input('alt_text'));
        }

        return redirect()
            ->route('storefront.admin.listings.show', $listing)
            ->with('success', "Listing {$listing->reference} updated.");
    }

    protected function sourceCategoryForListing(StorefrontListing $listing): string
    {
        return match ($listing->kind) {
            StorefrontListing::KIND_BREEDING_STOCK => StorefrontPigPricingService::CATEGORY_BREEDING,
            StorefrontListing::KIND_PIGLET_LOT => StorefrontPigPricingService::CATEGORY_PIGLETS,
            StorefrontListing::KIND_LIVE_PIG => StorefrontPigPricingService::CATEGORY_STANDARD,
            default => StorefrontPigPricingService::CATEGORY_MANUAL,
        };
    }

    /**
     * @return array<string, array{label: string, hint: string}>
     */
    protected function categoryOptions(): array
    {
        return [
            StorefrontPigPricingService::CATEGORY_BREEDING => [
                'label' => 'Breeding Stock',
                'hint'  => 'Sows, boars and gilts held for breeding.',
            ],
            StorefrontPigPricingService::CATEGORY_STANDARD => [
                'label' => 'Standard Stock',
                'hint'  => 'Grow-out and market-ready animals.',
            ],
            StorefrontPigPricingService::CATEGORY_PIGLETS => [
                'label' => 'Piglets',
                'hint'  => 'Sold by the litter; animals are created on approval.',
            ],
            StorefrontPigPricingService::CATEGORY_MANUAL => [
                'label' => 'Manual Entry',
                'hint'  => 'Feed, manure, services — nothing from the herd.',
            ],
        ];
    }

    public function addMedia(Request $request, StorefrontListing $listing): RedirectResponse
    {
        $this->authorize('update', $listing);

        $request->validate([
            'image'     => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'media_url' => ['nullable', 'url', 'max:500'],
            'alt_text'  => ['nullable', 'string', 'max:200'],
        ]);

        if ($request->hasFile('image')) {
            $this->editor->addUploadedMedia($listing, $request->file('image'), $request->input('alt_text'));
        } elseif ($url = $request->input('media_url')) {
            $this->editor->addUrlMedia($listing, $url, StorefrontListingMedia::TYPE_IMAGE, $request->input('alt_text'));
        } else {
            return back()->with('error', 'Provide an image file or a media URL.');
        }

        return back()->with('success', 'Media added.');
    }

    public function setPrimaryMedia(StorefrontListing $listing, StorefrontListingMedia $media): RedirectResponse
    {
        $this->authorize('update', $listing);
        abort_unless($media->listing_id === $listing->id, 404);

        $this->editor->setPrimary($media);

        return back()->with('success', 'Primary image updated.');
    }

    public function removeMedia(StorefrontListing $listing, StorefrontListingMedia $media): RedirectResponse
    {
        $this->authorize('update', $listing);
        abort_unless($media->listing_id === $listing->id, 404);

        $this->editor->removeMedia($media);

        return back()->with('success', 'Media removed.');
    }
}
