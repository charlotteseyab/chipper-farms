<?php

namespace Tests\Feature\Finance\Schema;

use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\Finance\Account;
use App\Models\Finance\Journal;
use App\Models\Finance\JournalBatch;
use App\Models\Finance\JournalLine;
use App\Models\Organization;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1a — verifies the database CHECK constraints actually reject
 * malformed journal lines and unbalanced posted journals.
 *
 * These are defence-in-depth tests. PostingEngine in Phase 1b is the
 * primary validator, but the DB constraint is what catches a bug that
 * slips past application validation.
 *
 * Note: SQLite enforces CHECK constraints; older MySQL parses but doesn't
 * enforce. Tests are SQLite-friendly.
 */
class JournalBalanceTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected FiscalPeriod $period;
    protected Account $cash;
    protected Account $revenue;
    protected JournalBatch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped(
                'SQLite cannot add the ALTER TABLE check constraints used by the finance schema migrations.'
            );
        }

        $this->org = Organization::create(['slug' => 'cf', 'name' => 'CF']);
        $this->app->make(CurrentOrganization::class)->bind($this->org);

        $year = FiscalYear::create([
            'organization_id' => $this->org->id,
            'fiscal_year'     => (int) now()->year,
            'name'            => 'FY' . now()->year,
            'start_date'      => now()->startOfYear()->toDateString(),
            'end_date'        => now()->endOfYear()->toDateString(),
            'status'          => 'open',
        ]);

        // A current fiscal period is required for journals.
        $this->period = FiscalPeriod::create([
            'organization_id' => $this->org->id,
            'fiscal_year_id'  => $year->id,
            'period_number'   => 1,
            'name'            => 'Test Period',
            'start_date'      => now()->startOfMonth()->toDateString(),
            'end_date'        => now()->endOfMonth()->toDateString(),
            'status'          => 'open',
        ]);

        $this->cash = Account::create([
            'organization_id' => $this->org->id,
            'code'            => '1010',
            'name'            => 'Cash',
            'account_type'    => Account::TYPE_ASSET,
        ]);

        $this->revenue = Account::create([
            'organization_id' => $this->org->id,
            'code'            => '4010',
            'name'            => 'Revenue',
            'account_type'    => Account::TYPE_REVENUE,
        ]);

        $this->batch = JournalBatch::create([
            'organization_id'  => $this->org->id,
            'batch_number'     => 'JB-TEST-001',
            'batch_type'       => 'manual_journal',
            'posting_date'     => now()->toDateString(),
            'fiscal_period_id' => $this->period->id,
            'status'           => JournalBatch::STATUS_DRAFT,
        ]);
    }

    public function test_journal_line_rejects_both_zero(): void
    {
        $journal = Journal::create([
            'organization_id'  => $this->org->id,
            'journal_batch_id' => $this->batch->id,
            'journal_number'   => 'JV-TEST-0001',
            'journal_type'     => 'manual',
            'posting_date'     => now()->toDateString(),
            'fiscal_period_id' => $this->period->id,
            'status'           => Journal::STATUS_DRAFT,
        ]);

        $this->expectException(QueryException::class);

        // Both zero — must fail.
        JournalLine::create([
            'organization_id' => $this->org->id,
            'journal_id'      => $journal->id,
            'line_number'     => 1,
            'account_id'      => $this->cash->id,
            'debit'           => 0,
            'credit'          => 0,
            'debit_tc'        => 0,
            'credit_tc'       => 0,
        ]);
    }

    public function test_journal_line_rejects_both_positive(): void
    {
        $journal = Journal::create([
            'organization_id'  => $this->org->id,
            'journal_batch_id' => $this->batch->id,
            'journal_number'   => 'JV-TEST-0002',
            'journal_type'     => 'manual',
            'posting_date'     => now()->toDateString(),
            'fiscal_period_id' => $this->period->id,
            'status'           => Journal::STATUS_DRAFT,
        ]);

        $this->expectException(QueryException::class);

        // Both positive — must fail.
        JournalLine::create([
            'organization_id' => $this->org->id,
            'journal_id'      => $journal->id,
            'line_number'     => 1,
            'account_id'      => $this->cash->id,
            'debit'           => 100,
            'credit'          => 100,
            'debit_tc'        => 100,
            'credit_tc'       => 100,
        ]);
    }

    public function test_journal_line_accepts_debit_only(): void
    {
        $journal = Journal::create([
            'organization_id'  => $this->org->id,
            'journal_batch_id' => $this->batch->id,
            'journal_number'   => 'JV-TEST-0003',
            'journal_type'     => 'manual',
            'posting_date'     => now()->toDateString(),
            'fiscal_period_id' => $this->period->id,
            'status'           => Journal::STATUS_DRAFT,
        ]);

        $line = JournalLine::create([
            'organization_id' => $this->org->id,
            'journal_id'      => $journal->id,
            'line_number'     => 1,
            'account_id'      => $this->cash->id,
            'debit'           => 100,
            'credit'          => 0,
            'debit_tc'        => 100,
            'credit_tc'       => 0,
        ]);

        $this->assertNotNull($line->id);
        $this->assertSame('100.00', (string) $line->debit);
    }

    public function test_journal_line_accepts_credit_only(): void
    {
        $journal = Journal::create([
            'organization_id'  => $this->org->id,
            'journal_batch_id' => $this->batch->id,
            'journal_number'   => 'JV-TEST-0004',
            'journal_type'     => 'manual',
            'posting_date'     => now()->toDateString(),
            'fiscal_period_id' => $this->period->id,
            'status'           => Journal::STATUS_DRAFT,
        ]);

        $line = JournalLine::create([
            'organization_id' => $this->org->id,
            'journal_id'      => $journal->id,
            'line_number'     => 1,
            'account_id'      => $this->revenue->id,
            'debit'           => 0,
            'credit'          => 100,
            'debit_tc'        => 0,
            'credit_tc'       => 100,
        ]);

        $this->assertNotNull($line->id);
        $this->assertSame('100.00', (string) $line->credit);
    }

    public function test_unbalanced_posted_journal_rejected(): void
    {
        // A draft journal with unequal totals is allowed.
        // A POSTED journal with unequal totals must be rejected.
        $journal = Journal::create([
            'organization_id'  => $this->org->id,
            'journal_batch_id' => $this->batch->id,
            'journal_number'   => 'JV-TEST-0005',
            'journal_type'     => 'manual',
            'posting_date'     => now()->toDateString(),
            'fiscal_period_id' => $this->period->id,
            'status'           => Journal::STATUS_DRAFT,
            'total_debit'      => 100,
            'total_credit'     => 50,  // Unbalanced
        ]);

        $this->assertSame(Journal::STATUS_DRAFT, $journal->status);

        // Now try to flip status to posted while still unbalanced.
        $this->expectException(QueryException::class);
        $journal->update(['status' => Journal::STATUS_POSTED]);
    }

    public function test_balanced_posted_journal_accepted(): void
    {
        $journal = Journal::create([
            'organization_id'  => $this->org->id,
            'journal_batch_id' => $this->batch->id,
            'journal_number'   => 'JV-TEST-0006',
            'journal_type'     => 'manual',
            'posting_date'     => now()->toDateString(),
            'fiscal_period_id' => $this->period->id,
            'status'           => Journal::STATUS_DRAFT,
            'total_debit'      => 100,
            'total_credit'     => 100,
        ]);

        $journal->update(['status' => Journal::STATUS_POSTED]);

        $this->assertSame(Journal::STATUS_POSTED, $journal->fresh()->status);
        $this->assertTrue($journal->fresh()->isBalanced());
    }
}
