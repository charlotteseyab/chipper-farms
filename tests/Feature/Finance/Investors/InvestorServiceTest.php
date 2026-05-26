<?php

namespace Tests\Feature\Finance\Investors;

use App\Models\Finance\Account;
use App\Models\Finance\Investor;
use App\Models\Finance\Journal;
use App\Models\Finance\JournalLine;
use App\Services\Finance\Investors\Exceptions\InvestorException;
use App\Services\Finance\Investors\InvestorService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class InvestorServiceTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        foreach ([
            ['3100', 'Investor Capital', Account::TYPE_EQUITY, 'equity'],
            ['3110', 'Investor Contributions', Account::TYPE_EQUITY, 'equity'],
            ['3120', 'Investor Distributions', Account::TYPE_EQUITY, 'equity'],
            ['3130', 'Investor Profit Allocation', Account::TYPE_EQUITY, 'equity'],
            ['3200', 'Retained Earnings', Account::TYPE_EQUITY, 'equity'],
            ['1030', 'Bank Main', Account::TYPE_ASSET, 'current_asset'],
        ] as [$code, $name, $type, $sub]) {
            Account::create([
                'organization_id' => $this->org->id, 'code' => $code, 'name' => $name,
                'account_type' => $type, 'account_subtype' => $sub, 'currency_code' => 'GHS',
                'is_active' => true, 'allow_manual_journal' => true,
            ]);
        }

        config()->set('investors.accounts', [
            'capital_control' => '3100', 'contributions' => '3110', 'distributions' => '3120',
            'profit_allocation' => '3130', 'retained_earnings' => '3200',
            'preferred_shares' => '2620', 'accrued_returns' => '2630', 'returns_payable' => '2640',
            'preferred_return_exp' => '7120',
        ]);
        config()->set('investors.default_cash_account_code', '1030');
        config()->set('investors.allocation.post_debit_account', '3200');
    }

    public function test_create_investor_opens_capital_account(): void
    {
        $inv = $this->investor('Ama', '0.25');
        $this->assertNotNull($inv->capitalAccount);
        $this->assertSame('0.00', $inv->capitalAccount->current_balance);
    }

    public function test_contribution_posts_and_raises_balance(): void
    {
        $inv = $this->investor('Ama', '0.25');
        $txn = app(InvestorService::class)->contribute($inv, '50000.00', $this->user, null, CarbonImmutable::parse('2026-05-10'));

        $lines = $this->batchLines($txn->journal_batch_id);
        // Dr 1030 / Cr 3110.
        $this->assertTrue($this->lineExists($lines, '1030', debit: '50000.00'));
        $this->assertTrue($this->lineExists($lines, '3110', credit: '50000.00'));

        $this->assertSame('50000.00', $inv->capitalAccount->fresh()->current_balance);
        $this->assertSame('50000.00', $inv->capitalAccount->fresh()->total_contributed);
    }

    public function test_distribution_posts_and_reduces_balance(): void
    {
        $inv = $this->investor('Ama', '0.25');
        app(InvestorService::class)->contribute($inv, '50000.00', $this->user, null, CarbonImmutable::parse('2026-05-10'));

        $txn = app(InvestorService::class)->distribute($inv, '10000.00', $this->user, null, CarbonImmutable::parse('2026-05-20'));
        $lines = $this->batchLines($txn->journal_batch_id);
        // Dr 3120 / Cr 1030.
        $this->assertTrue($this->lineExists($lines, '3120', debit: '10000.00'));
        $this->assertTrue($this->lineExists($lines, '1030', credit: '10000.00'));

        $this->assertSame('40000.00', $inv->capitalAccount->fresh()->current_balance);
    }

    public function test_distribution_cannot_exceed_balance(): void
    {
        $inv = $this->investor('Ama', '0.25');
        app(InvestorService::class)->contribute($inv, '1000.00', $this->user, null, CarbonImmutable::parse('2026-05-10'));

        $this->expectException(InvestorException::class);
        app(InvestorService::class)->distribute($inv, '2000.00', $this->user, null, CarbonImmutable::parse('2026-05-20'));
    }

    // helpers
    protected function investor(string $name, string $ownership): Investor
    {
        return app(InvestorService::class)->createInvestor([
            'name' => $name, 'investor_class' => 'equity', 'ownership_percentage' => $ownership,
        ], $this->user);
    }

    protected function batchLines(?int $batchId)
    {
        return JournalLine::whereIn('journal_id', Journal::whereIn('journal_batch_id', [$batchId])->pluck('id'))->get();
    }

    protected function lineExists($lines, string $code, ?string $debit = null, ?string $credit = null): bool
    {
        $acctId = Account::where('organization_id', $this->org->id)->where('code', $code)->value('id');
        foreach ($lines as $l) {
            if ((int) $l->account_id !== (int) $acctId) continue;
            if ($debit !== null && bccomp((string) $l->debit, $debit, 2) === 0) return true;
            if ($credit !== null && bccomp((string) $l->credit, $credit, 2) === 0) return true;
        }
        return false;
    }
}
