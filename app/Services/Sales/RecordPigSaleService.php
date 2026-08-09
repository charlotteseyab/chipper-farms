<?php

namespace App\Services\Sales;

use App\Domain\Events\Sales\PigSaleRecorded;
use App\Domain\Events\Support\SourceReference;
use App\Models\PigManagement;
use App\Models\PigSale;
use App\Models\Setting;
use App\Models\User;
use App\Services\Audit\AuditTrailService;
use App\Services\DomainEvents\DispatchDomainEvent;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Encapsulates the write path for recording a pig sale.
 *
 * Phase 0.2: introduced, fixing the sold_by attribution bug.
 * Phase 0.4: now emits a PigSaleRecorded domain event inside the same DB
 *            transaction so finance (Phase 1+) can subscribe safely.
 *
 * The organization id for the event comes from CurrentOrganization, which
 * is bound by the EnsureOrganizationContext middleware. In non-HTTP contexts
 * (jobs, tests) the caller must bind the resolver explicitly before invoking
 * this service.
 */
class RecordPigSaleService
{
    public function __construct(
        protected DispatchDomainEvent $dispatcher,
        protected CurrentOrganization $currentOrganization,
        protected AuditTrailService $audit,
    ) {
    }

    /**
     * Record a sale.
     *
     * @param  PigManagement  $pig      The pig being sold (still alive or deceased).
     * @param  User           $actor    The authenticated user recording the sale.
     * @param  array          $data     Validated payload from RecordPigSaleRequest.
     * @return PigSale
     */
    public function execute(PigManagement $pig, User $actor, array $data): PigSale
    {
        $organizationId = $this->currentOrganization->requireId();

        return DB::transaction(function () use ($pig, $actor, $data, $organizationId) {
            // Normalise weight.
            if (isset($data['sale_weight'])) {
                $data['sale_weight'] = round((float) $data['sale_weight'], 2);
            }

            // Resolve the price rate from settings at the moment of sale.
            // EXCEPTION: storefront-originated sales carry an agreed price the
            // customer already accepted/paid. When price_source === 'explicit'
            // we trust the supplied price_per_kg / sale_price and do NOT
            // re-price from settings. Default behaviour (operations-recorded
            // sales) is unchanged.
            $explicitPrice = ($data['price_source'] ?? null) === 'explicit';

            if (! $explicitPrice) {
                if (($data['weight_type'] ?? null) === 'live') {
                    $data['price_per_kg'] = (float) Setting::get('sales.pricing.live_weight_price', 30.00);
                } elseif (($data['weight_type'] ?? null) === 'carcass') {
                    $data['price_per_kg'] = (float) Setting::get('sales.pricing.carcass_weight_price', 50.00);
                }
            }
            unset($data['price_source']);

            // sale_purpose KEEPS its constrained value; the free text goes to
            // its own column.
            //
            // This used to overwrite sale_purpose with the free text, which the
            // pig_sales_sale_purpose_check constraint rejects — so selecting
            // "Other" and typing a reason was a 500 in production. PigSale's
            // accessor surfaces the text on read, so every existing
            // $sale->sale_purpose call site is unaffected.
            if (($data['sale_purpose'] ?? null) !== 'other') {
                $data['sale_purpose_other'] = null;
            }

            // payment_method carries no CHECK constraint, so the existing
            // collapse there is left alone rather than changed speculatively.
            if (($data['payment_method'] ?? null) === 'other') {
                $data['payment_method'] = $data['payment_method_other'] ?? 'other';
            }
            unset($data['payment_method_other']);

            if (! isset($data['buyer_name']) || $data['buyer_name'] === null) {
                $data['buyer_name'] = '';
            }

            // Seller attribution — the Phase 0.2 fix.
            $data['sold_by_user_id'] = $actor->id;
            $data['sold_by_name']    = $actor->name;
            $data['sold_by']         = $actor->name;  // Legacy deprecation window.

            $data['pig_id'] = $pig->id;

            $sale = PigSale::create($data);

            if (! $pig->trashed()) {
                $pig->delete();
            }

            $this->audit->log('sales', 'sold', [
                'user' => $actor,
                'auditable' => $sale,
                'organization_id' => $organizationId,
                'record_label' => $pig->ear_tag_number ?: $pig->code,
                'description' => "Sale recorded for pig {$pig->code}.",
                'new_values' => $sale->only(['sale_date', 'sale_weight', 'weight_type', 'price_per_kg', 'sale_price', 'buyer_name', 'payment_status']),
            ]);

            // Phase 0.4: dispatch the domain event inside this same transaction.
            // If anything below this rolls back, so does the outbox row.
            $this->dispatcher->dispatch(new PigSaleRecorded(
                source: new SourceReference(
                    module:         'sales',
                    sourceType:     PigSale::class,
                    sourceId:       (int) $sale->id,
                    organizationId: $organizationId,
                ),
                occurredAt: CarbonImmutable::parse($sale->sale_date)->setTime(
                    (int) now()->hour, (int) now()->minute, (int) now()->second
                ),
                dispatchedByUserId: $actor->id,
                pigId:          (int) $pig->id,
                pigCode:        (string) ($pig->code ?? ''),
                saleWeight:     (float) $sale->sale_weight,
                weightType:     (string) $sale->weight_type,
                pricePerKg:     (float) $sale->price_per_kg,
                saleTotal:      (float) $sale->sale_price,
                salePurpose:    (string) $sale->sale_purpose,
                paymentStatus:  (string) $sale->payment_status,
                paymentMethod:  $sale->payment_method,
                buyerName:      $sale->buyer_name ?: null,
                transportCost:  $sale->transport_cost !== null ? (float) $sale->transport_cost : null,
            ));

            return $sale;
        });
    }
}
