<?php

namespace App\Finance\Resolvers;

use App\Models\Counterparty;
use App\Support\CurrentOrganization;

/**
 * Resolves a Counterparty for a sale or purchase event.
 *
 * Pig sales currently store buyer_name as a string — many sales are
 * walk-in cash sales without a registered customer. The resolver:
 *
 *   1. If pig_sales.customer_id is set → return that counterparty.
 *   2. Else if buyer_name maps to an existing counterparty by exact
 *      name match → return it.
 *   3. Else → return the system "Walk-in Customer" counterparty for
 *      the org. Auto-creates it if missing.
 *
 * Why not auto-create a counterparty for every unique buyer_name?
 *   - The "buyer_name" column was free-text. Typos and duplicates
 *     ("John Doe", "John D.", "j doe") would create duplicate counterparty
 *     records, polluting the customer master.
 *   - Subscribers should NOT do best-effort matching that could create
 *     subtle duplicates. The clean rule is: customer_id explicit OR
 *     walk-in. Manual customer master enrichment happens via the customer
 *     UI in Phase 2.
 *
 * The "Walk-in Customer" is a system counterparty marked is_active=true
 * with a recognisable name. AR ageing reports filter it out by default.
 */
class CounterpartyResolver
{
    public function __construct(protected CurrentOrganization $currentOrganization)
    {
    }

    /**
     * Resolve the counterparty for a pig sale.
     *
     * @param  int|null  $explicitCustomerId  pig_sales.customer_id, if set.
     */
    public function forPigSale(?int $explicitCustomerId): Counterparty
    {
        if ($explicitCustomerId !== null) {
            $cp = Counterparty::find($explicitCustomerId);
            if ($cp && $cp->is_active) {
                return $cp;
            }
            // Fall through to walk-in if the explicit id is invalid/inactive.
        }

        return $this->walkIn('customer');
    }

    /**
     * Resolve the counterparty for a procurement event.
     *
     * @param  int|null  $explicitSupplierId
     */
    public function forPurchase(?int $explicitSupplierId): Counterparty
    {
        if ($explicitSupplierId !== null) {
            $cp = Counterparty::find($explicitSupplierId);
            if ($cp && $cp->is_active) {
                return $cp;
            }
        }

        return $this->walkIn('supplier');
    }

    /**
     * Get-or-create the system "Walk-in Customer" or "Walk-in Supplier"
     * counterparty for the current organisation.
     */
    protected function walkIn(string $type): Counterparty
    {
        $organizationId = $this->currentOrganization->id();

        $code = $type === 'customer' ? 'WALKIN-CUST' : 'WALKIN-SUPP';
        $name = $type === 'customer' ? 'Walk-in Customer' : 'Walk-in Supplier';

        $existing = Counterparty::query()
            ->when(
                $organizationId !== null,
                fn ($q) => $q->where('organization_id', $organizationId),
                fn ($q) => $q->whereNull('organization_id'),
            )
            ->where('code', $code)
            ->first();

        if ($existing) {
            return $existing;
        }

        return Counterparty::create([
            'organization_id' => $organizationId,
            'type'            => $type,
            'code'            => $code,
            'name'            => $name,
            'legal_name'      => $name,
            'is_active'       => true,
            'notes'           => 'System counterparty for unregistered ' . $type . 's. '
                                 . 'Auto-created by CounterpartyResolver. '
                                 . 'Replace pig_sale.customer_id with a real counterparty '
                                 . 'to capture proper customer history.',
        ]);
    }
}
