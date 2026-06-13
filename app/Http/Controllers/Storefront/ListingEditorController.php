<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\UpdateListingRequest;
use App\Models\Storefront\StorefrontCategory;
use App\Models\Storefront\StorefrontListing;
use App\Models\Storefront\StorefrontListingMedia;
use App\Storefront\Services\ListingEditorService;
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

        return view('storefront.admin.listings.edit', [
            'listing'    => $listing,
            'categories' => $categories,
            'kinds'      => StorefrontListing::KINDS,
            'modes'      => StorefrontListing::MODES,
            'bases'      => StorefrontListing::BASES,
        ]);
    }

    public function update(UpdateListingRequest $request, StorefrontListing $listing): RedirectResponse
    {
        $this->editor->update($listing, $request->validated(), $request->user());

        return redirect()
            ->route('storefront.admin.listings.show', $listing)
            ->with('success', "Listing {$listing->reference} updated.");
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
