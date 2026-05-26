<?php

namespace App\Services\Finance\Suppliers;

use App\Models\Counterparty;
use App\Models\CounterpartyAddress;
use App\Models\CounterpartyContact;
use App\Models\Finance\SupplierCategory;
use App\Services\Numbering\NumberingService;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\DB;

/**
 * Encapsulates supplier create/update — code auto-generation, address +
 * contact creation, banking-detail capture, withholding configuration.
 *
 * Mirrors CustomerService (Phase 2a) in shape and contract. The two
 * services share the underlying counterparties table and address/
 * contact morphs, but they enforce different defaults and different
 * "type" boundaries:
 *
 *   CustomerService → forces type IN (customer, both)
 *   SupplierService → forces type IN (supplier, both)
 *
 * For counterparties that are BOTH customer and supplier (cooperatives,
 * resellers we also buy from), the operations team typically creates
 * the customer first, then upgrades the existing record via update().
 * This service supports either path: createSupplier() with type='both'
 * works on a fresh record; an update() that switches the type works
 * on an existing one.
 *
 * No GL impact — supplier master changes are not financial events.
 * Phase 3b's vendor invoice code is where finance postings happen.
 */
class SupplierService
{
    public function __construct(
        protected NumberingService $numbering,
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    /**
     * Create a supplier with optional inline addresses and contacts.
     *
     * @param  array  $data         Counterparty fields
     * @param  array  $addresses    List of address arrays
     * @param  array  $contacts     List of contact arrays
     */
    public function createSupplier(array $data, array $addresses = [], array $contacts = []): Counterparty
    {
        $organizationId = $this->currentOrganization->resolve()?->id;

        // Force type to supplier/both. Defensive.
        $type = $data['type'] ?? Counterparty::TYPE_SUPPLIER;
        if (! in_array($type, [Counterparty::TYPE_SUPPLIER, Counterparty::TYPE_BOTH], true)) {
            $type = Counterparty::TYPE_SUPPLIER;
        }

        return DB::transaction(function () use ($data, $addresses, $contacts, $organizationId, $type) {
            // Auto-generate code if blank — uses 'VEND' prefix to distinguish
            // from customer codes ('CUST') in the same counterparties table.
            $code = $data['code'] ?? null;
            if (empty($code)) {
                $code = $this->numbering->next(
                    sequenceType:   'supplier',
                    prefix:         'VEND',
                    year:           (int) now()->year,
                    organizationId: $organizationId,
                    padding:        4,
                );
            }

            // Resolve payment terms hierarchy:
            //   data → category → 30 (industry default)
            $paymentTermsDays = $data['payment_terms_days'] ?? null;
            if ($paymentTermsDays === null && ! empty($data['supplier_category_id'])) {
                $category = SupplierCategory::find($data['supplier_category_id']);
                $paymentTermsDays = $category?->payment_terms_days;
            }
            $paymentTermsDays = $paymentTermsDays ?? 30;

            $cp = Counterparty::create([
                'organization_id'                  => $organizationId,
                'type'                             => $type,
                'code'                             => $code,
                'name'                             => $data['name'],
                'legal_name'                       => $data['legal_name'] ?? null,
                'tax_id'                           => $data['tax_id'] ?? null,
                'registration_number'              => $data['registration_number'] ?? null,
                'primary_contact_name'             => $data['primary_contact_name'] ?? null,
                'phone'                            => $data['phone'] ?? null,
                'email'                            => $data['email'] ?? null,
                'address_line_1'                   => $data['address_line_1'] ?? null,
                'address_line_2'                   => $data['address_line_2'] ?? null,
                'city'                             => $data['city'] ?? null,
                'region'                           => $data['region'] ?? null,
                'country'                          => $data['country'] ?? 'GH',

                // Supplier-side fields
                'supplier_category_id'             => $data['supplier_category_id'] ?? null,
                'default_expense_account_id'       => $data['default_expense_account_id'] ?? null,
                'default_payment_method_for_ap_id' => $data['default_payment_method_for_ap_id'] ?? null,
                'payment_terms_days'               => $paymentTermsDays,
                'currency_code'                    => $data['currency_code'] ?? 'GHS',

                // Banking
                'bank_account_name'                => $data['bank_account_name'] ?? null,
                'bank_account_number'              => $data['bank_account_number'] ?? null,
                'bank_name'                        => $data['bank_name'] ?? null,
                'bank_branch'                      => $data['bank_branch'] ?? null,
                'bank_swift'                       => $data['bank_swift'] ?? null,

                // Mobile money
                'momo_provider'                    => $data['momo_provider'] ?? null,
                'momo_number'                      => $data['momo_number'] ?? null,
                'momo_account_name'                => $data['momo_account_name'] ?? null,

                // Withholding
                'withholding_percent'              => $data['withholding_percent'] ?? null,
                'withholding_exempt'               => (bool) ($data['withholding_exempt'] ?? false),

                // Common
                'is_active'                        => (bool) ($data['is_active'] ?? true),
                'notes'                            => $data['notes'] ?? null,
                'internal_notes'                   => $data['internal_notes'] ?? null,
            ]);

            foreach ($addresses as $addr) {
                if (empty($addr['line_1']) && empty($addr['city'])) continue;
                CounterpartyAddress::create(array_merge(
                    ['counterparty_id' => $cp->id, 'organization_id' => $organizationId],
                    $addr,
                ));
            }

            foreach ($contacts as $ct) {
                if (empty($ct['name'])) continue;
                CounterpartyContact::create(array_merge(
                    ['counterparty_id' => $cp->id, 'organization_id' => $organizationId],
                    $ct,
                ));
            }

            return $cp->fresh(['supplierCategory', 'addresses', 'contacts']);
        });
    }

    /**
     * Update header fields (no addresses/contacts).
     *
     * Note on type changes:
     *   Going from supplier → both is fine (extending capabilities).
     *   Going from both → customer (dropping supplier role) is allowed
     *   only if the supplier has no historical purchases. Operations
     *   should consider deactivating instead.
     */
    public function updateSupplier(Counterparty $supplier, array $data): Counterparty
    {
        if (! $supplier->isSupplier()) {
            throw new \DomainException(
                "Counterparty #{$supplier->id} is not a supplier (type: {$supplier->type})"
            );
        }

        // Type-change validation
        if (isset($data['type']) && $data['type'] !== $supplier->type) {
            $this->validateTypeChange($supplier, $data['type']);
        }

        return DB::transaction(function () use ($supplier, $data) {
            $supplier->update(array_filter([
                'name'                             => $data['name'] ?? null,
                'legal_name'                       => $data['legal_name'] ?? null,
                'tax_id'                           => $data['tax_id'] ?? null,
                'registration_number'              => $data['registration_number'] ?? null,
                'primary_contact_name'             => $data['primary_contact_name'] ?? null,
                'phone'                            => $data['phone'] ?? null,
                'email'                            => $data['email'] ?? null,
                'type'                             => $data['type'] ?? null,
                'supplier_category_id'             => $data['supplier_category_id'] ?? null,
                'default_expense_account_id'       => $data['default_expense_account_id'] ?? null,
                'default_payment_method_for_ap_id' => $data['default_payment_method_for_ap_id'] ?? null,
                'payment_terms_days'               => $data['payment_terms_days'] ?? null,
                'currency_code'                    => $data['currency_code'] ?? null,
                'bank_account_name'                => $data['bank_account_name'] ?? null,
                'bank_account_number'              => $data['bank_account_number'] ?? null,
                'bank_name'                        => $data['bank_name'] ?? null,
                'bank_branch'                      => $data['bank_branch'] ?? null,
                'bank_swift'                       => $data['bank_swift'] ?? null,
                'momo_provider'                    => $data['momo_provider'] ?? null,
                'momo_number'                      => $data['momo_number'] ?? null,
                'momo_account_name'                => $data['momo_account_name'] ?? null,
                'withholding_percent'              => $data['withholding_percent'] ?? null,
                'notes'                            => $data['notes'] ?? null,
                'internal_notes'                   => $data['internal_notes'] ?? null,
            ], fn ($v) => $v !== null));

            // Booleans need explicit handling — array_filter strips falsy.
            if (array_key_exists('is_active', $data)) {
                $supplier->update(['is_active' => (bool) $data['is_active']]);
            }
            if (array_key_exists('withholding_exempt', $data)) {
                $supplier->update(['withholding_exempt' => (bool) $data['withholding_exempt']]);
            }

            return $supplier->fresh(['supplierCategory']);
        });
    }

    /**
     * Deactivate a supplier. Doesn't delete — preserves historical
     * vendor invoices and payments. The vendor stops appearing in
     * "create new VI" dropdowns.
     */
    public function deactivate(Counterparty $supplier, ?string $reason = null): Counterparty
    {
        $supplier->update([
            'is_active'      => false,
            'internal_notes' => trim(($supplier->internal_notes ?? '') . "\n\nDeactivated: " . ($reason ?? 'no reason given')),
        ]);
        return $supplier;
    }

    /* internals */

    protected function validateTypeChange(Counterparty $supplier, string $newType): void
    {
        $allowed = [Counterparty::TYPE_SUPPLIER, Counterparty::TYPE_BOTH, Counterparty::TYPE_CUSTOMER];
        if (! in_array($newType, $allowed, true)) {
            throw new \DomainException("Invalid counterparty type: {$newType}");
        }

        // Going from supplier role to customer-only is allowed only if no
        // historical purchases exist.
        $droppingSupplierRole = (
            $supplier->type === Counterparty::TYPE_SUPPLIER && $newType === Counterparty::TYPE_CUSTOMER
        ) || (
            $supplier->type === Counterparty::TYPE_BOTH && $newType === Counterparty::TYPE_CUSTOMER
        );

        if ($droppingSupplierRole && $supplier->hasBeenBilled()) {
            throw new \DomainException(
                "Cannot change supplier #{$supplier->id} to customer-only type — " .
                "they have historical purchases. Deactivate instead."
            );
        }
    }
}
