<?php

namespace App\Services\Finance\Customers;

use App\Models\Counterparty;
use App\Models\CounterpartyAddress;
use App\Models\CounterpartyContact;
use App\Models\CustomerCategory;
use App\Services\Numbering\NumberingService;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\DB;

/**
 * Encapsulates customer create/update — handling code auto-generation,
 * default address creation, and primary contact linkage.
 *
 * Why a service instead of straight model save?
 *   - Customer creation typically includes 1+ addresses and 0-N contacts.
 *     Atomicity matters — half-created customers (header but no address)
 *     are bad data.
 *   - Code auto-generation needs the NumberingService for gap-free
 *     sequencing under concurrent writes.
 *   - The "credit hold" lifecycle (set/clear with audit) belongs here
 *     so the controller stays thin.
 *
 * Contract:
 *   - createCustomer($data, $addresses, $contacts) — atomic.
 *   - updateCustomer($cp, $data) — header only; addresses/contacts
 *     have their own methods.
 *   - placeOnCreditHold / clearCreditHold — atomic with audit.
 *
 * The service does NOT post journal entries — customer master changes
 * are not financial events. Phase 2b's invoice code is where finance
 * postings happen.
 */
class CustomerService
{
    public function __construct(
        protected NumberingService $numbering,
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    /**
     * Create a customer with optional inline addresses and contacts.
     *
     * @param  array  $data         Counterparty fields (name, email, etc.)
     * @param  array  $addresses    List of address arrays
     * @param  array  $contacts     List of contact arrays
     */
    public function createCustomer(array $data, array $addresses = [], array $contacts = []): Counterparty
    {
        $organizationId = $this->currentOrganization->resolve()?->id;

        // Force type to customer/both. Defensive — caller should already
        // be passing this but the service won't trust unset.
        $type = $data['type'] ?? Counterparty::TYPE_CUSTOMER;
        if (! in_array($type, [Counterparty::TYPE_CUSTOMER, Counterparty::TYPE_BOTH], true)) {
            $type = Counterparty::TYPE_CUSTOMER;
        }

        return DB::transaction(function () use ($data, $addresses, $contacts, $organizationId, $type) {
            // Auto-generate code if blank.
            $code = $data['code'] ?? null;
            if (empty($code)) {
                $code = $this->numbering->next(
                    sequenceType:   'customer',
                    prefix:         'CUST',
                    year:           (int) now()->year,
                    organizationId: $organizationId,
                    padding:        4,
                );
            }

            $cp = Counterparty::create([
                'organization_id'           => $organizationId,
                'type'                      => $type,
                'code'                      => $code,
                'name'                      => $data['name'],
                'legal_name'                => $data['legal_name'] ?? null,
                'tax_id'                    => $data['tax_id'] ?? null,
                'registration_number'       => $data['registration_number'] ?? null,
                'primary_contact_name'      => $data['primary_contact_name'] ?? null,
                'phone'                     => $data['phone'] ?? null,
                'email'                     => $data['email'] ?? null,
                'address_line_1'            => $data['address_line_1'] ?? null,
                'address_line_2'            => $data['address_line_2'] ?? null,
                'city'                      => $data['city'] ?? null,
                'region'                    => $data['region'] ?? null,
                'country'                   => $data['country'] ?? 'GH',
                'default_payment_method_id' => $data['default_payment_method_id'] ?? null,
                'default_tax_code_id'       => $data['default_tax_code_id'] ?? null,
                'payment_terms_days'        => $data['payment_terms_days'] ?? null,
                'credit_limit'              => $data['credit_limit'] ?? null,
                'is_active'                 => $data['is_active'] ?? true,
                'notes'                     => $data['notes'] ?? null,

                // Phase 2a additions
                'customer_category_id'      => $data['customer_category_id'] ?? null,
                'currency_code'             => $data['currency_code'] ?? 'GHS',
                'discount_percent'          => $data['discount_percent'] ?? 0,
                'on_credit_hold'            => false,
                'internal_notes'            => $data['internal_notes'] ?? null,
            ]);

            foreach ($addresses as $addr) {
                $this->addAddress($cp, $addr);
            }

            foreach ($contacts as $c) {
                $this->addContact($cp, $c);
            }

            return $cp->fresh(['addresses', 'contacts', 'category']);
        });
    }

    public function updateCustomer(Counterparty $cp, array $data): Counterparty
    {
        // Code is intentionally not editable — historical references depend on it.
        $allowed = [
            'name', 'legal_name', 'tax_id', 'registration_number',
            'primary_contact_name', 'phone', 'email',
            'address_line_1', 'address_line_2', 'city', 'region', 'country',
            'default_payment_method_id', 'default_tax_code_id',
            'payment_terms_days', 'credit_limit', 'is_active', 'notes',
            'customer_category_id', 'currency_code', 'discount_percent',
            'internal_notes',
        ];

        $update = array_intersect_key($data, array_flip($allowed));

        $cp->update($update);

        return $cp->fresh(['addresses', 'contacts', 'category']);
    }

    public function addAddress(Counterparty $cp, array $data): CounterpartyAddress
    {
        $organizationId = $this->currentOrganization->resolve()?->id;
        $isDefault = (bool) ($data['is_default'] ?? false);

        return DB::transaction(function () use ($cp, $data, $organizationId, $isDefault) {
            // If this is being set as default, clear other defaults of same type.
            if ($isDefault) {
                CounterpartyAddress::where('counterparty_id', $cp->id)
                    ->where('address_type', $data['address_type'])
                    ->update(['is_default' => false]);
            }

            return CounterpartyAddress::create([
                'organization_id'  => $organizationId,
                'counterparty_id'  => $cp->id,
                'address_type'     => $data['address_type'],
                'label'            => $data['label'] ?? null,
                'attention_to'     => $data['attention_to'] ?? null,
                'address_line_1'   => $data['address_line_1'],
                'address_line_2'   => $data['address_line_2'] ?? null,
                'city'             => $data['city'] ?? null,
                'region'           => $data['region'] ?? null,
                'postal_code'      => $data['postal_code'] ?? null,
                'country'          => $data['country'] ?? 'GH',
                'latitude'         => $data['latitude'] ?? null,
                'longitude'        => $data['longitude'] ?? null,
                'ghana_post_gps'   => $data['ghana_post_gps'] ?? null,
                'is_default'       => $isDefault,
                'is_active'        => true,
                'notes'            => $data['notes'] ?? null,
            ]);
        });
    }

    public function addContact(Counterparty $cp, array $data): CounterpartyContact
    {
        $organizationId = $this->currentOrganization->resolve()?->id;
        $isPrimary = (bool) ($data['is_primary'] ?? false);

        return DB::transaction(function () use ($cp, $data, $organizationId, $isPrimary) {
            if ($isPrimary) {
                CounterpartyContact::where('counterparty_id', $cp->id)
                    ->update(['is_primary' => false]);
            }

            return CounterpartyContact::create([
                'organization_id' => $organizationId,
                'counterparty_id' => $cp->id,
                'name'            => $data['name'],
                'role'            => $data['role'] ?? null,
                'phone'           => $data['phone'] ?? null,
                'phone_alt'       => $data['phone_alt'] ?? null,
                'email'           => $data['email'] ?? null,
                'whatsapp'        => $data['whatsapp'] ?? null,
                'is_primary'      => $isPrimary,
                'is_active'       => true,
                'notes'            => $data['notes'] ?? null,
            ]);
        });
    }

    public function placeOnCreditHold(Counterparty $cp, string $reason): Counterparty
    {
        $cp->update([
            'on_credit_hold'     => true,
            'credit_hold_reason' => $reason,
        ]);
        return $cp->fresh();
    }

    public function clearCreditHold(Counterparty $cp): Counterparty
    {
        $cp->update([
            'on_credit_hold'     => false,
            'credit_hold_reason' => null,
        ]);
        return $cp->fresh();
    }
}
