<?php

namespace App\Storefront\Services;

use App\Models\Storefront\StorefrontOrder;
use App\Models\Storefront\StorefrontOrderPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Records payment transactions against orders and reflects verified payments
 * onto the order's payment_status / status (via OrderService). Posts nothing to
 * finance — Phase 4 subscribes to the resulting order state.
 */
class PaymentService
{
    public function __construct(protected OrderService $orders)
    {
    }

    /**
     * Record a freshly-initialised Paystack transaction (status=initiated).
     */
    public function recordPaystackInitiated(
        StorefrontOrder $order,
        string $reference,
        string $amount,
    ): StorefrontOrderPayment {
        return StorefrontOrderPayment::create([
            'organization_id'    => $order->organization_id,
            'order_id'           => $order->id,
            'provider'           => StorefrontOrderPayment::PROVIDER_PAYSTACK,
            'reference'          => $reference,
            'provider_reference' => $reference,
            'amount'             => $amount,
            'currency_code'      => $order->currency_code,
            'status'             => StorefrontOrderPayment::STATUS_INITIATED,
        ]);
    }

    /**
     * Mark a Paystack payment verified and advance the order to
     * payment_confirmed. Idempotent: a re-delivered verification is a no-op.
     *
     * @param  array<string,mixed>  $rawResponse
     */
    public function markPaystackVerified(
        StorefrontOrderPayment $payment,
        array $rawResponse,
        ?User $actor = null,
    ): StorefrontOrderPayment {
        if ($payment->status === StorefrontOrderPayment::STATUS_VERIFIED) {
            return $payment; // already verified — idempotent.
        }

        return DB::transaction(function () use ($payment, $rawResponse, $actor) {
            $payment->forceFill([
                'status'       => StorefrontOrderPayment::STATUS_VERIFIED,
                'raw_response' => $rawResponse,
                'verified_at'  => now(),
            ])->save();

            $order = $payment->order;
            if ($order !== null) {
                $this->orders->markPaymentConfirmed($order, $actor);
            }

            return $payment->refresh();
        });
    }

    public function markPaystackFailed(StorefrontOrderPayment $payment, array $rawResponse): StorefrontOrderPayment
    {
        $payment->forceFill([
            'status'       => StorefrontOrderPayment::STATUS_FAILED,
            'raw_response' => $rawResponse,
        ])->save();

        return $payment->refresh();
    }

    /**
     * Record COD cash collected (used at fulfilment, Phase 5). Sets the order
     * paid. Kept here so the payment ledger is the single home for collections.
     */
    public function recordCodCollected(StorefrontOrder $order, string $amount, User $actor): StorefrontOrderPayment
    {
        return DB::transaction(function () use ($order, $amount, $actor) {
            $payment = StorefrontOrderPayment::create([
                'organization_id'    => $order->organization_id,
                'order_id'           => $order->id,
                'provider'           => StorefrontOrderPayment::PROVIDER_COD,
                'reference'          => 'COD-' . $order->reference,
                'provider_reference' => 'COD-' . $order->reference,
                'amount'             => $amount,
                'currency_code'      => $order->currency_code,
                'status'             => StorefrontOrderPayment::STATUS_VERIFIED,
                'verified_at'        => now(),
                'created_by_user_id' => $actor->id,
            ]);

            $order->forceFill(['payment_status' => StorefrontOrder::PAY_PAID])->save();
            $this->orders->recordEvent($order, 'payment', null, null, "COD payment collected: {$amount}", $actor->id);

            return $payment;
        });
    }
}
