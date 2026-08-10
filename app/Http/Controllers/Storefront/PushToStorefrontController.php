<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\PushToStorefrontRequest;
use App\Models\PigManagement;
use App\Models\Storefront\StorefrontCategory;
use App\Models\Storefront\StorefrontListing;
use App\Storefront\Services\Exceptions\DuplicateListingException;
use App\Storefront\Services\PushToStorefrontService;
use App\Storefront\Support\PushToStorefrontData;
use App\Support\CurrentOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        return view('storefront.admin.listings.push', [
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

        if (! $request->categoryKindMatches()) {
            return back()->withInput()
                ->with('error', 'The chosen category does not match the listing kind.');
        }

        $source = $this->resolveSource($request);

        $data = PushToStorefrontData::fromArray($request->validated());

        try {
            $listing = $this->pushService->execute($source, $request->user(), $data);
        } catch (DuplicateListingException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\App\Storefront\Services\Exceptions\ListingNotEligibleException $e) {
            // Every reason at once, as a list rather than one run-on sentence.
            return back()->withInput()
                ->with('error', 'This pig cannot be listed for sale.')
                ->with('eligibility_blockers', $e->reasons);
        }

        return redirect()
            ->route('storefront.admin.listings.show', $listing)
            ->with('success', "Listing {$listing->reference} created" . ($listing->status === StorefrontListing::STATUS_PUBLISHED ? ' and published.' : ' as a draft.'));
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
