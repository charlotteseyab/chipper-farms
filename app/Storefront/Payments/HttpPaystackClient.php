<?php

namespace App\Storefront\Payments;

use App\Storefront\Services\Exceptions\PaymentException;
use Illuminate\Support\Facades\Http;

/**
 * Production Paystack client. All calls are authenticated with the secret key
 * and time-bounded. Any non-success gateway response is surfaced as a
 * PaymentException so callers fail closed.
 */
class HttpPaystackClient implements PaystackClient
{
    public function __construct(
        protected ?string $secretKey,
        protected string $baseUrl = 'https://api.paystack.co',
    ) {
    }

    public function initializeTransaction(
        string $email,
        int $amountMinor,
        string $reference,
        string $currency,
        ?string $callbackUrl = null,
        array $metadata = [],
    ): array {
        $payload = array_filter([
            'email'        => $email,
            'amount'       => $amountMinor,
            'reference'    => $reference,
            'currency'     => $currency,
            'callback_url' => $callbackUrl,
            'metadata'     => $metadata ?: null,
        ], fn ($v) => $v !== null);

        $response = $this->client()->post('/transaction/initialize', $payload);

        if (! $response->successful() || ! ($response->json('status') === true)) {
            throw new PaymentException('Paystack initialise failed: ' . $response->body());
        }

        $data = $response->json('data');

        return [
            'authorization_url' => (string) ($data['authorization_url'] ?? ''),
            'access_code'       => (string) ($data['access_code'] ?? ''),
            'reference'         => (string) ($data['reference'] ?? $reference),
        ];
    }

    public function verifyTransaction(string $reference): array
    {
        $response = $this->client()->get('/transaction/verify/' . urlencode($reference));

        if (! $response->successful() || ! ($response->json('status') === true)) {
            throw new PaymentException('Paystack verify failed: ' . $response->body());
        }

        $data = $response->json('data') ?? [];

        return [
            'status'    => (string) ($data['status'] ?? 'failed'),
            'amount'    => (int) ($data['amount'] ?? 0),
            'currency'  => (string) ($data['currency'] ?? ''),
            'reference' => (string) ($data['reference'] ?? $reference),
            'raw'       => is_array($data) ? $data : [],
        ];
    }

    protected function client()
    {
        if (empty($this->secretKey)) {
            throw new PaymentException('Paystack secret key is not configured.');
        }

        return Http::withToken($this->secretKey)
            ->baseUrl($this->baseUrl)
            ->acceptJson()
            ->timeout(20);
    }
}
