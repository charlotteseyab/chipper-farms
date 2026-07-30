<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Storefront\StorefrontOrderPayment;
use App\Storefront\Payments\PaystackSignature;
use App\Storefront\Services\Exceptions\PaymentException;
use App\Storefront\Services\PaystackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Paystack return-URL callback (browser) and server-to-server webhook.
 *
 * Both paths VERIFY SERVER-SIDE — the browser redirect is never trusted on its
 * own, and the webhook body is authenticated by HMAC signature before any
 * lookup. Verification advances the order to payment_confirmed only; a human
 * still confirms.
 */
class PaystackWebhookController extends Controller
{
    public function __construct(protected PaystackService $paystack)
    {
    }

    /**
     * Customer is redirected back here from Paystack with ?reference=...
     */
    public function callback(Request $request): RedirectResponse
    {
        $reference = (string) $request->query('reference', '');

        if ($reference === '') {
            return redirect()->route('storefront.account.orders.index')
                ->with('error', 'Missing payment reference.');
        }

        $payment = StorefrontOrderPayment::query()
            ->where('provider', StorefrontOrderPayment::PROVIDER_PAYSTACK)
            ->where('provider_reference', $reference)
            ->first();

        $order = $payment?->order;

        try {
            $this->paystack->verify($reference);
        } catch (PaymentException $e) {
            $target = $order
                ? redirect()->route('storefront.account.orders.show', $order)
                : redirect()->route('storefront.account.orders.index');

            // CF-P1-020: this used to concatenate $e->getMessage(), which
            // HttpPaystackClient built from the raw gateway response body — so
            // Paystack's JSON was flashed into the customer's browser.
            // customerMessage() is safe by construction and carries only a
            // correlation id, which support can use to find the logged detail.
            return $target->with('error', $e->customerMessage());
        }

        return ($order
            ? redirect()->route('storefront.account.orders.show', $order)
            : redirect()->route('storefront.account.orders.index'))
            ->with('success', 'Payment received. Your order is now awaiting confirmation.');
    }

    /**
     * Server-to-server webhook. CSRF-exempt (see bootstrap/app.php); authenticated
     * by signature, then re-verified server-side.
     */
    public function webhook(Request $request): JsonResponse
    {
        $rawBody   = $request->getContent();
        $signature = $request->header('x-paystack-signature');
        $secret    = config('services.paystack.secret_key');

        if (! PaystackSignature::isValid($rawBody, $signature, $secret)) {
            Log::warning('Paystack webhook rejected: invalid signature.');
            return response()->json(['status' => 'invalid signature'], 401);
        }

        $payload = json_decode($rawBody, true) ?: [];
        $event   = $payload['event'] ?? null;
        $reference = $payload['data']['reference'] ?? null;

        if ($event === 'charge.success' && is_string($reference) && $reference !== '') {
            try {
                // Re-verify server-side (never trust the webhook body's amount).
                $this->paystack->handleVerifiedReference($reference);
            } catch (PaymentException $e) {
                Log::warning('Paystack webhook verify failed', ['reference' => $reference, 'error' => $e->getMessage()]);
                // 200 anyway so Paystack does not hammer retries for a business
                // rule failure; the payment row is marked failed for ops review.
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
