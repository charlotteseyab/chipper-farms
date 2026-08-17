<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use App\Models\Organization;
use Illuminate\Database\Seeder;

/**
 * Seeds the payment_methods reference table with the methods commonly used
 * on a Ghanaian pig farm. Idempotent via `updateOrCreate` on (organization_id, code).
 *
 * Mobile money is split by provider because in Ghana they route through
 * different merchant settlement accounts — MTN MoMo merchant cashouts hit
 * different bank accounts than AirtelTigo or Vodafone.
 *
 * gl_account_hint values are placeholders; Phase 1 will map these to the
 * actual COA accounts once the COA lands.
 */
class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $organizationIds = Organization::query()->pluck('id');

        if ($organizationIds->isEmpty()) {
            $organizationIds = collect([null]);
        }

        foreach ($organizationIds as $organizationId) {
            $this->seedForOrganization($organizationId);
        }
    }

    private function seedForOrganization(?int $organizationId): void
    {
        $methods = [
            // Cash
            [
                'code' => 'CASH', 'name' => 'Cash', 'is_cash' => true,
                'gl_account_hint' => 'CASH_ON_HAND', 'display_order' => 10,
            ],

            // Mobile money — each provider separated
            [
                'code' => 'MOMO_MTN', 'name' => 'MTN Mobile Money', 'is_mobile_money' => true,
                'requires_reference' => true, 'gl_account_hint' => 'MOMO_MTN', 'display_order' => 20,
            ],
            [
                'code' => 'MOMO_ATGO', 'name' => 'AirtelTigo Money', 'is_mobile_money' => true,
                'requires_reference' => true, 'gl_account_hint' => 'MOMO_ATGO', 'display_order' => 21,
            ],
            [
                'code' => 'MOMO_VODA', 'name' => 'Vodafone Cash', 'is_mobile_money' => true,
                'requires_reference' => true, 'gl_account_hint' => 'MOMO_VODA', 'display_order' => 22,
            ],

            // Bank
            [
                'code' => 'BANK_TRANSFER', 'name' => 'Bank Transfer', 'is_bank' => true,
                'requires_reference' => true, 'gl_account_hint' => 'BANK_MAIN', 'display_order' => 30,
            ],
            [
                'code' => 'CHEQUE', 'name' => 'Cheque', 'is_bank' => true,
                'requires_reference' => true, 'gl_account_hint' => 'BANK_MAIN', 'display_order' => 31,
            ],
            [
                'code' => 'POS', 'name' => 'POS / Card Payment', 'is_bank' => true,
                'requires_reference' => true, 'gl_account_hint' => 'BANK_MAIN', 'display_order' => 32,
            ],
            [
                'code' => 'PAYSTACK', 'name' => 'Paystack Online Checkout', 'is_bank' => true,
                'requires_reference' => true, 'gl_account_hint' => 'PAYSTACK_CLEARING', 'display_order' => 33,
            ],

            // Credit terms
            [
                'code' => 'CREDIT', 'name' => 'On Credit', 'is_credit' => true,
                'gl_account_hint' => 'AR_CONTROL', 'display_order' => 90,
            ],

            // Barter / other
            [
                'code' => 'OTHER', 'name' => 'Other', 'display_order' => 99,
            ],
        ];

        foreach ($methods as $method) {
            PaymentMethod::updateOrCreate(
                [
                    'organization_id' => $organizationId,
                    'code'            => $method['code'],
                ],
                array_merge([
                    'is_cash'            => false,
                    'is_bank'            => false,
                    'is_mobile_money'    => false,
                    'is_credit'          => false,
                    'requires_reference' => false,
                    'is_active'          => true,
                ], $method)
            );
        }
    }
}
