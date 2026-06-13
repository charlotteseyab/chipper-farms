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
}
