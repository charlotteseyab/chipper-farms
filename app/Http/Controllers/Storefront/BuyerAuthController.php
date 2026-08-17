<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Storefront\StorefrontBuyer;
use App\Models\Storefront\StorefrontOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BuyerAuthController extends Controller
{
    public function showLogin(): View
    {
        return view('storefront.buyer.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email'    => ['required', 'email', 'max:190'],
            'password' => ['required', 'string'],
        ]);

        $buyer = StorefrontBuyer::query()
            ->where('email', strtolower($data['email']))
            ->when(app(\App\Support\CurrentOrganization::class)->id(), fn ($q, $orgId) => $q->where('organization_id', $orgId))
            ->first();

        if (! $buyer || ! Hash::check($data['password'], $buyer->password)) {
            throw ValidationException::withMessages([
                'email' => 'These buyer account details do not match our records.',
            ]);
        }

        $buyer->forceFill(['last_login_at' => now()])->save();
        $request->session()->put('storefront_buyer_id', $buyer->getKey());
        $request->session()->regenerate();

        return redirect()->route('storefront.buyer.orders.index');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('storefront_buyer_id');

        return redirect()->route('storefront.home')->with('success', 'You have signed out of your buyer account.');
    }

    public function createFromOrder(Request $request, string $reference, string $token): View
    {
        $order = $this->locateGuestOrder($reference, $token);

        return view('storefront.buyer.register-from-order', [
            'order' => $order,
            'token' => $token,
        ]);
    }

    public function storeFromOrder(Request $request, string $reference, string $token): RedirectResponse
    {
        $order = $this->locateGuestOrder($reference, $token);

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:200'],
            'email'    => ['required', 'email', 'max:190'],
            'phone'    => ['nullable', 'string', 'max:60'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $email = strtolower(trim($data['email']));

        if (StorefrontBuyer::query()
            ->where('organization_id', $order->organization_id)
            ->where('email', $email)
            ->exists()) {
            throw ValidationException::withMessages([
                'email' => 'A buyer account already exists for this email. Sign in to continue.',
            ]);
        }

        $buyer = DB::transaction(function () use ($order, $data, $email): StorefrontBuyer {
            $buyer = StorefrontBuyer::create([
                'organization_id' => $order->organization_id,
                'counterparty_id' => $order->counterparty_id,
                'name'            => trim($data['name']),
                'email'           => $email,
                'phone'           => $data['phone'] ?? $order->guest_phone,
                'password'        => Hash::make($data['password']),
                'last_login_at'   => now(),
            ]);

            $order->forceFill([
                'storefront_buyer_id' => $buyer->getKey(),
                'guest_claim_token'   => null,
                'claimed_at'          => now(),
            ])->save();

            return $buyer;
        });

        $request->session()->put('storefront_buyer_id', $buyer->getKey());
        $request->session()->regenerate();

        return redirect()
            ->route('storefront.buyer.orders.show', $order)
            ->with('success', 'Your buyer account is ready and this order is saved to it.');
    }

    public function orders(Request $request): View|RedirectResponse
    {
        $buyer = $this->buyer($request);
        if (! $buyer) {
            return redirect()->route('storefront.buyer.login')->with('error', 'Sign in to your buyer account first.');
        }

        $orders = StorefrontOrder::query()
            ->where('storefront_buyer_id', $buyer->getKey())
            ->latest('placed_at')
            ->latest('id')
            ->get();

        return view('storefront.buyer.orders.index', [
            'buyer' => $buyer,
            'orders' => $orders,
        ]);
    }

    public function order(Request $request, StorefrontOrder $order): View|RedirectResponse
    {
        $buyer = $this->buyer($request);
        if (! $buyer) {
            return redirect()->route('storefront.buyer.login')->with('error', 'Sign in to your buyer account first.');
        }

        abort_unless((int) $order->storefront_buyer_id === (int) $buyer->getKey(), 404);

        $order->load(['items', 'events']);

        return view('storefront.buyer.orders.show', [
            'buyer' => $buyer,
            'order' => $order,
        ]);
    }

    protected function buyer(Request $request): ?StorefrontBuyer
    {
        $id = $request->session()->get('storefront_buyer_id');
        $buyer = $id ? StorefrontBuyer::find($id) : null;

        return $buyer;
    }

    protected function locateGuestOrder(string $reference, string $token): StorefrontOrder
    {
        $orders = StorefrontOrder::query()
            ->where('reference', $reference)
            ->whereNotNull('guest_claim_token')
            ->get();

        foreach ($orders as $order) {
            if (is_string($order->guest_claim_token) && hash_equals($order->guest_claim_token, $token)) {
                return $order;
            }
        }

        abort(404);
    }
}
