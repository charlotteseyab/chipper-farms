<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Storefront\StorefrontOrder;
use App\Models\Storefront\StorefrontBuyer;
use App\Storefront\Services\CustomerAccountService;
use App\Storefront\Support\StorefrontSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * A guest's order: tracking it, and later claiming it (Phase 9, §38).
 *
 * THE TOKEN IS THE CREDENTIAL
 * ---------------------------
 * A guest has no account, so the only thing that distinguishes them from
 * anybody else typing order references is the single-use token they were given
 * at checkout. Every lookup here matches on **both** the reference and the
 * token, and compares the token with `hash_equals` — a plain `===` on a secret
 * leaks its prefix through timing, and a lookup by reference alone would make
 * every order in the system readable by anyone who can count.
 *
 * WHY CLAIMING NEEDS A TOKEN AND NOT AN EMAIL
 * -------------------------------------------
 * The obvious alternative — attach guest orders to whoever later registers with
 * the same email — makes a stranger's order history a prize for registering
 * with their address, and this application does not prove email ownership
 * before login. Presenting the token proves the claimant is the person who was
 * standing at the checkout.
 *
 * The token is cleared the moment it is used, so a confirmation link forwarded
 * to a colleague cannot be replayed to attach the order to a second account.
 * The order stays reachable afterwards through the buyer's account.
 *
 * NOT CLOSED WHEN THE SHOP IS CLOSED
 * ----------------------------------
 * Deliberately outside the `storefront.open` gate, for the same reason as the
 * Paystack webhook: an order already placed must stay visible to the person who
 * placed it, whatever the farm has since done with its shop.
 */
class GuestOrderController extends Controller
{
    public function __construct(
        protected CustomerAccountService $accounts,
    ) {
    }

    public function track(Request $request, string $reference, string $token): View
    {
        $order = $this->locate($reference, $token);

        $order->load(['items', 'events']);

        return view('storefront.orders.guest-track', [
            'order'        => $order,
            'token'        => $token,
            'canClaim'     => $request->user() !== null && $order->customer_user_id === null,
            'supportEmail' => StorefrontSettings::supportEmail($order->organization_id),
            'supportPhone' => StorefrontSettings::supportPhone($order->organization_id),
            'buyerAccountExists' => filled($order->guest_email) && StorefrontBuyer::query()
                ->where('organization_id', $order->organization_id)
                ->where('email', strtolower(trim($order->guest_email)))
                ->exists(),
        ]);
    }

    /**
     * Attach a guest order to the signed-in account.
     */
    public function claim(Request $request, string $reference, string $token): RedirectResponse
    {
        $order = $this->locate($reference, $token);

        $user = $request->user();

        // Already attached to somebody. Not an error worth a stack trace, but
        // definitely not something to silently redo — re-pointing an order at a
        // second account would rewrite whose purchase it was.
        if ($order->customer_user_id !== null) {
            return redirect()
                ->route('storefront.order.track', ['reference' => $reference, 'token' => $token])
                ->with('error', 'This order already belongs to an account.');
        }

        DB::transaction(function () use ($order, $user): void {
            $profile = $this->accounts->ensureProfile($user);

            $order->update([
                'customer_user_id'  => $user->id,
                // The account's counterparty becomes the order's, so the buyer's
                // ledger, statement and ageing all show it. Where the guest was
                // matched to the same counterparty by email this is a no-op.
                'counterparty_id'   => $profile->counterparty_id,
                'guest_claim_token' => null,
                'claimed_at'        => now(),
            ]);
        });

        return redirect()
            ->route('storefront.account.orders.show', $order)
            ->with('success', "Order {$order->reference} is now on your account.");
    }

    /**
     * Find the order by reference AND token, in constant time on the token.
     */
    protected function locate(string $reference, string $token): StorefrontOrder
    {
        // The reference narrows the search; the token is what proves ownership,
        // so it is compared in PHP with hash_equals rather than in the WHERE
        // clause. A database comparison on a secret is a timing oracle and, on
        // some collations, a case-insensitive match.
        //
        // `get()` rather than `first()`: the reference is unique per
        // ORGANISATION, not globally, so two farms can both have SO-0001. Taking
        // the first row and testing its token would 404 a valid link whenever
        // another tenant happened to sort ahead.
        $candidates = StorefrontOrder::query()
            ->where('reference', $reference)
            ->whereNotNull('guest_claim_token')
            ->get();

        foreach ($candidates as $candidate) {
            if (is_string($candidate->guest_claim_token)
                && hash_equals($candidate->guest_claim_token, $token)) {
                return $candidate;
            }
        }

        abort(404);
    }
}
