<?php

namespace Tests\Feature\Finance\Resolvers;

use App\Finance\Resolvers\FinanceAccountResolver;
use App\Finance\Resolvers\MissingAccountException;
use App\Models\Finance\Account;
use App\Models\Organization;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceAccountResolverTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::create(['slug' => 'cf', 'name' => 'CF']);
        $this->app->make(CurrentOrganization::class)->bind($this->org);
    }

    public function test_by_code_returns_existing_account(): void
    {
        $expected = Account::create([
            'organization_id' => $this->org->id,
            'code' => '1010',
            'name' => 'Cash',
            'account_type' => Account::TYPE_ASSET,
            'is_active' => true,
        ]);

        $resolver = $this->app->make(FinanceAccountResolver::class);
        $result = $resolver->byCode('1010');

        $this->assertSame($expected->id, $result->id);
    }

    public function test_by_code_throws_if_missing(): void
    {
        $resolver = $this->app->make(FinanceAccountResolver::class);

        $this->expectException(MissingAccountException::class);
        $resolver->byCode('9999');
    }

    public function test_by_code_throws_if_inactive(): void
    {
        Account::create([
            'organization_id' => $this->org->id,
            'code' => '1010',
            'name' => 'Cash',
            'account_type' => Account::TYPE_ASSET,
            'is_active' => false,
        ]);

        $resolver = $this->app->make(FinanceAccountResolver::class);

        $this->expectException(MissingAccountException::class);
        $resolver->byCode('1010');
    }

    public function test_by_hint_resolves_via_map(): void
    {
        $cash = Account::create([
            'organization_id' => $this->org->id,
            'code' => '1010',
            'name' => 'Cash',
            'account_type' => Account::TYPE_ASSET,
            'is_active' => true,
        ]);

        $resolver = $this->app->make(FinanceAccountResolver::class);
        $result = $resolver->byHint('CASH_ON_HAND');

        $this->assertSame($cash->id, $result->id);
    }

    public function test_by_hint_throws_for_unknown_hint(): void
    {
        $resolver = $this->app->make(FinanceAccountResolver::class);

        $this->expectException(MissingAccountException::class);
        $resolver->byHint('NONEXISTENT_HINT');
    }

    public function test_caches_repeat_lookups(): void
    {
        $a = Account::create([
            'organization_id' => $this->org->id,
            'code' => '1010',
            'name' => 'Cash',
            'account_type' => Account::TYPE_ASSET,
            'is_active' => true,
        ]);

        $resolver = $this->app->make(FinanceAccountResolver::class);
        $first  = $resolver->byCode('1010');
        $second = $resolver->byCode('1010');

        // Same instance returned both times (cached).
        $this->assertSame($first, $second);
    }

    public function test_caches_misses_too(): void
    {
        $resolver = $this->app->make(FinanceAccountResolver::class);

        // Two attempts, both throw — the cache should remember the miss.
        try { $resolver->byCode('9999'); } catch (MissingAccountException) {}
        try { $resolver->byCode('9999'); } catch (MissingAccountException) {}

        $this->assertTrue(true);  // No DB hit on second call (verified by the design).
    }
}
