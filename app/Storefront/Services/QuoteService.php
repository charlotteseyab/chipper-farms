<?php

namespace App\Storefront\Services;

use App\Models\Organization;
use App\Models\Storefront\StorefrontQuote;
use App\Models\Storefront\StorefrontQuoteItem;
use App\Models\User;
use App\Notifications\StorefrontQuoteRequestedNotification;
use App\Storefront\Services\Exceptions\InvalidQuoteTransitionException;
use App\Storefront\Support\QuoteRequestData;
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

            return $quote->refresh();
        });
    }

    public function accept(StorefrontQuote $quote, User $actor): StorefrontQuote
    {
        return $this->transition($quote, StorefrontQuote::STATUS_ACCEPTED, $actor);
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
    public function convertToOrder(StorefrontQuote $quote, User $actor): \App\Models\Storefront\StorefrontOrder
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

        return DB::transaction(function () use ($quote, $actor) {
            $quote->loadMissing('items');
            $orgId = $quote->organization_id;

            $total = $quote->quoted_total !== null ? (string) $quote->quoted_total : '0.00';

            $order = \App\Models\Storefront\StorefrontOrder::create([
                'organization_id'   => $orgId,
                'reference'         => $this->nextOrderReference($orgId),
                'counterparty_id'   => $quote->counterparty_id,
                'customer_user_id'  => $quote->customer_user_id,
                'quote_id'          => $quote->id,
                'status'            => \App\Models\Storefront\StorefrontOrder::STATUS_PENDING_CONFIRMATION,
                'payment_status'    => \App\Models\Storefront\StorefrontOrder::PAY_COD,
                'payment_method'    => \App\Models\Storefront\StorefrontOrder::METHOD_COD,
                'fulfilment_method' => $quote->fulfilment_preference === 'delivery'
                    ? \App\Models\Storefront\StorefrontOrder::FULFIL_DELIVERY
                    : \App\Models\Storefront\StorefrontOrder::FULFIL_PICKUP,
                'subtotal'          => $total,
                'delivery_fee'      => '0.00',
                'total'             => $total,
                'currency_code'     => $quote->currency_code ?: 'GHS',
                'customer_note'     => $quote->message,
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
                'note'            => "Created from quote {$quote->reference}.",
                'actor_user_id'   => $actor->id,
            ]);

            return $order->fresh('items');
        });
    }

    protected function nextOrderReference(?int $organizationId): string
    {
        $count = \App\Models\Storefront\StorefrontOrder::query()
            ->where('organization_id', $organizationId)
            ->withTrashed()
            ->count();

        return 'SO-' . str_pad((string) ($count + 1), 6, '0', STR_PAD_LEFT);
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

    private function notifyStaff(StorefrontQuote $quote, ?int $organizationId): void
    {
        $recipients = User::query()
            ->when($organizationId !== null, fn ($q) => $q->where('organization_id', $organizationId))
            ->whereIn('role', ['salesperson', 'admin', 'superuser'])
            ->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new StorefrontQuoteRequestedNotification($quote));
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

    private function nextReference(?int $organizationId): string
    {
        $count = StorefrontQuote::query()
            ->where('organization_id', $organizationId)
            ->withTrashed()
            ->count();

        return 'QUO-' . str_pad((string) ($count + 1), 6, '0', STR_PAD_LEFT);
    }
}
