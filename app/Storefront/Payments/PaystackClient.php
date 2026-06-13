<?php

namespace App\Storefront\Payments;

/**
 * Thin gateway abstraction over the Paystack REST API. Implemented by
 * HttpPaystackClient in production and faked in tests, so the payment flow is
 * fully testable without network access.
 *
 * Amounts are in the gateway's minor unit (pesewas for GHS), matching
 * Paystack's API contract.
 */
interface PaystackClient
{
    /**
     * Initialise a transaction.
     *
     * @param  array<string,mixed>  $metadata
     * @return array{authorization_url:string, access_code:string, reference:string}
     */
    public function initializeTransaction(
        string $email,
        int $amountMinor,
        string $reference,
        string $currency,
        ?string $callbackUrl = null,
        array $metadata = [],
    ): array;

    /**
     * Verify a transaction by reference.
     *
     * @return array{status:string, amount:int, currency:string, reference:string, raw:array<string,mixed>}
     */
    public function verifyTransaction(string $reference): array;
}
