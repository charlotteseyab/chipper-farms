<?php

namespace App\Storefront\Payments;

/**
 * Validates Paystack webhook signatures. Paystack signs the raw request body
 * with HMAC-SHA512 using the secret key and sends it in the
 * x-paystack-signature header. We recompute and compare in constant time.
 */
class PaystackSignature
{
    public static function isValid(string $rawBody, ?string $signature, ?string $secretKey): bool
    {
        if (empty($signature) || empty($secretKey)) {
            return false;
        }

        $expected = hash_hmac('sha512', $rawBody, $secretKey);

        return hash_equals($expected, $signature);
    }
}
