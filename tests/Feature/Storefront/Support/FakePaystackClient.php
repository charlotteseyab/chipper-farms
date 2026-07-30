<?php

namespace Tests\Feature\Storefront\Support;

use App\Storefront\Payments\PaystackClient;

/**
 * In-memory Paystack client for tests. Lets a test script the verify result
 * (status/amount/currency) so the whole payment flow is exercised without
 * network access.
 */
class FakePaystackClient implements PaystackClient
{
    public array $initialised = [];

    /** @var array{status:string,amount:int,currency:string}|null */
    public ?array $verifyResult = null;

    public function initializeTransaction(
        string $email,
        int $amountMinor,
        string $reference,
        string $currency,
        ?string $callbackUrl = null,
        array $metadata = [],
    ): array {
        $this->initialised[] = compact('email', 'amountMinor', 'reference', 'currency');

        return [
            'authorization_url' => 'https://checkout.paystack.test/' . $reference,
            'access_code'       => 'ac_' . $reference,
            'reference'         => $reference,
        ];
    }

    public function verifyTransaction(string $reference): array
    {
        // Default: success for the exact amount last initialised.
        $result = $this->verifyResult ?? [
            'status'   => 'success',
            'amount'   => $this->initialised[0]['amountMinor'] ?? 0,
            'currency' => $this->initialised[0]['currency'] ?? 'GHS',
        ];

        return [
            'status'    => $result['status'],
            'amount'    => $result['amount'],
            'currency'  => $result['currency'],
            'reference' => $reference,
            'raw'       => ['reference' => $reference] + $result,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Refunds (CF-P0-007)                                                */
    /* ------------------------------------------------------------------ */

    /** Every refund submission, so tests can assert the gateway was actually called. */
    public array $refunds = [];

    /**
     * Scripted refund outcome. Null = accepted as 'pending', which is what
     * Paystack actually returns: refunds settle asynchronously, so 'pending' is
     * the realistic default rather than instant success.
     *
     * @var array{status:string, provider_reference?:?string}|null
     */
    public ?array $refundResult = null;

    /** When set, refundTransaction() throws this instead of returning. */
    public ?\Throwable $refundException = null;

    public function refundTransaction(
        string $transactionReference,
        ?int $amountMinor = null,
        ?string $reason = null,
        ?string $idempotencyKey = null,
    ): array {
        $this->refunds[] = compact('transactionReference', 'amountMinor', 'reason', 'idempotencyKey');

        if ($this->refundException !== null) {
            throw $this->refundException;
        }

        $result = $this->refundResult ?? ['status' => 'pending'];

        return [
            'status'             => $result['status'],
            'provider_reference' => $result['provider_reference']
                ?? ('rf_' . substr(md5($transactionReference . count($this->refunds)), 0, 12)),
            'amount'             => $amountMinor,
            'currency'           => $this->initialised[0]['currency'] ?? 'GHS',
            'raw'                => ['transaction' => $transactionReference] + $result,
        ];
    }

    /** Number of times a refund was submitted for a given transaction. */
    public function refundCountFor(string $transactionReference): int
    {
        return count(array_filter(
            $this->refunds,
            fn (array $r) => $r['transactionReference'] === $transactionReference,
        ));
    }
}
