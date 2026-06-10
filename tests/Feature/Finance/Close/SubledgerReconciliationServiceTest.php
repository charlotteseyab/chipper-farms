<?php

namespace Tests\Feature\Finance\Close;

use App\Finance\Drafts\JournalBatchDraft;
use App\Finance\Drafts\JournalDraft;
use App\Finance\Drafts\JournalLineDraft;
use App\Finance\Posting\PostingEngine;
use App\Models\Finance\Account;
use App\Services\Finance\Close\SubledgerReconciliationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * Focused on the GL-balance side of reconciliation (the subledger sources
 * for AR/AP/inventory/grir/pig are exercised by their own module tests).
 * Here we assert the service reads the right control accounts and computes
 * variance correctly when there is no subledger data (everything should be
 * zero/zero => reconciled, except where GL has a balance with no subledger).
 */
class SubledgerReconciliationServiceTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();
    }

    public function test_pig_pool_variance_detected_when_gl_has_balance_but_no_pool(): void
    {
        // Create the 1230 live pig control + an offsetting equity account.
        $livePig = Account::firstOrCreate([
            'organization_id' => $this->org->id,
            'code' => '1230',
        ], [
            'name' => 'Live Pig Inventory',
            'account_type' => Account::TYPE_ASSET, 'account_subtype' => 'current_asset',
            'currency_code' => 'GHS', 'is_active' => true, 'is_control' => true, 'allow_manual_journal' => true,
        ]);

        // Post Dr 1230 / Cr Revenue 500 — GL pig balance = 500, but no
        // group_cost_pools rows exist => subledger 0 => variance 500.
        $this->postJournal('2026-06-10', [
            JournalLineDraft::debit($livePig->id, '500.00'),
            JournalLineDraft::credit($this->revenueAccount->id, '500.00'),
        ]);

        $recon = app(SubledgerReconciliationService::class)->pigPool(CarbonImmutable::parse('2026-06-30'));

        $this->assertSame('500.00', $recon['gl_balance']);
        $this->assertSame('0.00', $recon['subledger_balance']);
        $this->assertSame('500.00', $recon['variance']);
        $this->assertFalse($recon['reconciled']);
    }

    public function test_all_returns_five_reconciliations(): void
    {
        $all = app(SubledgerReconciliationService::class)->all(CarbonImmutable::parse('2026-06-30'));

        $this->assertArrayHasKey('ar_reconciled', $all);
        $this->assertArrayHasKey('ap_reconciled', $all);
        $this->assertArrayHasKey('inventory_reconciled', $all);
        $this->assertArrayHasKey('grir_reconciled', $all);
        $this->assertArrayHasKey('pig_pool_reconciled', $all);
    }

    public function test_empty_books_reconcile(): void
    {
        // No control accounts, no subledger => all zero => reconciled.
        $all = app(SubledgerReconciliationService::class)->all(CarbonImmutable::parse('2026-06-30'));
        foreach ($all as $recon) {
            $this->assertTrue($recon['reconciled'], $recon['label'] . ' should reconcile on empty books.');
        }
    }

    /** @param array<int,JournalLineDraft> $lines */
    protected function postJournal(string $date, array $lines): void
    {
        $draft = new JournalBatchDraft(
            batchType: 'manual_journal',
            postingDate: CarbonImmutable::parse($date),
            journals: [new JournalDraft(journalType: 'manual', lines: $lines)],
        );
        app(PostingEngine::class)->post($draft, $this->user);
    }
}
