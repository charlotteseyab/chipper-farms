<?php

namespace App\Observers;

use App\Models\Storefront\StorefrontOrder;
use App\Models\Storefront\StorefrontOrderEvent;
use App\Services\Email\EmailTemplateService;
use App\Storefront\Support\StorefrontSettings;
use Illuminate\Support\Facades\DB;

class StorefrontOrderEventObserver
{
    public function created(StorefrontOrderEvent $event): void
    {
        $templateKey = $this->templateFor($event->to_status);
        if ($templateKey === null) {
            return;
        }

        $orderId = (int) $event->order_id;

        DB::afterCommit(function () use ($orderId, $templateKey): void {
            $order = StorefrontOrder::with(['storefrontBuyer', 'customer', 'counterparty'])->find($orderId);
            if (! $order) {
                return;
            }

            $recipient = $order->storefrontBuyer?->email
                ?? $order->customer?->email
                ?? $order->guest_email
                ?? $order->counterparty?->email;

            app(EmailTemplateService::class)->send(
                (int) $order->organization_id,
                $templateKey,
                $recipient,
                $this->variables($order),
            );

            $buyer = $order->storefrontBuyer ?? $order->customer;
            if ($buyer) {
                $title = match ($templateKey) {
                    'storefront.order_placed' => 'Order received',
                    'storefront.payment_confirmed' => 'Payment confirmed',
                    'storefront.order_confirmed' => 'Order confirmed',
                    'storefront.ready_for_pickup' => 'Ready for pickup',
                    'storefront.out_for_delivery' => 'Out for delivery',
                    'storefront.order_completed' => 'Order completed',
                    'storefront.order_cancelled' => 'Order cancelled',
                    'storefront.payment_failed' => 'Payment unsuccessful',
                    default => 'Order update',
                };
                $link = $order->storefront_buyer_id
                    ? route('storefront.buyer.orders.show', $order)
                    : route('storefront.account.orders.show', $order);

                app(\App\Services\Notifications\InAppNotificationService::class)->notifyBuyer(
                    $buyer,
                    $templateKey,
                    $title,
                    "{$title} for {$order->reference}.",
                    $link,
                    in_array($templateKey, ['storefront.order_cancelled', 'storefront.payment_failed'], true) ? 'warning' : 'info',
                    ['order_id' => $order->id, 'order_reference' => $order->reference],
                );
            }
        });
    }

    protected function templateFor(?string $status): ?string
    {
        return match ($status) {
            StorefrontOrder::STATUS_PENDING_PAYMENT,
            StorefrontOrder::STATUS_PENDING_CONFIRMATION => 'storefront.order_placed',
            StorefrontOrder::STATUS_PAYMENT_CONFIRMED => 'storefront.payment_confirmed',
            StorefrontOrder::STATUS_CONFIRMED => 'storefront.order_confirmed',
            StorefrontOrder::STATUS_READY_FOR_PICKUP => 'storefront.ready_for_pickup',
            StorefrontOrder::STATUS_OUT_FOR_DELIVERY => 'storefront.out_for_delivery',
            StorefrontOrder::STATUS_COMPLETED => 'storefront.order_completed',
            StorefrontOrder::STATUS_CANCELLED => 'storefront.order_cancelled',
            StorefrontOrder::STATUS_FAILED => 'storefront.payment_failed',
            default => null,
        };
    }

    protected function variables(StorefrontOrder $order): array
    {
        $guestUrl = $order->guest_claim_token
            ? route('storefront.order.track', ['reference' => $order->reference, 'token' => $order->guest_claim_token])
            : null;

        return [
            'customer_name' => $order->storefrontBuyer?->name ?? $order->customer?->name ?? $order->guest_name ?? $order->ship_to_name ?? 'Customer',
            'order_reference' => $order->reference,
            'order_total' => $order->currency_code.' '.number_format((float) $order->total, 2),
            'payment_status' => str_replace('_', ' ', $order->payment_status),
            'order_status' => str_replace('_', ' ', $order->status),
            'fulfilment_method' => str_replace('_', ' ', $order->fulfilment_method),
            'store_name' => StorefrontSettings::storeName($order->organization_id),
            'support_email' => StorefrontSettings::supportEmail($order->organization_id),
            'support_phone' => StorefrontSettings::supportPhone($order->organization_id),
            'action_url' => $guestUrl ?: route('storefront.buyer.login'),
            'create_account_url' => $order->guest_claim_token
                ? route('storefront.buyer.register.from-order', ['reference' => $order->reference, 'token' => $order->guest_claim_token])
                : '',
        ];
    }
}
