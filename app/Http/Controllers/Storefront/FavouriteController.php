<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Storefront\StorefrontListing;
use App\Storefront\Services\FavouriteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Saved items (favourites) toggle for authenticated customers.
 */
class FavouriteController extends Controller
{
    public function __construct(protected FavouriteService $favourites)
    {
    }

    public function toggle(Request $request, StorefrontListing $listing): RedirectResponse
    {
        $nowFavourited = $this->favourites->toggle($request->user(), $listing);

        return back()->with(
            'success',
            $nowFavourited ? 'Saved to your list.' : 'Removed from your list.'
        );
    }
}
