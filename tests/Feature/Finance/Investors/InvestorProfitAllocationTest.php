<?php

namespace Tests\Feature\Finance\Investors;

use App\Models\Finance\Account;
use App\Models\Finance\Investor;
use App\Models\Finance\InvestorProfitAllocationRun;
use App\Models\Finance\Journal;
use App\Models\Finance\JournalBatch;
use App\Models\Finance\JournalLine;
use App\Services\Finance\Investors\Exceptions\InvestorException;
use App\Services\Finance\Investors\InvestorProfitAllocationService;
use App\Services\Finance\Investors\InvestorReportingService;
use App\Services\Finance\Investors\InvestorService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class InvestorProfitAllocationTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        foreach ([
            ['3100', Account::TYPE_EQUITY, 'equity'], ['3110', Account::TYPE_EQUITY, 'equity'],
            ['3120', Account::TYPE_EQUITY, 'equity'], ['3130', Account::TYPE_EQUITY, 'equity'],
            ['3200', Account::TYPE_EQUITY, 'equity'], ['1030', Account::TYPE_ASSET, 'current_asset'],
        ] as [$code, $type, $sub]) {
            Account::create([
                'organization_id' => $this->org->id, 'code' => $code, 'name' => "Acct {$code}",
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
        config()->set('investors.allocation.method', 'ownership_pct');
        config()->set('investors.allocation.post_debit_account', '3200');
    }

    public function test_ownership_allocation_splits_by_percentage(): void
    {
        $ama = $this->investor('Ama', '0.60');
        $kofi = $this->investor('Kofi', '0.40');

        $run = app(InvestorProfitAllocationService::class)->allocate(
            $this->year, '100000.00', $this->user, CarbonImmutable::parse('2026-05-31')
        );

        // Ama 60000, Kofi 40000.
        $this->assertSame('60000.00', $ama->capitalAccount->fresh()->current_balance);
        $this->assertSame('40000.00', $kofi->capitalAccount->fresh()->current_balance);

        // Journal: Dr 3200 100000 / Cr 3100 100000 (balanced).
        $lines = $this->batchLines($run->transactions->first()->journal_batch_id);
        $this->assertEqualsWithDelta($lines->sum(fn ($l) => (float) $l->debit), $lines->sum(fn ($l) => (float) $l->credit), 0.001);
        $this->assertTrue($this->lineExists($lines, '3200', debit: '100000.00'));
        $this->assertSame(
            'auto_investor_allocation',
            JournalBatch::findOrFail($run->transactions->first()->journal_batch_id)->batch_type,
        );
        $this->assertLessThanOrEqual(30, strlen('auto_investor_allocation'));

        // Total credited to 3100 = 100000.
        $cr3100 = $lines->where('account_id', $this->acctId('3100'))->sum(fn ($l) => (float) $l->credit);
        $this->assertEqualsWithDelta(100000.00, $cr3100, 0.001);
    }

    public function test_allocation_absorbs_rounding_on_last_investor(): void
    {
        // Thirds: 1/3 each of 100.00 -> 33.33, 33.33, 33.34.
        $a = $this->investor('A', '0.333333');
        $b = $this->investor('B', '0.333333');
        $c = $this->investor('C', '0.333334');

        app(InvestorProfitAllocationService::class)->allocate(
            $this->year, '100.00', $this->user, CarbonImmutable::parse('2026-05-31')
        );

        $sum = bcadd(bcadd(
            $a->capitalAccount->fresh()->current_balance,
            $b->capitalAccount->fresh()->current_balance, 2),
            $c->capitalAccount->fresh()->current_balance, 2);

        // The allocated total must equal pool × total-ownership with no drift.
        $this->assertSame('100.00', $sum);
    }

    public function test_double_allocation_is_blocked(): void
    {
        $this->investor('Ama', '1.0');
        app(InvestorProfitAllocationService::class)->allocate($this->year, '5000.00', $this->user, CarbonImmutable::parse('2026-05-31'));

        $this->expectException(InvestorException::class);
        app(InvestorProfitAllocationService::class)->allocate($this->year, '5000.00', $this->user, CarbonImmutable::parse('2026-05-31'));
    }

    public function test_reconciliation_ties_subledger_to_gl_block(): void
    {
        $ama = $this->investor('Ama', '0.50');
        app(InvestorService::class)->contribute($ama, '20000.00', $this->user, null, CarbonImmutable::parse('2026-05-10'));
        app(InvestorProfitAllocationService::class)->allocate($this->year, '10000.00', $this->user, CarbonImmutable::parse('2026-05-31'));
        app(InvestorService::class)->distribute($ama, '5000.00', $this->user, null, CarbonImmutable::parse('2026-05-31'));

        // Subledger: 20000 + (10000*0.5=5000) - 5000 = 20000.
        $recon = app(InvestorReportingService::class)->reconcile(CarbonImmutable::parse('2026-05-31'));
        $this->assertSame('20000.00', $recon['subledger']);
        $this->assertTrue($recon['reconciled'], 'subledger should tie to 3100/3110/3120 block; variance ' . $recon['variance']);
    }

    // helpers
    protected function investor(string $name, string $ownership): Investor
    {
        return app(InvestorService::class)->createInvestor([
            'name' => $name, 'investor_class' => 'equity', 'ownership_percentage' => $ownership,
        ], $this->user);
    }

    protected function acctId(string $code): int
    {
        return (int) Account::where('organization_id', $this->org->id)->where('code', $code)->value('id');
    }

    protected function batchLines(?int $batchId)
    {
        return JournalLine::whereIn('journal_id', Journal::whereIn('journal_batch_id', [$batchId])->pluck('id'))->get();
    }

    protected function lineExists($lines, string $code, ?string $debit = null, ?string $credit = null): bool
    {
        $acctId = $this->acctId($code);
        foreach ($lines as $l) {
            if ((int) $l->account_id !== $acctId) continue;
            if ($debit !== null && bccomp((string) $l->debit, $debit, 2) === 0) return true;
            if ($credit !== null && bccomp((string) $l->credit, $credit, 2) === 0) return true;
        }
        return false;
    }
}
