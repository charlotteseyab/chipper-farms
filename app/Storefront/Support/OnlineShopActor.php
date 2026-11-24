<?php

namespace App\Storefront\Support;

use App\Models\User;
use App\Services\Users\UserProvisioningService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The account the shop acts as when no person is involved.
 *
 * A prepaid order is accepted the moment its payment is verified — often by a
 * Paystack webhook, with nobody signed in. The sale it records still needs a
 * "sold by", and stamping a real admin there would claim they made a sale by
 * hand. So each farm gets one "Online shop" account, created the first time
 * it is needed.
 *
 * It cannot sign in: it is inactive (Fortify refuses inactive accounts) and
 * its password is random and never stored anywhere. Being inactive also keeps
 * it out of every staff notification, which all filter on `active_status`.
 */
class OnlineShopActor
{
    public const NAME = 'Online shop';

    public static function emailFor(int $organizationId): string
    {
        return "online-shop.{$organizationId}@storefront.invalid";
    }

    public static function for(int $organizationId): User
    {
        $email = self::emailFor($organizationId);

        $existing = User::withoutGlobalScopes()->withTrashed()->where('email', $email)->first();

        if ($existing !== null) {
            return $existing;
        }

        try {
            return app(UserProvisioningService::class)->provision([
                'first_name'      => 'Online',
                'last_name'       => 'shop',
                'email'           => $email,
                'role'            => 'salesperson',
                'password'        => Str::random(64),
                'active_status'   => false,
                'email_verified'  => true,
                'organization_id' => $organizationId,
            ]);
        } catch (ValidationException|UniqueConstraintViolationException $e) {
            // Two payments verified at the same moment both tried to create it.
            // The other one won; use its account.
            $created = User::withoutGlobalScopes()->where('email', $email)->first();

            if ($created === null) {
                throw $e;
            }

            return $created;
        }
    }
}
