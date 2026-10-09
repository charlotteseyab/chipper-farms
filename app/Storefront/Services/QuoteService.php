<?php

namespace App\Storefront\Services;

use App\Models\Organization;
use App\Models\Storefront\StorefrontQuote;
use App\Models\Storefront\StorefrontQuoteItem;
use App\Models\Storefront\StorefrontBuyer;
use App\Models\User;
use App\Notifications\StorefrontQuoteRequestedNotification;
use App\Storefront\Services\Exceptions\InvalidQuoteTransitionException;
use App\Storefront\Support\QuoteRequestData;
use App\Storefront\Support\StorefrontCatalog;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Owns the quote-request lifecycle. A quote NEVER posts to finance; it is a
 * pre-sales artifact. Conversion to an order (which does feed finance) is
 * Phase 5.
 *
 * Status machine:
 *   requested → reviewing → quoted → accepted → converted
 *               (any open) → declined | expired
 */
class QuoteService
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        StorefrontQuote::STATUS_REQUESTED => [
            StorefrontQuote::STATUS_REVIEWING,
            StorefrontQuote::STATUS_QUOTED,
            StorefrontQuote::STATUS_DECLINED,
            StorefrontQuote::STATUS_EXPIRED,
        ],
        StorefrontQuote::STATUS_REVIEWING => [
            StorefrontQuote::STATUS_QUOTED,
            StorefrontQuote::STATUS_DECLINED,
            StorefrontQuote::STATUS_EXPIRED,
        ],
        StorefrontQuote::STATUS_QUOTED => [
            StorefrontQuote::STATUS_ACCEPTED,
            StorefrontQuote::STATUS_DECLINED,
            StorefrontQuote::STATUS_EXPIRED,
        ],
        StorefrontQuote::STATUS_ACCEPTED => [
            StorefrontQuote::STATUS_CONVERTED,
            StorefrontQuote::STATUS_EXPIRED,
        ],
        StorefrontQuote::STATUS_DECLINED  => [],
        StorefrontQuote::STATUS_EXPIRED   => [],
        StorefrontQuote::STATUS_CONVERTED => [],
    ];

    public function __construct(
        protected CurrentOrganization $currentOrganization,
        protected CustomerAccountService $accounts,
    ) {
    }

    /**
     * Create a quote request. Works for guests (contact details only) and
     * authenticated customers (links their counterparty for clean finance
     * integration later). Notifies sales staff.
     */
    public function request(QuoteRequestData $data, ?User $customer = null): StorefrontQuote
    {
        $organizationId = $this->resolveOrganizationId();

        return DB::transaction(function () use ($data, $customer, $organizationId) {
            $counterpartyId = null;
            if ($customer !== null) {
                $profile = $this->accounts->ensureProfile($customer);
                $counterpartyId = $profile->counterparty_id;
            }

            $quote = StorefrontQuote::create([
                'organization_id'       => $organizationId,
                'reference'             => $this->nextReference($organizationId),
                'counterparty_id'       => $counterpartyId,
                'customer_user_id'      => $customer?->id,
                'contact_name'          => $data->contactName,
                'contact_email'         => $data->contactEmail,
                'contact_phone'         => $data->contactPhone,
                'source'                => $data->source,
                'fulfilment_preference' => $data->fulfilmentPreference,
                'delivery_city'         => $data->deliveryCity,
                'delivery_region'       => $data->deliveryRegion,
                'message'               => $data->message,
                'status'                => StorefrontQuote::STATUS_REQUESTED,
                'currency_code'         => $data->currencyCode,
                'created_by_user_id'    => $customer?->id,
            ]);

            foreach ($data->items as $item) {
                StorefrontQuoteItem::create([
                    'organization_id'   => $organizationId,
                    'quote_id'          => $quote->id,
                    'listing_id'        => $item['listing_id'] ?? null,
                    'kind'              => $item['kind'] ?? null,
                    'description'       => $item['description'],
                    'quantity'          => $item['quantity'] ?? '1',
                    'quantity_unit'     => $item['quantity_unit'] ?? 'unit',
                    'line_note'         => $item['line_note'] ?? null,
                ]);
            }

            $this->notifyStaff($quote, $organizationId);

            $quoteId = (int) $quote->id;
            DB::afterCommit(function () use ($quoteId): void {
                $savedQuote = StorefrontQuote::find($quoteId);
                if (! $savedQuote) {
                    return;
                }

                app(\App\Services\Email\EmailTemplateService::class)->send(
                    (int) $savedQuote->organization_id,
                    'storefront.quote_received',
                    $savedQuote->contact_email,
                    [
                        'customer_name' => $savedQuote->contact_name,
                        'quote_reference' => $savedQuote->reference,
                        'store_name' => \App\Storefront\Support\StorefrontSettings::storeName($savedQuote->organization_id),
                        'support_email' => \App\Storefront\Support\StorefrontSettings::supportEmail($savedQuote->organization_id),
                        'support_phone' => \App\Storefront\Support\StorefrontSettings::supportPhone($savedQuote->organization_id),
                    ],
                );
            });

            return $quote->load('items');
        });
    }

    public function markReviewing(StorefrontQuote $quote, User $actor): StorefrontQuote
    {
        return $this->transition($quote, StorefrontQuote::STATUS_REVIEWING, $actor);
    }

    /**
     * Provide pricing and move to "quoted".
     */
    public function provideQuote(
        StorefrontQuote $quote,
        User $actor,
        string $quotedTotal,
        ?string $validUntil = null,
        ?string $staffNotes = null,
    ): StorefrontQuote {
        return DB::transaction(function () use ($quote, $actor, $quotedTotal, $validUntil, $staffNotes) {
            $this->assertTransitionAllowed($quote, StorefrontQuote::STATUS_QUOTED);

            $quote->forceFill([
                'status'              => StorefrontQuote::STATUS_QUOTED,
                'quoted_total'        => $quotedTotal,
                'valid_until'         => $validUntil,
                'staff_notes'         => $staffNotes ?? $quote->staff_notes,
                'assigned_to_user_id' => $quote->assigned_to_user_id ?? $actor->id,
                'responded_at'        => now(),
            ])->save();

            $quoteId = (int) $quote->id;
            DB::afterCommit(function () use ($quoteId): void {
                $savedQuote = StorefrontQuote::find($quoteId);
                if (! $savedQuote) {
                    return;
                }

                $amount = ($savedQuote->currency_code ?: 'GHS').' '.number_format((float) $savedQuote->quoted_total, 2);
                $validUntil = $savedQuote->valid_until?->format('d M Y') ?? 'further notice';
                $actionUrl = route('storefront.buyer.quotes.show', $savedQuote);

                app(\App\Services\Email\EmailTemplateService::class)->send(
                    (int) $savedQuote->organization_id,
                    'storefront.quote_priced',
                    $savedQuote->contact_email,
                    [
                        'customer_name' => $savedQuote->contact_name,
                        'quote_reference' => $savedQuote->reference,
                        'quoted_total' => $amount,
                        'valid_until' => $validUntil,
                        'action_url' => $actionUrl,
                        'store_name' => \App\Storefront\Support\StorefrontSettings::storeName($savedQuote->organization_id),
                        'support_email' => \App\Storefront\Support\StorefrontSettings::supportEmail($savedQuote->organization_id),
                        'support_phone' => \App\Storefront\Support\StorefrontSettings::supportPhone($savedQuote->organization_id),
                    ],
                );

                $buyer = StorefrontBuyer::query()
                    ->where('organization_id', $savedQuote->organization_id)
                    ->whereRaw('LOWER(email) = ?', [strtolower(trim((string) $savedQuote->contact_email))])
                    ->first();

                app(\App\Services\Notifications\InAppNotificationService::class)->notifyBuyer(
                    $buyer,
                    'storefront.quote_priced',
                    'Your quote is ready',
                    "Quote {$savedQuote->reference} has been priced at {$amount}.",
                    $actionUrl,
                    context: ['quote_id' => $savedQuote->id],
                );
            });

            return $quote->refresh();
        });
    }

    public function accept(StorefrontQuote $quote, User $actor): StorefrontQuote
    {
        return $this->transition($quote, StorefrontQuote::STATUS_ACCEPTED, $actor);
    }

    public function acceptAndConvertByBuyer(StorefrontQuote $quote, StorefrontBuyer $buyer, string $paymentMethod): \App\Models\Storefront\StorefrontOrder
    {
        return DB::transaction(function () use ($quote, $buyer, $paymentMethod) {
            $locked = StorefrontQuote::query()->lockForUpdate()->findOrFail($quote->id);
            $this->assertBuyerOwnsQuote($locked, $buyer);

            if (! StorefrontCatalog::isPaymentMethodEnabled($paymentMethod, (int) $locked->organization_id)) {
                throw new InvalidQuoteTransitionException('That payment method is not currently available.');
            }

            if ($locked->status === StorefrontQuote::STATUS_CONVERTED && $locked->converted_order_id) {
                return \App\Models\Storefront\StorefrontOrder::findOrFail($locked->converted_order_id);
            }

            if ($locked->status !== StorefrontQuote::STATUS_QUOTED) {
                throw new InvalidQuoteTransitionException('Only a priced quote can be accepted.');
            }

            if ($locked->valid_until && $locked->valid_until->isBefore(today())) {
                $locked->forceFill(['status' => StorefrontQuote::STATUS_EXPIRED])->save();
                throw new InvalidQuoteTransitionException('This quote has expired. Please request a new price.');
            }

            $locked->forceFill([
                'status' => StorefrontQuote::STATUS_ACCEPTED,
                'responded_at' => now(),
            ])->save();

            $order = $this->convertToOrder($locked, null, $buyer, $paymentMethod);

            DB::afterCommit(function () use ($locked, $order): void {
                app(\App\Services\Notifications\InAppNotificationService::class)->notifyRelevantStaff(
                    (int) $locked->organization_id,
                    'storefront.quote_staff_alert',
                    'Quote accepted by buyer',
                    "Quote {$locked->reference} was accepted and order {$order->reference} was created.",
                    route('storefront.admin.orders.show', $order),
                    context: ['quote_id' => $locked->id, 'order_id' => $order->id],
                );
            });

            return $order;
        });
    }

    public function declineByBuyer(StorefrontQuote $quote, StorefrontBuyer $buyer): StorefrontQuote
    {
        return DB::transaction(function () use ($quote, $buyer) {
            $locked = StorefrontQuote::query()->lockForUpdate()->findOrFail($quote->id);
            $this->assertBuyerOwnsQuote($locked, $buyer);

            if ($locked->status !== StorefrontQuote::STATUS_QUOTED) {
                throw new InvalidQuoteTransitionException('Only a priced quote can be declined.');
            }

            $locked->forceFill([
                'status' => StorefrontQuote::STATUS_DECLINED,
                'responded_at' => now(),
            ])->save();

            return $locked->refresh();
        });
    }

    public function decline(StorefrontQuote $quote, User $actor, ?string $reason = null): StorefrontQuote
    {
        return DB::transaction(function () use ($quote, $actor, $reason) {
            $this->assertTransitionAllowed($quote, StorefrontQuote::STATUS_DECLINED);

            $quote->forceFill([
                'status'              => StorefrontQuote::STATUS_DECLINED,
                'staff_notes'         => $reason ? trim(($quote->staff_notes ? $quote->staff_notes . "\n" : '') . $reason) : $quote->staff_notes,
                'assigned_to_user_id' => $quote->assigned_to_user_id ?? $actor->id,
                'responded_at'        => now(),
            ])->save();

            return $quote->refresh();
        });
    }

    public function expire(StorefrontQuote $quote, User $actor): StorefrontQuote
    {
        return $this->transition($quote, StorefrontQuote::STATUS_EXPIRED, $actor);
    }

    /**
     * Convert an ACCEPTED quote into an order (Phase 5). Idempotent: returns
     * the existing order if already converted. The order is created at
     * pending_confirmation with COD by default; staff adjust payment/fulfilment
     * before confirming. Lines carry the quote's free-text items, so finance
     * treats them as manually-invoiced (deferred) unless later linked to a
     * listing — safe by design.
     */
    public function convertToOrder(StorefrontQuote $quote, ?User $actor, ?StorefrontBuyer $buyer = null, string $paymentMethod = \App\Models\Storefront\StorefrontOrder::METHOD_COD): \App\Models\Storefront\StorefrontOrder
    {
        if ($quote->status === StorefrontQuote::STATUS_CONVERTED && $quote->converted_order_id) {
            $existing = \App\Models\Storefront\StorefrontOrder::find($quote->converted_order_id);
            if ($existing) {
                return $existing;
            }
        }

        if ($quote->status !== StorefrontQuote::STATUS_ACCEPTED) {
            throw new InvalidQuoteTransitionException(
                "Only an accepted quote can be converted to an order (quote #{$quote->id} is {$quote->status})."
            );
        }

        return DB::transaction(function () use ($quote, $actor, $buyer, $paymentMethod) {
            $quote->loadMissing('items');
            $orgId = $quote->organization_id;
            $isPaystack = $paymentMethod === \App\Models\Storefront\StorefrontOrder::METHOD_PAYSTACK;

            $total = $quote->quoted_total !== null ? (string) $quote->quoted_total : '0.00';

            $order = \App\Models\Storefront\StorefrontOrder::create([
                'organization_id'   => $orgId,
                'reference'         => $this->nextOrderReference($orgId),
                'counterparty_id'   => $quote->counterparty_id,
                'customer_user_id'  => $quote->customer_user_id,
                'storefront_buyer_id' => $buyer?->id,
                'quote_id'          => $quote->id,
                'guest_name'        => $quote->contact_name,
                'guest_email'       => $quote->contact_email,
                'guest_phone'       => $quote->contact_phone,
                'status'            => $isPaystack
                    ? \App\Models\Storefront\StorefrontOrder::STATUS_PENDING_PAYMENT
                    : \App\Models\Storefront\StorefrontOrder::STATUS_PENDING_CONFIRMATION,
                'payment_status'    => $isPaystack
                    ? \App\Models\Storefront\StorefrontOrder::PAY_PAYSTACK_PEND
                    : \App\Models\Storefront\StorefrontOrder::PAY_COD,
                'payment_method'    => $paymentMethod,
                'fulfilment_method' => $quote->fulfilment_preference === 'delivery'
                    ? \App\Models\Storefront\StorefrontOrder::FULFIL_DELIVERY
                    : \App\Models\Storefront\StorefrontOrder::FULFIL_PICKUP,
                'subtotal'          => $total,
                'delivery_fee'      => '0.00',
                'total'             => $total,
                'currency_code'     => $quote->currency_code ?: 'GHS',
                'customer_note'     => $quote->message,
                'ship_to_name'      => $quote->contact_name,
                'ship_to_phone'     => $quote->contact_phone,
                'ship_city'         => $quote->delivery_city,
                'ship_region'       => $quote->delivery_region,
                'placed_at'         => now(),
            ]);

            foreach ($quote->items as $qItem) {
                $unitPrice = $qItem->target_unit_price !== null ? (string) $qItem->target_unit_price : '0';
                $lineTotal = bcmul($unitPrice, (string) $qItem->quantity, 2);

                \App\Models\Storefront\StorefrontOrderItem::create([
                    'organization_id' => $orgId,
                    'order_id'        => $order->id,
                    'listing_id'      => $qItem->listing_id,
                    'kind'            => $qItem->kind,
                    'description'     => $qItem->description,
                    'quantity'        => $qItem->quantity,
                    'quantity_unit'   => $qItem->quantity_unit,
                    'price_basis'     => 'fixed',
                    'unit_price'      => $unitPrice,
                    'line_total'      => $lineTotal,
                ]);
            }

            $quote->forceFill([
                'status'             => StorefrontQuote::STATUS_CONVERTED,
                'converted_order_id' => $order->id,
                'responded_at'       => now(),
            ])->save();

            \App\Models\Storefront\StorefrontOrderEvent::create([
                'organization_id' => $orgId,
                'order_id'        => $order->id,
                'type'            => 'system',
                'to_status'       => $order->status,
                'note'            => "Created from quote {$quote->reference}.",
                'actor_user_id'   => $actor?->id,
            ]);

            return $order->fresh('items');
        });
    }

    /**
     * Allocate an order reference for a quote being converted to an order.
     *
     * CRITICAL (CF-P1-014): this must use the SAME sequence as
     * CheckoutService::nextReference(). Both previously counted
     * storefront_orders independently and formatted 'SO-{count+1}', so a
     * checkout and a quote conversion occurring near each other could mint the
     * same reference against a uniquely-keyed column.
     */
    protected function nextOrderReference(?int $organizationId): string
    {
        return app(StorefrontReferenceAllocator::class)->orderReference($organizationId);
    }

    public function canTransition(StorefrontQuote $quote, string $target): bool
    {
        return in_array($target, self::TRANSITIONS[$quote->status] ?? [], true);
    }

    private function transition(StorefrontQuote $quote, string $target, User $actor): StorefrontQuote
    {
        $this->assertTransitionAllowed($quote, $target);

        $quote->forceFill([
            'status'              => $target,
            'assigned_to_user_id' => $quote->assigned_to_user_id ?? $actor->id,
            'responded_at'        => now(),
        ])->save();

        return $quote->refresh();
    }

    private function assertTransitionAllowed(StorefrontQuote $quote, string $target): void
    {
        if ($quote->status === $target) {
            return;
        }

        if (! $this->canTransition($quote, $target)) {
            throw new InvalidQuoteTransitionException(
                "Illegal quote transition: {$quote->status} -> {$target} (quote #{$quote->id})."
            );
        }
    }

    private function assertBuyerOwnsQuote(StorefrontQuote $quote, StorefrontBuyer $buyer): void
    {
        if ((int) $quote->organization_id !== (int) $buyer->organization_id
            || strtolower(trim((string) $quote->contact_email)) !== strtolower(trim($buyer->email))) {
            throw new InvalidQuoteTransitionException('This quote does not belong to your buyer account.');
        }
    }

    private function notifyStaff(StorefrontQuote $quote, ?int $organizationId): void
    {
        $recipients = User::query()
            ->when($organizationId !== null, fn ($q) => $q->where('organization_id', $organizationId))
            ->where('active_status', true)
            ->with('role.permissions')
            ->get()
            ->filter(fn (User $user) => $user->isSuperuser()
                || $user->isAdmin()
                || $user->isSalesperson()
                || $user->hasPermission('view_storefront_quotes'));

        if ($recipients->isNotEmpty()) {
            // Drop the role.permissions loaded for the filter above. Queued
            // notifications store loaded relations and reload them, and `role`
            // is also a plain column on users, so reloading it fails.
            Notification::send($recipients->each->setRelations([]), new StorefrontQuoteRequestedNotification($quote));
        }
    }

    private function resolveOrganizationId(): ?int
    {
        $org = $this->currentOrganization->get();
        if ($org !== null) {
            return $org->id;
        }

        return Organization::query()->orderBy('id')->value('id');
    }

    /**
     * Allocate a quote reference (CF-P1-014). Was count()+1.
     */
    private function nextReference(?int $organizationId): string
    {
        return app(StorefrontReferenceAllocator::class)->quoteReference($organizationId);
    }
}
