<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\PushToStorefrontRequest;
use App\Models\PigManagement;
use App\Models\Storefront\StorefrontCategory;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontListingMedia;
use App\Storefront\Services\Exceptions\DuplicateListingException;
use App\Storefront\Services\ListingEditorService;
use App\Storefront\Services\PushToStorefrontService;
use App\Storefront\Support\PushToStorefrontData;
use App\Support\CurrentOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Push-to-Storefront wizard.
 *
 * Two entry points:
 *   - From a pig record: create(source=pig) pre-fills the form from operational
 *     data (tag, breed, weight) without ever writing back to the pig.
 *   - Standalone: create() with no source for a manual product listing
 *     (feed bag, manure, service line).
 *
 * The store action delegates ALL business logic to PushToStorefrontService;
 * the controller only resolves the source, validates, and redirects.
 */
class PushToStorefrontController extends Controller
{
    public function __construct(
        protected CurrentOrganization $currentOrganization,
        protected PushToStorefrontService $pushService,
        protected ListingEditorService $editor,
    ) {
    }

    /**
     * Show the wizard. Optional ?pig={id} pre-selects a pig source.
     */
    public function create(Request $request): View
    {
        $this->authorize('create', StorefrontListing::class);

        $orgId = $this->currentOrganization->requireId();

        $source       = null;
        $sourceKind   = null;
        $prefill      = [];
        // Phase 11 (§35 step 2): checked when the wizard OPENS, so a staff
        // member sees why an animal cannot be sold before filling in a form
        // that will be refused.
        $eligibility  = ['eligible' => true, 'blockers' => [], 'cautions' => []];

        if ($pigId = $request->integer('pig')) {
            /** @var PigManagement|null $pig */
            $pig = PigManagement::find($pigId);
            if ($pig) {
                $source     = $pig;
                $sourceKind = $this->kindForPig($pig);
                $prefill = [
                    'title'               => $this->defaultPigTitle($pig),
                    'kind'                => $sourceKind,
                    'estimated_weight_kg' => $pig->current_weight_kg_cache,
                    'quantity_available'  => 1,
                    'quantity_unit'       => 'head',
                    'pricing_basis'       => StorefrontListing::BASIS_LIVE,
                    'sale_mode'           => StorefrontListing::MODE_FIXED,
                ];

                $eligibility = app(\App\Storefront\Services\PigListingEligibilityService::class)
                    ->check($pig, StorefrontListing::BASIS_LIVE, $orgId);
            }
        }

        $categories = StorefrontCategory::query()
            ->where('organization_id', $orgId)
            ->active()->ordered()->get();

        // WHICH STOCK THIS LISTING DRAWS FROM
        //
        // Staff choose a source CATEGORY — breeding stock, standard stock,
        // piglets, or manual — and that decides both the animals on offer and
        // the resulting listing kind. Nobody publishing a listing should have
        // to know that "standard stock" means kind `live_pig`.
        //
        // Offered whenever the wizard is not already pinned to one animal via
        // ?pig=, which is the older "this exact animal" shape and still works.
        $pricing  = app(\App\Storefront\Services\StorefrontPigPricingService::class);
        $pool     = app(\App\Storefront\Services\StorefrontPigPoolService::class);

        $category = $request->string('source_category')->toString()
            ?: ($sourceKind === StorefrontListing::KIND_BREEDING_STOCK
                ? \App\Storefront\Services\StorefrontPigPricingService::CATEGORY_BREEDING
                : \App\Storefront\Services\StorefrontPigPricingService::CATEGORY_STANDARD);

        $search = $request->string('stock_search')->toString() ?: null;

        $poolCandidates = $source === null
            ? $pool->candidatesForCategory($orgId, $category, null, $search)
            : collect();

        return view('storefront.admin.listings.push', [
            'poolCandidates'  => $poolCandidates,
            'sourceCategory'  => $category,
            'stockSearch'     => $search,
            // The farm's own rates, shown so staff can see what the listing
            // will be priced from rather than typing a number themselves.
            'livePricePerKg'    => $pricing->livePricePerKg(),
            'carcassPricePerKg' => $pricing->carcassPricePerKg(),
            // Everything the selector displays is computed here. The browser
            // mirrors it for feedback; these are the numbers that count.
            'poolSummary'     => $pricing->summarise($poolCandidates),
            // Per-animal weight and value, computed HERE. The row markup needs
            // both, and a Blade template calling a pricing service once per row
            // is how N+1 queries and float arithmetic get into a view.
            'poolRows'        => $poolCandidates->mapWithKeys(fn ($pig) => [
                $pig->getKey() => [
                    'weight' => $pricing->latestWeightKg($pig),
                    'value'  => $pricing->liveValueOf($pig),
                ],
            ]),
            'categoryOptions' => [
                \App\Storefront\Services\StorefrontPigPricingService::CATEGORY_BREEDING => [
                    'label' => 'Breeding Stock',
                    'hint'  => 'Sows, boars and gilts held for breeding.',
                ],
                \App\Storefront\Services\StorefrontPigPricingService::CATEGORY_STANDARD => [
                    'label' => 'Standard Stock',
                    'hint'  => 'Grow-out and market-ready animals.',
                ],
                \App\Storefront\Services\StorefrontPigPricingService::CATEGORY_PIGLETS => [
                    'label' => 'Piglets',
                    'hint'  => 'Sold by the litter; animals are created on approval.',
                ],
                \App\Storefront\Services\StorefrontPigPricingService::CATEGORY_MANUAL => [
                    'label' => 'Manual Entry',
                    'hint'  => 'Feed, manure, services — nothing from the herd.',
                ],
            ],
            'source'      => $source,
            'sourceType'  => $source ? $source::class : null,
            'sourceId'    => $source?->getKey(),
            'sourceKind'  => $sourceKind,
            'prefill'     => $prefill,
            'categories'  => $categories,
            'kinds'       => StorefrontListing::KINDS,
            'modes'       => StorefrontListing::MODES,
            'bases'       => StorefrontListing::BASES,
            'eligibility' => $eligibility,
        ]);
    }

    public function store(PushToStorefrontRequest $request): RedirectResponse
    {
        $this->authorize('create', StorefrontListing::class);

        if (($request->hasFile('image') || $request->filled('media_url'))
            && ! $request->user()->hasPermission('manage_storefront_media')) {
            abort(403);
        }

        if (! $request->categoryKindMatches()) {
            return back()->withInput()
                ->with('error', 'The chosen category does not match the listing kind.');
        }

        $source = $this->resolveSource($request);

        $data = PushToStorefrontData::fromArray($request->validated());

        $poolPigIds = array_filter(array_map('intval', (array) $request->input('pool_pig_ids', [])));

        try {
            $listing = DB::transaction(function () use ($source, $request, $data, $poolPigIds) {
                $listing = $this->pushService->execute($source, $request->user(), $data);

                // Record which stock this listing draws from, so the shop and
                // the manager screens know how to treat it later.
                $listing->forceFill([
                    'source_category' => $request->input('source_category'),
                ])->save();

                if ($poolPigIds !== []) {
                    app(\App\Storefront\Services\StorefrontPigPoolService::class)
                        ->attachPigs($listing, $poolPigIds, $request->user());

                    // Price the listing from the farm's setup rates and the
                    // animals' latest weights. Derived, never typed.
                    app(\App\Storefront\Services\StorefrontPigPricingService::class)
                        ->refreshListingSnapshot($listing->fresh());
                }

                $this->attachInitialMedia($listing->fresh(), $request);

                return $listing->fresh();
            });
        } catch (DuplicateListingException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\App\Storefront\Services\Exceptions\ListingNotEligibleException $e) {
            // Every reason at once, as a list rather than one run-on sentence.
            return back()->withInput()
                ->with('error', 'This pig cannot be listed for sale.')
                ->with('eligibility_blockers', $e->reasons);
        } catch (\App\Storefront\Services\Exceptions\InventoryConflictException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('storefront.admin.listings.show', $listing)
            ->with('success', "Listing {$listing->reference} created" . ($listing->status === StorefrontListing::STATUS_PUBLISHED ? ' and published.' : ' as a draft.'));
    }

    protected function attachInitialMedia(StorefrontListing $listing, PushToStorefrontRequest $request): void
    {
        if ($request->hasFile('image')) {
            $this->editor->addUploadedMedia($listing, $request->file('image'), $request->input('alt_text'));
            return;
        }

        if ($url = $request->input('media_url')) {
            $this->editor->addUrlMedia(
                $listing,
                $url,
                StorefrontListingMedia::TYPE_IMAGE,
                $request->input('alt_text')
            );
        }
    }

    /**
     * Resolve the optional operational source from the hidden source_type /
     * source_id fields. Only whitelisted source models are accepted.
     */
    protected function resolveSource(Request $request): ?Model
    {
        $type = $request->input('source_type');
        $id   = $request->input('source_id');

        if (! $type || ! $id) {
            return null;
        }

        $allowed = [PigManagement::class, \App\Models\FeedType::class];
        if (! in_array($type, $allowed, true) || ! class_exists($type)) {
            return null;
        }

        return $type::query()->find($id);
    }

    protected function kindForPig(PigManagement $pig): string
    {
        // Breeding stock flagged via stock_type; otherwise a live pig.
        $stock = strtolower((string) $pig->stock_type);

        return str_contains($stock, 'breed')
            ? StorefrontListing::KIND_BREEDING_STOCK
            : StorefrontListing::KIND_LIVE_PIG;
    }

    protected function defaultPigTitle(PigManagement $pig): string
    {
        $tag   = $pig->ear_tag_number ?: ('Pig #' . $pig->id);
        $breed = $pig->breed && $pig->breed !== 'unknown' ? ucfirst($pig->breed) . ' ' : '';

        return trim($breed . 'pig — ' . $tag);
    }
}
