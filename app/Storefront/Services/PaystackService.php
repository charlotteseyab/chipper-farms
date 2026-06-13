<?php

namespace App\Storefront\Services;

use App\Models\Storefront\StorefrontOrder;
use App\Models\Storefront\StorefrontOrderPayment;
use App\Models\User;
use App\Storefront\Payments\PaystackClient;
use App\Storefront\Services\Exceptions\PaymentException;
use Illuminate\Support\Str;

/**
 * Orchestrates Paystack for storefront orders.
 *
 * Security posture:
 *   - Verification is ALWAYS server-side (verifyTransaction), never trusting a
 *     client redirect or a webhook body alone.
 *   - On verify we assert the gateway status is "success" AND the amount and
 *     currency match the order — a mismatch is treated as fraud and rejected.
 *   - A verified payment advances the order to payment_confirmed only; it never
 *     auto-confirms (staff confirm explicitly).
 *   - Idempotent: re-verifying an already-verified payment is a no-op.
 */
class PaystackService
{
    public function __construct(
        protected PaystackClient $client,
        protected PaymentService $payments,
    ) {
    }

    /**
     * Initialise a transaction for an order and return the Paystack
     * authorization URL the customer is redirected to.
     */
    public function initialise(StorefrontOrder $order, ?string $callbackUrl = null): array
    {
        if ($order->payment_method !== StorefrontOrder::METHOD_PAYSTACK) {
            throw new PaymentException('Order is not a Paystack order.');
        }

        $email = $this->emailFor($order);
        if (empty($email)) {
            throw new PaymentException('A customer email is required for Paystack payment.');
        }

        $reference  = $this->generateReference($order);
        $amountMinor = (int) bcmul((string) $order->total, '100', 0);
        $currency    = $order->currency_code ?: (string) config('services.paystack.currency', 'GHS');

        $result = $this->client->initializeTransaction(
            email:       $email,
            amountMinor: $amountMinor,
            reference:   $reference,
            currency:    $currency,
            callbackUrl: $callbackUrl,
            metadata:    ['order_id' => $order->id, 'order_reference' => $order->reference],
        );

        $this->payments->recordPaystackInitiated($order, $reference, (string) $order->total);

        return $result;
    }

    /**
     * Verify a transaction by reference, validate it against the order, and on
     * success advance the order. Returns the (now verified) payment, or throws.
     */
    public function verify(string $reference, ?User $actor = null): StorefrontOrderPayment
    {
        $payment = StorefrontOrderPayment::query()
            ->where('provider', StorefrontOrderPayment::PROVIDER_PAYSTACK)
            ->where('provider_reference', $reference)
            ->first();

        if ($payment === null) {
            throw new PaymentException("No Paystack payment found for reference {$reference}.");
        }

        // Idempotent short-circuit.
        if ($payment->status === StorefrontOrderPayment::STATUS_VERIFIED) {
            return $payment;
        }

        $order = $payment->order;
        if ($order === null) {
            throw new PaymentException('Payment is not linked to an order.');
        }

        $result = $this->client->verifyTransaction($reference);

        // Gateway must report success.
        if (($result['status'] ?? null) !== 'success') {
            $this->payments->markPaystackFailed($payment, $result['raw'] ?? $result);
            throw new PaymentException('Paystack reports the transaction was not successful.');
        }

        // Amount + currency must match the order exactly (anti-tamper).
        $expectedMinor = (int) bcmul((string) $order->total, '100', 0);
        if ((int) $result['amount'] !== $expectedMinor) {
            $this->payments->markPaystackFailed($payment, $result['raw'] ?? $result);
            throw new PaymentException('Paid amount does not match the order total.');
        }

        $expectedCurrency = $order->currency_code ?: (string) config('services.paystack.currency', 'GHS');
        if (strtoupper((string) $result['currency']) !== strtoupper($expectedCurrency)) {
            $this->payments->markPaystackFailed($payment, $result['raw'] ?? $result);
            throw new PaymentException('Payment currency does not match the order.');
        }

        return $this->payments->markPaystackVerified($payment, $result['raw'] ?? $result, $actor);
    }

    /**
     * Resolve the order for an inbound webhook reference (used by the webhook
     * controller after signature validation), then verify server-side.
     */
    public function handleVerifiedReference(string $reference): ?StorefrontOrderPayment
    {
        $exists = StorefrontOrderPayment::query()
            ->where('provider', StorefrontOrderPayment::PROVIDER_PAYSTACK)
            ->where('provider_reference', $reference)
            ->exists();

        if (! $exists) {
            return null;
        }

        return $this->verify($reference);
    }

    protected function emailFor(StorefrontOrder $order): ?string
    {
        return $order->counterparty?->email
            ?? $order->customer?->email;
    }

    protected function generateReference(StorefrontOrder $order): string
    {
        return 'PSK-' . $order->reference . '-' . strtoupper(Str::random(8));
    }
}
