<?php

namespace App\Storefront\Services;

use App\Models\Counterparty;
use App\Models\CustomerProfile;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Bridges an authenticated storefront customer (a User with the `customer`
 * role) to the finance/AR world.
 *
 * Every storefront customer is, for finance purposes, a Counterparty of type
 * customer. This service get-or-creates:
 *   1. A Counterparty (the AR entity invoices/receipts will attach to), and
 *   2. A CustomerProfile linking user ↔ counterparty + storefront metadata.
 *
 * This is what makes Phase 4 finance integration clean: by the time an order
 * is placed, the customer already resolves to a real Counterparty, so the
 * existing CounterpartyResolver / InvoiceService / ReceiptService flows work
 * unchanged.
 *
 * Idempotent and transaction-safe.
 */
class CustomerAccountService
{
    public function __construct(protected CurrentOrganization $currentOrganization)
    {
    }

    public function ensureProfile(User $user): CustomerProfile
    {
        $existing = CustomerProfile::query()->where('user_id', $user->id)->first();
        if ($existing !== null) {
            return $existing;
        }

        $organizationId = $user->organization_id
            ?? $this->currentOrganization->id();

        return DB::transaction(function () use ($user, $organizationId) {
            $counterparty = $this->ensureCounterparty($user, $organizationId);

            return CustomerProfile::create([
                'organization_id' => $organizationId,
                'counterparty_id' => $counterparty->id,
                'user_id'         => $user->id,
                'email_subscribed'=> true,
                'order_count'     => 0,
                'lifetime_value'  => 0,
                'referral_code'   => $this->uniqueReferralCode(),
            ]);
        });
    }

    protected function ensureCounterparty(User $user, ?int $organizationId): Counterparty
    {
        // Reuse an existing customer counterparty matched on email, else create.
        if ($user->email) {
            $byEmail = Counterparty::query()
                ->where('organization_id', $organizationId)
                ->where('type', '!=', Counterparty::TYPE_SUPPLIER)
                ->where('email', $user->email)
                ->first();

            if ($byEmail !== null) {
                return $byEmail;
            }
        }

        return Counterparty::create([
            'organization_id'      => $organizationId,
            'type'                 => Counterparty::TYPE_CUSTOMER,
            'code'                 => $this->nextCustomerCode($organizationId),
            'name'                 => $user->name ?: ($user->email ?? 'Storefront customer'),
            'primary_contact_name' => $user->name,
            'email'                => $user->email,
            'phone'                => $user->phone ?? null,
            'currency_code'        => 'GHS',
            'is_active'            => true,
        ]);
    }

    protected function nextCustomerCode(?int $organizationId): string
    {
        $count = Counterparty::query()
            ->where('organization_id', $organizationId)
            ->where('type', '!=', Counterparty::TYPE_SUPPLIER)
            ->count();

        return 'CUST-' . str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);
    }

    protected function uniqueReferralCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (CustomerProfile::query()->where('referral_code', $code)->exists());

        return $code;
    }
}
