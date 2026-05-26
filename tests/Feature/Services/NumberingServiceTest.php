<?php

namespace Tests\Feature\Services;

use App\Models\DocumentSequence;
use App\Services\Numbering\NumberingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 0.8 — NumberingService invariants.
 *
 * The service MUST:
 *   - Return unique numbers when called repeatedly
 *   - Format correctly (prefix-year-padded-number)
 *   - Create a new sequence row on first call for a (type, year, org) combination
 *   - Increment next_number by exactly 1 per call
 *   - Scope sequences by organization_id
 *   - Respect custom padding and format_template
 */
class NumberingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected NumberingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(NumberingService::class);
    }

    public function test_returns_first_number_on_cold_start(): void
    {
        $n = $this->service->next('pig_sale', 'INV', 2026);

        $this->assertSame('INV-2026-000001', $n);
    }

    public function test_increments_on_subsequent_calls(): void
    {
        $n1 = $this->service->next('pig_sale', 'INV', 2026);
        $n2 = $this->service->next('pig_sale', 'INV', 2026);
        $n3 = $this->service->next('pig_sale', 'INV', 2026);

        $this->assertSame('INV-2026-000001', $n1);
        $this->assertSame('INV-2026-000002', $n2);
        $this->assertSame('INV-2026-000003', $n3);

        $seq = DocumentSequence::where('sequence_type', 'pig_sale')->where('year', 2026)->first();
        $this->assertSame(4, (int) $seq->next_number);
    }

    public function test_different_types_are_independent(): void
    {
        $this->service->next('pig_sale', 'INV', 2026);
        $this->service->next('pig_sale', 'INV', 2026);
        $first_pr = $this->service->next('purchase_request', 'PR', 2026);

        $this->assertSame('PR-2026-000001', $first_pr);
    }

    public function test_different_years_reset_to_one(): void
    {
        $this->service->next('pig_sale', 'INV', 2026);
        $this->service->next('pig_sale', 'INV', 2026);
        $first_next_year = $this->service->next('pig_sale', 'INV', 2027);

        $this->assertSame('INV-2027-000001', $first_next_year);
    }

    public function test_different_organizations_are_independent(): void
    {
        $n_org1 = $this->service->next('pig_sale', 'INV', 2026, organizationId: 1);
        $n_org2 = $this->service->next('pig_sale', 'INV', 2026, organizationId: 2);

        $this->assertSame('INV-2026-000001', $n_org1);
        $this->assertSame('INV-2026-000001', $n_org2);
    }

    public function test_padding_parameter_is_respected(): void
    {
        $n = $this->service->next('pig_sale', 'INV', 2026, padding: 4);

        $this->assertSame('INV-2026-0001', $n);
    }

    public function test_format_template_overrides_default_format(): void
    {
        $n = $this->service->next('pig_sale', 'INV', 2026, formatTemplate: '{year}/{prefix}/{number}');

        $this->assertSame('2026/INV/000001', $n);
    }

    public function test_peek_does_not_consume_number(): void
    {
        $this->service->next('pig_sale', 'INV', 2026);          // Consumes 000001.
        $peek1 = $this->service->peek('pig_sale', 'INV', 2026); // Should be 000002.
        $peek2 = $this->service->peek('pig_sale', 'INV', 2026); // Still 000002.

        $this->assertSame('INV-2026-000002', $peek1);
        $this->assertSame('INV-2026-000002', $peek2);

        $n = $this->service->next('pig_sale', 'INV', 2026);
        $this->assertSame('INV-2026-000002', $n);
    }

    public function test_sequence_row_is_created_on_first_use(): void
    {
        $this->assertSame(0, DocumentSequence::count());

        $this->service->next('pig_sale', 'INV', 2026);

        $this->assertSame(1, DocumentSequence::count());
        $seq = DocumentSequence::first();
        $this->assertSame('pig_sale', $seq->sequence_type);
        $this->assertSame('INV', $seq->prefix);
        $this->assertSame(2026, (int) $seq->year);
        $this->assertSame(2, (int) $seq->next_number);
    }

    public function test_concurrent_allocations_produce_unique_numbers(): void
    {
        // Simulate 100 sequential allocations; this test doesn't test real
        // concurrency (which would require multiple DB connections), but it
        // does verify that repeated calls within a single process yield
        // strictly increasing unique numbers — which is the contract.
        $numbers = [];
        for ($i = 0; $i < 100; $i++) {
            $numbers[] = $this->service->next('pig_sale', 'INV', 2026);
        }

        $this->assertCount(100, $numbers);
        $this->assertCount(100, array_unique($numbers));
        $this->assertSame('INV-2026-000001', $numbers[0]);
        $this->assertSame('INV-2026-000100', $numbers[99]);
    }
}