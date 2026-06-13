<?php

namespace Tests\Feature\Storefront;

use App\Models\Counterparty;
use App\Models\CustomerProfile;
use App\Storefront\Services\CustomerAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CustomerAccountServiceTest extends StorefrontTestCase
{
    use RefreshDatabase;

    protected function service(): CustomerAccountService
    {
        return $this->app->make(CustomerAccountService::class);
    }

    public function test_it_creates_profile_and_counterparty_for_a_customer(): void
    {
        $user = $this->makeUser('customer');

        $profile = $this->service()->ensureProfile($user);

        $this->assertInstanceOf(CustomerProfile::class, $profile);
        $this->assertSame($user->id, $profile->user_id);
        $this->assertSame($this->org->id, $profile->organization_id);

        $cp = Counterparty::find($profile->counterparty_id);
        $this->assertNotNull($cp);
        $this->assertSame(Counterparty::TYPE_CUSTOMER, $cp->type);
        $this->assertSame($user->email, $cp->email);
        $this->assertNotEmpty($profile->referral_code);
    }

    public function test_it_is_idempotent(): void
    {
        $user = $this->makeUser('customer');

        $first  = $this->service()->ensureProfile($user);
        $second = $this->service()->ensureProfile($user);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, CustomerProfile::where('user_id', $user->id)->count());
    }
}
