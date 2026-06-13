<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\CounterpartyAddress;
use App\Storefront\Services\CustomerAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Customer "My Account" area. Gated by auth (any authenticated user with an
 * org context); the storefront customer role naturally has no back-office
 * access. The account is backed by CustomerProfile + Counterparty +
 * CounterpartyAddress — the same AR structures finance already uses, so orders
 * (Phase 3) and invoices/receipts (Phase 4) attach with no extra plumbing.
 *
 * Phase 1 scope: dashboard, profile view, and address listing/creation.
 * Orders/quotes/saved-items tabs are scaffolded and filled in Phases 2–5.
 */
class CustomerAccountController extends Controller
{
    public function __construct(protected CustomerAccountService $accounts)
    {
    }

    public function dashboard(Request $request): View
    {
        $profile = $this->accounts->ensureProfile($request->user());
        $profile->load('counterparty');

        return view('storefront.account.dashboard', [
            'profile'     => $profile,
            'counterparty'=> $profile->counterparty,
        ]);
    }

    public function profile(Request $request): View
    {
        $profile = $this->accounts->ensureProfile($request->user());
        $profile->load('counterparty');

        return view('storefront.account.profile', [
            'profile'      => $profile,
            'counterparty' => $profile->counterparty,
            'user'         => $request->user(),
        ]);
    }

    public function addresses(Request $request): View
    {
        $profile = $this->accounts->ensureProfile($request->user());

        $addresses = CounterpartyAddress::query()
            ->where('counterparty_id', $profile->counterparty_id)
            ->orderByDesc('is_default')
            ->get();

        return view('storefront.account.addresses', [
            'profile'   => $profile,
            'addresses' => $addresses,
        ]);
    }

    public function savedItems(Request $request): View
    {
        $this->accounts->ensureProfile($request->user());

        $favourites = \App\Models\Storefront\StorefrontFavourite::query()
            ->where('user_id', $request->user()->id)
            ->with(['listing.media'])
            ->latest()
            ->get()
            ->filter(fn ($f) => $f->listing !== null)
            ->values();

        return view('storefront.account.saved', [
            'favourites' => $favourites,
        ]);
    }

    public function quotes(Request $request): View
    {
        $profile = $this->accounts->ensureProfile($request->user());

        $quotes = \App\Models\Storefront\StorefrontQuote::query()
            ->where(function ($q) use ($request, $profile) {
                $q->where('customer_user_id', $request->user()->id)
                    ->orWhere('counterparty_id', $profile->counterparty_id);
            })
            ->withCount('items')
            ->latest()
            ->get();

        return view('storefront.account.quotes', [
            'quotes' => $quotes,
        ]);
    }

    public function storeAddress(Request $request): RedirectResponse
    {
        $profile = $this->accounts->ensureProfile($request->user());

        $validated = $request->validate([
            'label'          => ['nullable', 'string', 'max:60'],
            'attention_to'   => ['nullable', 'string', 'max:120'],
            'address_line_1' => ['required', 'string', 'max:200'],
            'address_line_2' => ['nullable', 'string', 'max:200'],
            'city'           => ['required', 'string', 'max:120'],
            'region'         => ['nullable', 'string', 'max:120'],
            'ghana_post_gps' => ['nullable', 'string', 'max:40'],
            'is_default'     => ['boolean'],
        ]);

        $isDefault = $request->boolean('is_default');

        if ($isDefault) {
            CounterpartyAddress::query()
                ->where('counterparty_id', $profile->counterparty_id)
                ->update(['is_default' => false]);
        }

        CounterpartyAddress::create([
            'organization_id' => $profile->organization_id,
            'counterparty_id' => $profile->counterparty_id,
            'address_type'    => 'shipping',
            'label'           => $validated['label'] ?? 'Delivery address',
            'attention_to'    => $validated['attention_to'] ?? $request->user()->name,
            'address_line_1'  => $validated['address_line_1'],
            'address_line_2'  => $validated['address_line_2'] ?? null,
            'city'            => $validated['city'],
            'region'          => $validated['region'] ?? null,
            'country'         => 'Ghana',
            'ghana_post_gps'  => $validated['ghana_post_gps'] ?? null,
            'is_default'      => $isDefault,
            'is_active'       => true,
        ]);

        return back()->with('success', 'Address saved.');
    }
}
