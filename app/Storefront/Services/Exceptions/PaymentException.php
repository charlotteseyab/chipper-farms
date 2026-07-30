<?php

namespace App\Storefront\Services\Exceptions;

use RuntimeException;

/**
 * Thrown for payment/gateway failures (verification mismatch, gateway error).
 *
 * CUSTOMER-SAFE MESSAGES (CF-P1-020)
 * ----------------------------------
 * HttpPaystackClient used to build these with the raw gateway response body:
 *
 *     throw new PaymentException('Paystack verify failed: ' . $response->body());
 *
 * and PaystackWebhookController::callback() flashed getMessage() straight into
 * the customer's session. So Paystack's raw JSON — internal error codes,
 * transaction metadata, whatever the provider chose to return — was rendered in
 * the browser of whoever happened to fail a payment.
 *
 * The message on this exception is therefore always safe to display. Diagnostic
 * detail lives in $context, is written to the log and never to a response, and
 * every occurrence carries a correlation id so a support conversation ("I got
 * reference CF-PAY-8f3a2b1c") can be joined to the log entry without the
 * customer ever seeing provider internals.
 *
 * The constructor keeps a plain single-string signature working, so existing
 * `new PaymentException('...')` call sites remain valid — they simply get a
 * correlation id for free.
 */
class PaymentException extends RuntimeException
{
    /**
     * Short opaque id shared between the customer-facing message and the log
     * record. Not derived from payment data — purely a join key.
     */
    protected string $correlationId;

    /** @var array<string,mixed> Diagnostic detail. NEVER rendered to a response. */
    protected array $context;

    /**
     * @param  array<string,mixed>  $context
     */
    public function __construct(
        string $customerSafeMessage,
        array $context = [],
        ?string $correlationId = null,
        ?\Throwable $previous = null,
    ) {
        $this->correlationId = $correlationId ?? self::newCorrelationId();
        $this->context       = $context;

        parent::__construct($customerSafeMessage, 0, $previous);
    }

    public static function newCorrelationId(): string
    {
        return 'CF-PAY-' . bin2hex(random_bytes(4));
    }

    public function correlationId(): string
    {
        return $this->correlationId;
    }

    /** @return array<string,mixed> */
    public function context(): array
    {
        return $this->context;
    }

    /**
     * The message to show a customer, with the correlation id appended so they
     * can quote it to support.
     */
    public function customerMessage(): string
    {
        return $this->getMessage() . ' (reference: ' . $this->correlationId . ')';
    }
}
