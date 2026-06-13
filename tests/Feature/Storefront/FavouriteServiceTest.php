<?php

namespace Tests\Feature\Storefront;

use App\Models\Storefront\StorefrontFavourite;
use App\Storefront\Services\FavouriteService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FavouriteServiceTest extends StorefrontTestCase
{
    use RefreshDatabase;

    protected function service(): FavouriteService
    {
        return $this->app->make(FavouriteService::class);
    }

    public function test_toggle_adds_then_removes(): void
    {
        $user    = $this->makeUser('customer');
        $listing = $this->makePublishedListing();

        $this->assertFalse($this->service()->isFavourited($user, $listing));

        $now = $this->service()->toggle($user, $listing);
        $this->assertTrue($now);
        $this->assertTrue($this->service()->isFavourited($user, $listing));
        $this->assertSame(1, StorefrontFavourite::count());

        $now = $this->service()->toggle($user, $listing);
        $this->assertFalse($now);
        $this->assertSame(0, StorefrontFavourite::count());
    }

    public function test_add_is_idempotent(): void
    {
        $user    = $this->makeUser('customer');
        $listing = $this->makePublishedListing();

        $this->service()->add($user, $listing);
        $this->service()->add($user, $listing);

        $this->assertSame(1, StorefrontFavourite::count());
    }
}
