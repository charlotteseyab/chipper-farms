<?php

namespace Tests\Feature\Finance\Schema;

use App\Models\FiscalPeriod;
use App\Models\Finance\JournalBatch;
use App\Models\Finance\JournalPosting;
use App\Models\Organization;
use App\Support\CurrentOrganization;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1a — verifies the idempotency UNIQUE constraint on
 * (organization_id, idempotency_key) on journal_postings.
 *
 * This is THE central guarantee for the async posting model. If the
 * UNIQUE index fails to enforce, two queue retries of the same domain
 * event would create duplicate journal batches, double-counting revenue.
 */
class IdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected FiscalPeriod $period;
    protected JournalBatch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create(['slug' => 'cf', 'name' => 'CF']);
        $this->app->make(CurrentOrganization::class)->bind($this->org);

        $this->period = FiscalPeriod::create([
            'organization_id' => $this->org->id,
            'fiscal_year_id'  => 1,
            'period_number'   => 1,
            'name'            => 'Test Period',
            'start_date'      => now()->startOfMonth()->toDateString(),
            'end_date'        => now()->endOfMonth()->toDateString(),
            'status'          => 'open',
        ]);

        $this->batch = JournalBatch::create([
            'organization_id'  => $this->org->id,
            'batch_number'     => 'JB-IDEMP-001',
            'batch_type'       => 'auto_pig_sale',
            'posting_date'     => now()->toDateString(),
            'fiscal_period_id' => $this->period->id,
            'status'           => JournalBatch::STATUS_DRAFT,
        ]);
    }

    public function test_first_idempotency_key_insert_succeeds(): void
    {
        $posting = JournalPosting::create([
            'organization_id'  => $this->org->id,
            'idempotency_key'  => 'pig_sale.recorded:PigSale:42:revenue',
            'journal_batch_id' => $this->batch->id,
            'posting_purpose'  => JournalPosting::PURPOSE_REVENUE,
            'subscriber_class' => 'App\\Listeners\\Test',
        ]);

        $this->assertNotNull($posting->id);
    }

    public function test_duplicate_idempotency_key_within_same_org_rejected(): void
    {
        JournalPosting::create([
            'organization_id'  => $this->org->id,
            'idempotency_key'  => 'pig_sale.recorded:PigSale:42:revenue',
            'journal_batch_id' => $this->batch->id,
            'posting_purpose'  => JournalPosting::PURPOSE_REVENUE,
            'subscriber_class' => 'App\\Listeners\\Test',
        ]);

        // Second attempt with the same key must throw.
        $this->expectException(QueryException::class);

        JournalPosting::create([
            'organization_id'  => $this->org->id,
            'idempotency_key'  => 'pig_sale.recorded:PigSale:42:revenue',
            'journal_batch_id' => $this->batch->id,
            'posting_purpose'  => JournalPosting::PURPOSE_REVENUE,
            'subscriber_class' => 'App\\Listeners\\Test',
        ]);
    }

    public function test_different_purposes_for_same_source_event_allowed(): void
    {
        // A single pig sale legitimately produces multiple postings
        // (revenue + COGS), each with a distinct purpose suffix.
        JournalPosting::create([
            'organization_id'  => $this->org->id,
            'idempotency_key'  => 'pig_sale.recorded:PigSale:42:revenue',
            'journal_batch_id' => $this->batch->id,
            'posting_purpose'  => JournalPosting::PURPOSE_REVENUE,
        ]);

        $cogs = JournalPosting::create([
            'organization_id'  => $this->org->id,
            'idempotency_key'  => 'pig_sale.recorded:PigSale:42:cogs',
            'journal_batch_id' => $this->batch->id,
            'posting_purpose'  => JournalPosting::PURPOSE_COGS,
        ]);

        $this->assertNotNull($cogs->id);
        $this->assertSame(2, JournalPosting::count());
    }

    public function test_same_key_across_different_orgs_allowed(): void
    {
        $org2 = Organization::create(['slug' => 'cf2', 'name' => 'CF2']);

        $batch2 = JournalBatch::create([
            'organization_id'  => $org2->id,
            'batch_number'     => 'JB-IDEMP-002',
            'batch_type'       => 'auto_pig_sale',
            'posting_date'     => now()->toDateString(),
            'fiscal_period_id' => $this->period->id,
            'status'           => JournalBatch::STATUS_DRAFT,
        ]);

        JournalPosting::create([
            'organization_id'  => $this->org->id,
            'idempotency_key'  => 'pig_sale.recorded:PigSale:42:revenue',
            'journal_batch_id' => $this->batch->id,
            'posting_purpose'  => JournalPosting::PURPOSE_REVENUE,
        ]);

        $crossOrg = JournalPosting::create([
            'organization_id'  => $org2->id,
            'idempotency_key'  => 'pig_sale.recorded:PigSale:42:revenue',
            'journal_batch_id' => $batch2->id,
            'posting_purpose'  => JournalPosting::PURPOSE_REVENUE,
        ]);

        $this->assertNotNull($crossOrg->id);
        // UNIQUE is on (org, key) — different orgs may share the same key.
    }

    public function test_build_idempotency_key_format(): void
    {
        $key = JournalPosting::buildIdempotencyKey(
            'pig_sale.recorded',
            'App\\Models\\PigSale',
            42,
            JournalPosting::PURPOSE_REVENUE,
        );

        $this->assertSame('pig_sale.recorded:PigSale:42:revenue', $key);
    }
}
