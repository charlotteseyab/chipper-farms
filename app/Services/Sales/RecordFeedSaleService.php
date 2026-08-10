<?php

namespace App\Services\Sales;

use App\Domain\Events\Sales\FeedSaleRecorded;
use App\Domain\Events\Support\SourceReference;
use App\Models\FeedSale;
use App\Models\FeedType;
use App\Models\User;
use App\Services\DomainEvents\DispatchDomainEvent;
use App\Services\Sales\Exceptions\FeedSaleException;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Write path for recording a feed sale, mirroring RecordPigSaleService.
 *
 * Creates the FeedSale row and dispatches FeedSaleRecorded inside one DB
 * transaction (so the outbox row rolls back with the sale if anything fails).
 * The two subscribers then post revenue/AR and COGS/inventory.
 *
 * Pricing is caller-supplied (unit_price per kg). The storefront's
 * settings-driven price book is a separate concern handled by that module;
 * this service trusts the validated price passed in.
 */
class RecordFeedSaleService
{
    public function __construct(
        protected DispatchDomainEvent $dispatcher,
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data  Validated payload.
     */
    public function execute(FeedType $feedType, User $actor, array $data): FeedSale
    {
        $organizationId = $this->currentOrganization->requireId();

        $quantity = round((float) ($data['quantity_kg'] ?? 0), 4);
        $unitPrice = round((float) ($data['unit_price'] ?? 0), 4);

        if ($quantity <= 0) {
            throw new FeedSaleException('Feed sale quantity must be greater than zero.');
        }
        if ($unitPrice < 0) {
            throw new FeedSaleException('Feed sale unit price cannot be negative.');
        }

        $saleTotal = round($quantity * $unitPrice, 2);
        $channel = $data['channel'] ?? (string) config('feed_sales.default_channel', 'direct');
        $saleDate = isset($data['sale_date'])
            ? CarbonImmutable::parse($data['sale_date'])
            : CarbonImmutable::now();

        return DB::transaction(function () use (
            $feedType, $actor, $data, $organizationId, $quantity, $unitPrice, $saleTotal, $channel, $saleDate
        ) {
            $sale = FeedSale::create([
                'organization_id' => $organizationId,
                'feed_type_id'    => $feedType->id,
                'quantity_kg'     => $quantity,
                'unit_price'      => $unitPrice,
                'sale_total'      => $saleTotal,
                'sale_date'       => $saleDate->toDateString(),
                'channel'         => $channel,
                'payment_status'  => $data['payment_status'] ?? FeedSale::PAYMENT_PENDING,
                'payment_method'  => $data['payment_method'] ?? null,
                'counterparty_id' => $data['counterparty_id'] ?? null,
                'buyer_name'      => $data['buyer_name'] ?? null,
                'buyer_contact'   => $data['buyer_contact'] ?? null,
                // IMP-091 — profile-based tax carried from a storefront order,
                // so the ledger records what the buyer was actually charged.
                // Null for a direct farm-gate sale, which is untouched.
                'tax_profile_id'  => $data['tax_profile_id'] ?? null,
                'tax_amount'      => $data['tax_amount'] ?? null,
                'tax_snapshot'    => $data['tax_snapshot'] ?? null,
                'sold_by_user_id' => $actor->id,
                'notes'           => $data['notes'] ?? null,
            ]);

            $this->dispatcher->dispatch(new FeedSaleRecorded(
                source: new SourceReference(
                    module:         'sales',
                    sourceType:     'feed_sale',
                    sourceId:       $sale->id,
                    organizationId: $organizationId,
                ),
                occurredAt:         $saleDate,
                dispatchedByUserId: $actor->id,
                feedSaleId:         $sale->id,
                feedTypeId:         $feedType->id,
                quantityKg:         $quantity,
                unitPrice:          $unitPrice,
                saleTotal:          $saleTotal,
                channel:            $channel,
                paymentStatus:      (string) $sale->payment_status,
                paymentMethod:      $sale->payment_method,
                counterpartyId:     $sale->counterparty_id ? (int) $sale->counterparty_id : null,
                buyerName:          $sale->buyer_name,
            ));

            return $sale->fresh();
        });
    }
}
