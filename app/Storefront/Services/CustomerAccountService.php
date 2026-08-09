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

    /**
     * The AR counterparty for a guest buyer (Phase 9, blueprint §38).
     *
     * A guest order still has to reach finance, and finance is built on
     * counterparties — so "no account" means no `User`, never no counterparty.
     *
     * MATCHING IS BY EMAIL ONLY, AND ONLY WHEN AN EMAIL IS GIVEN
     * ----------------------------------------------------------
     * Matching on phone was considered and rejected: phone numbers are shared
     * within households and re-issued by networks, so a match would silently
     * post one person's order onto another person's ledger. An email match is
     * the same rule `ensureCounterparty()` already applies for registered
     * buyers, so a guest who later registers with the same address lands on the
     * counterparty they were already using instead of a duplicate.
     *
     * A guest with no email gets a fresh counterparty every time. That is the
     * right trade: a duplicate contact record is a tidy-up job, whereas a wrong
     * match is someone else's purchase history and credit balance.
     */
    public function counterpartyForGuest(
        string $name,
        ?string $email,
        ?string $phone,
        ?int $organizationId,
    ): Counterparty {
        $email = $email !== null && trim($email) !== '' ? trim($email) : null;
        $phone = $phone !== null && trim($phone) !== '' ? trim($phone) : null;

        if ($email !== null) {
            $byEmail = Counterparty::query()
                ->where('organization_id', $organizationId)
                ->where('type', '!=', Counterparty::TYPE_SUPPLIER)
                ->where('email', $email)
                ->first();

            if ($byEmail !== null) {
                return $byEmail;
            }
        }

        return Counterparty::create([
            'organization_id'      => $organizationId,
            'type'                 => Counterparty::TYPE_CUSTOMER,
            'code'                 => $this->nextCustomerCode($organizationId),
            'name'                 => $name,
            'primary_contact_name' => $name,
            'email'                => $email,
            'phone'                => $phone,
            'currency_code'        => 'GHS',
            'is_active'            => true,
        ]);
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

    /**
     * Allocate the next customer code (CF-P1-014).
     *
     * Previously 'CUST-' . str_pad(count() + 1). Same two defects as the
     * storefront order reference: concurrent registrations compute the same
     * count and collide on the unique (organization_id, code) key, and the
     * count shifts whenever a counterparty is removed.
     *
     * Note the old count() also filtered on type != supplier, so the series was
     * derived from a subset of the very table whose unique key it had to satisfy
     * — a supplier created between two customer registrations could change the
     * next customer code. A dedicated sequence removes that coupling entirely.
     *
     * year: 0 — non-annual, continuous series. See CheckoutService::nextReference().
     */
    protected function nextCustomerCode(?int $organizationId): string
    {
        return app(\App\Services\Numbering\NumberingService::class)->next(
            sequenceType: 'customer_code',
            prefix: 'CUST',
            year: 0,
            organizationId: $organizationId,
            padding: 5,
            formatTemplate: '{prefix}-{number}',
            initialNumber: $this->highestExistingCustomerCodeNumber($organizationId) + 1,
        );
    }

    /**
     * Highest numeric suffix among existing 'CUST-nnnnn' codes.
     *
     * Deliberately NOT filtered by type: the unique key covers the whole table,
     * so the sequence must clear every existing CUST- code regardless of which
     * counterparty type holds it.
     */
    protected function highestExistingCustomerCodeNumber(?int $organizationId): int
    {
        $highest = Counterparty::query()
            ->where('organization_id', $organizationId)
            ->where('code', 'like', 'CUST-%')
            ->max('code');

        if (! is_string($highest) || ! preg_match('/(\d+)$/', $highest, $m)) {
            return 0;
        }

        return (int) $m[1];
    }

    protected function uniqueReferralCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (CustomerProfile::query()->where('referral_code', $code)->exists());

        return $code;
    }
}
