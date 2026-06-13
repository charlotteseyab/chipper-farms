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
