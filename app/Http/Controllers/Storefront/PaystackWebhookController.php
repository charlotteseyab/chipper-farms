<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Storefront\StorefrontOrder;
use App\Models\Storefront\StorefrontOrderEvent;
use App\Models\Storefront\StorefrontOrderPayment;
use App\Storefront\Payments\PaystackSignature;
use App\Storefront\Services\Exceptions\PaymentException;
use App\Storefront\Services\PaystackService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Paystack return-URL callback (browser) and server-to-server webhook.
 *
 * Both paths VERIFY SERVER-SIDE — the browser redirect is never trusted on its
 * own, and the webhook body is authenticated by HMAC signature before any
 * lookup. A verified payment accepts the order (see
 * OrderService::acceptPaidOrder()).
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
            return redirect()->route('storefront.home')
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
            $target = redirect()->to($this->orderDestination($request, $order));

            // CF-P1-020: this used to concatenate $e->getMessage(), which
            // HttpPaystackClient built from the raw gateway response body — so
            // Paystack's JSON was flashed into the customer's browser.
            // customerMessage() is safe by construction and carries only a
            // correlation id, which support can use to find the logged detail.
            return $target->with('error', $e->customerMessage());
        }

        $order?->refresh();

        $message = $order?->status === StorefrontOrder::STATUS_CONFIRMED
            ? "Payment received. Order {$order->reference} is confirmed."
            : 'Payment received. Your order is reserved for you and we\'re getting it ready.';

        return redirect()->to($this->orderDestination($request, $order))
            ->with('success', $message);
    }

    protected function orderDestination(Request $request, ?StorefrontOrder $order): string
    {
        if (! $order) {
            return route('storefront.home');
        }

        if ($order->guest_claim_token) {
            return route('storefront.order.track', [
                'reference' => $order->reference,
                'token' => $order->guest_claim_token,
            ]);
        }

        $buyerId = $request->session()->get('storefront_buyer_id');
        if ($buyerId && (int) $order->storefront_buyer_id === (int) $buyerId) {
            return route('storefront.buyer.orders.show', $order);
        }

        if ($request->user() && (int) $order->customer_user_id === (int) $request->user()->id) {
            return route('storefront.account.orders.show', $order);
        }

        return route('storefront.buyer.login', [
            'email' => $order->guest_email,
        ]);
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

        if (is_string($event) && str_starts_with($event, 'refund.')) {
            $this->handleRefundEvent($event, is_array($payload['data'] ?? null) ? $payload['data'] : []);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Paystack refund settlement is asynchronous. The submission endpoint only
     * says "accepted"; this authenticated webhook is what lets the storefront
     * finally claim that money has actually returned to the customer.
     *
     * @param  array<string,mixed>  $data
     */
    protected function handleRefundEvent(string $event, array $data): void
    {
        $refundReference = $this->stringFrom($data, ['reference', 'refund_reference', 'id']);
        $transactionReference = $this->stringFrom($data, ['transaction.reference', 'transaction_reference']);
        $providerStatus = $this->statusForRefundEvent($event, (string) ($data['status'] ?? ''));

        if ($refundReference === null && $transactionReference === null) {
            Log::warning('Paystack refund webhook ignored: missing refund and transaction references.', [
                'event' => $event,
            ]);

            return;
        }

        $order = StorefrontOrder::query()
            ->where(function (Builder $query) use ($refundReference, $transactionReference): void {
                if ($refundReference !== null) {
                    $query->where('refund_provider_reference', $refundReference);
                }

                if ($transactionReference !== null) {
                    $query->orWhereHas('payments', function (Builder $payments) use ($transactionReference): void {
                        $payments->where('provider', StorefrontOrderPayment::PROVIDER_PAYSTACK)
                            ->where('provider_reference', $transactionReference);
                    });
                }
            })
            ->latest('id')
            ->first();

        if ($order === null) {
            Log::warning('Paystack refund webhook ignored: no matching order.', [
                'event' => $event,
                'refund_reference' => $refundReference,
                'transaction_reference' => $transactionReference,
            ]);

            return;
        }

        DB::transaction(function () use ($order, $event, $data, $refundReference, $providerStatus): void {
            $locked = StorefrontOrder::lockForUpdate()->findOrFail($order->id);
            $fromStatus = $locked->status;
            $alreadyFinal = $locked->refunded_at !== null
                && $locked->status === StorefrontOrder::STATUS_REFUNDED
                && $locked->payment_status === StorefrontOrder::PAY_REFUNDED;

            $attributes = [
                'refund_provider_status' => $providerStatus,
                'refund_failure_reason' => null,
            ];

            if ($refundReference !== null && empty($locked->refund_provider_reference)) {
                $attributes['refund_provider_reference'] = $refundReference;
            }

            if ($this->refundEventIsSettled($event, $providerStatus)) {
                if ($alreadyFinal) {
                    $locked->forceFill($attributes)->save();
                    return;
                }

                $attributes['status'] = StorefrontOrder::STATUS_REFUNDED;
                $attributes['payment_status'] = StorefrontOrder::PAY_REFUNDED;
                $attributes['refunded_at'] = now();

                $locked->forceFill($attributes)->save();
                $this->markPaymentRefunded($locked, $data);
                $this->recordWebhookEvent(
                    $locked,
                    $fromStatus,
                    StorefrontOrder::STATUS_REFUNDED,
                    "Paystack refund settled via {$event}.",
                );

                return;
            }

            if ($this->refundEventIsFailed($event, $providerStatus)) {
                $attributes['status'] = StorefrontOrder::STATUS_REFUND_APPROVED;
                $attributes['payment_status'] = StorefrontOrder::PAY_REFUND_PENDING;
                $attributes['refund_failure_reason'] = 'Paystack reported refund failure.';

                $locked->forceFill($attributes)->save();
                $this->recordWebhookEvent(
                    $locked,
                    $fromStatus,
                    StorefrontOrder::STATUS_REFUND_APPROVED,
                    "Paystack refund failed via {$event}.",
                );

                return;
            }

            $locked->forceFill($attributes)->save();
        });
    }

    /**
     * @param  array<string,mixed>  $data
     */
    protected function markPaymentRefunded(StorefrontOrder $order, array $data): void
    {
        $transactionReference = $this->stringFrom($data, ['transaction.reference', 'transaction_reference']);

        $query = StorefrontOrderPayment::query()
            ->where('order_id', $order->id)
            ->where('provider', StorefrontOrderPayment::PROVIDER_PAYSTACK);

        if ($transactionReference !== null) {
            $query->where('provider_reference', $transactionReference);
        } else {
            $query->where('status', StorefrontOrderPayment::STATUS_VERIFIED);
        }

        $payment = $query->latest('id')->first();

        if ($payment !== null) {
            $payment->forceFill([
                'status' => StorefrontOrderPayment::STATUS_REFUNDED,
                'raw_response' => $data,
            ])->save();
        }
    }

    protected function recordWebhookEvent(
        StorefrontOrder $order,
        ?string $fromStatus,
        string $toStatus,
        string $note,
    ): void {
        StorefrontOrderEvent::create([
            'organization_id' => $order->organization_id,
            'order_id' => $order->id,
            'type' => StorefrontOrderEvent::TYPE_PAYMENT,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'note' => $note,
        ]);
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  list<string>  $paths
     */
    protected function stringFrom(array $payload, array $paths): ?string
    {
        foreach ($paths as $path) {
            $value = $payload;

            foreach (explode('.', $path) as $segment) {
                if (! is_array($value) || ! array_key_exists($segment, $value)) {
                    $value = null;
                    break;
                }

                $value = $value[$segment];
            }

            if (is_string($value) && $value !== '') {
                return $value;
            }

            if (is_int($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    protected function statusForRefundEvent(string $event, string $status): string
    {
        if ($status !== '') {
            return strtolower($status);
        }

        return match ($event) {
            'refund.processed' => 'processed',
            'refund.failed' => 'failed',
            default => 'pending',
        };
    }

    protected function refundEventIsSettled(string $event, string $status): bool
    {
        return $event === 'refund.processed'
            || in_array($status, ['processed', 'success', 'succeeded', 'reversed'], true);
    }

    protected function refundEventIsFailed(string $event, string $status): bool
    {
        return $event === 'refund.failed'
            || in_array($status, ['failed', 'cancelled', 'canceled'], true);
    }
}
