<?php

namespace App\Storefront\Payments;

use App\Storefront\Services\Exceptions\PaymentException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Production Paystack client. All calls are authenticated with the secret key
 * and time-bounded. Any non-success gateway response is surfaced as a
 * PaymentException so callers fail closed.
 *
 * ERROR HANDLING (CF-P1-020)
 * --------------------------
 * Failures used to be raised as
 *
 *     throw new PaymentException('Paystack verify failed: ' . $response->body());
 *
 * and PaystackWebhookController flashed that message into the customer's
 * session, so raw gateway JSON was rendered in the browser. Every throw site now
 * raises a customer-safe message and puts the provider detail in the exception
 * context, which is logged and never rendered. A correlation id ties the two
 * together.
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
            $this->fail(
                'We could not start your payment. Please try again.',
                'initialize',
                ['reference' => $reference, 'currency' => $currency],
                $response,
            );
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
            $this->fail(
                'We could not verify your payment. If you were charged, contact support and quote the reference below.',
                'verify',
                ['reference' => $reference],
                $response,
            );
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

    /**
     * Submit a refund (CF-P0-007).
     *
     * Paystack's /refund endpoint accepts the original transaction reference.
     * Omitting `amount` refunds the full transaction, which is why $amountMinor
     * is only sent when explicitly supplied — sending a computed "full" amount
     * risks disagreeing with Paystack's own view of the settled total (fees,
     * partial captures) and over- or under-refunding.
     *
     * The response status is NOT a completion signal. Paystack processes refunds
     * asynchronously and typically returns 'pending'; settlement arrives later
     * via a refund.processed webhook. The return value here means "accepted".
     */
    public function refundTransaction(
        string $transactionReference,
        ?int $amountMinor = null,
        ?string $reason = null,
        ?string $idempotencyKey = null,
    ): array {
        $payload = array_filter([
            'transaction'   => $transactionReference,
            'amount'        => $amountMinor,
            'merchant_note' => $reason,
        ], fn ($v) => $v !== null);

        $request = $this->client();

        if ($idempotencyKey !== null) {
            // Guards against a retried submission being processed as a second
            // refund. A duplicated refund is real money leaving the business
            // twice, so this is not merely tidiness.
            $request = $request->withHeaders(['Idempotency-Key' => $idempotencyKey]);
        }

        $response = $request->post('/refund', $payload);

        if (! $response->successful() || ! ($response->json('status') === true)) {
            $this->fail(
                'The refund could not be submitted to our payment provider. No money has moved; support can retry it.',
                'refund',
                [
                    'transaction_reference' => $transactionReference,
                    'amount_minor'          => $amountMinor,
                    'idempotency_key'       => $idempotencyKey,
                ],
                $response,
            );
        }

        $data = $response->json('data') ?? [];

        return [
            'status'             => (string) ($data['status'] ?? 'pending'),
            'provider_reference' => isset($data['id']) ? (string) $data['id'] : null,
            'amount'             => isset($data['amount']) ? (int) $data['amount'] : null,
            'currency'           => isset($data['currency']) ? (string) $data['currency'] : null,
            'raw'                => is_array($data) ? $data : [],
        ];
    }

    /**
     * Log the provider detail and throw a message that is safe to show a user.
     *
     * The response body is deliberately confined to the log. It is the thing
     * CF-P1-020 was about.
     *
     * @param  array<string,mixed>  $context
     *
     * @throws PaymentException
     */
    protected function fail(
        string $customerSafeMessage,
        string $operation,
        array $context,
        \Illuminate\Http\Client\Response $response,
    ): never {
        $correlationId = PaymentException::newCorrelationId();

        Log::error('paystack request failed', array_merge($context, [
            'correlation_id' => $correlationId,
            'operation'      => $operation,
            'http_status'    => $response->status(),
            // Truncated: a provider can return an arbitrarily large body and
            // this lands in the application log.
            'body'           => mb_substr($response->body(), 0, 2000),
        ]));

        throw new PaymentException($customerSafeMessage, $context, $correlationId);
    }

    protected function client()
    {
        if (empty($this->secretKey)) {
            // Configuration fault rather than a gateway fault. Still customer-safe:
            // it names no key and no endpoint.
            throw new PaymentException(
                'Online payments are not currently available. Please contact support.',
                ['reason' => 'paystack_secret_key_missing'],
            );
        }

        return Http::withToken($this->secretKey)
            ->baseUrl($this->baseUrl)
            ->acceptJson()
            ->timeout(20);
    }
}
