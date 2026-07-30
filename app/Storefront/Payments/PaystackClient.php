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

    /**
     * Request a refund against a settled transaction (CF-P0-007).
     *
     * This method did not exist. The abstraction exposed only initialize and
     * verify, so StorefrontOrderRefundService had no way to return money — it
     * set the order to 'refunded' and the payment to 'refunded' without ever
     * contacting the gateway. Customers were told they had been refunded while
     * the funds remained with the merchant.
     *
     * Contract notes:
     *
     *  - $amountMinor is optional. Null means a FULL refund of the original
     *    transaction, which is Paystack's own default when `amount` is omitted.
     *    Passing an amount performs a partial refund.
     *
     *  - Refunds are ASYNCHRONOUS at Paystack. A successful API call means the
     *    refund was accepted, not that the customer has their money: the
     *    returned status is typically 'pending' or 'processing' and settles
     *    later, notified by a refund.processed webhook. Callers must therefore
     *    treat a successful return as "submitted", never as "completed" — which
     *    is precisely the mistake the previous implementation made without even
     *    calling the API.
     *
     *  - $idempotencyKey lets a retried submission be recognised rather than
     *    duplicated. A double-submitted refund is real money leaving twice.
     *
     * @param  string  $transactionReference  the original transaction reference
     * @param  int|null  $amountMinor         null = full refund
     * @return array{status:string, provider_reference:?string, amount:?int, currency:?string, raw:array<string,mixed>}
     *
     * @throws \App\Storefront\Services\Exceptions\PaymentException
     */
    public function refundTransaction(
        string $transactionReference,
        ?int $amountMinor = null,
        ?string $reason = null,
        ?string $idempotencyKey = null,
    ): array;
}
