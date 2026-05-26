<?php

namespace Tests\Feature\Finance\SupplierStatements;

use App\Models\Counterparty;
use App\Models\Finance\SupplierStatement;
use App\Models\Finance\SupplierStatementLine;
use App\Services\Finance\SupplierStatements\SupplierStatementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class SupplierStatementServiceTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected Counterparty $supplier;
    protected SupplierStatementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        $this->supplier = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Acme Vendor', 'code' => 'VEND-A',
            'type' => Counterparty::TYPE_SUPPLIER, 'currency_code' => 'GHS',
            'tax_id' => 'P0001234567',
        ]);

        $this->service = $this->app->make(SupplierStatementService::class);
    }

    public function test_create_draft_allocates_number_and_initial_state(): void
    {
        $stmt = $this->service->createDraft(
            $this->supplier,
            new \DateTimeImmutable('2026-12-31'),
            new \DateTimeImmutable('2026-12-01'),
            new \DateTimeImmutable('2026-12-31'),
            '5500.00',
            $this->user,
        );

        $this->assertInstanceOf(SupplierStatement::class, $stmt);
        $this->assertMatchesRegularExpression('/^SSTMT-2026-\d{6}$/', $stmt->statement_number);
        $this->assertSame('5500.00', (string) $stmt->supplier_balance);
        $this->assertSame('0.00', (string) $stmt->our_balance);
        $this->assertSame('5500.00', (string) $stmt->balance_difference);
        $this->assertSame(SupplierStatement::STATUS_DRAFT, $stmt->status);
        $this->assertSame($this->user->id, $stmt->created_by_user_id);
    }

    public function test_create_draft_rejects_non_supplier(): void
    {
        $cust = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Cust', 'code' => 'C-1',
            'type' => Counterparty::TYPE_CUSTOMER, 'currency_code' => 'GHS',
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('not a supplier');
        $this->service->createDraft(
            $cust,
            new \DateTimeImmutable('2026-12-31'),
            new \DateTimeImmutable('2026-12-01'),
            new \DateTimeImmutable('2026-12-31'),
            '0.00',
            $this->user,
        );
    }

    public function test_create_draft_rejects_period_to_before_period_from(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('period_to');

        $this->service->createDraft(
            $this->supplier,
            new \DateTimeImmutable('2026-12-31'),
            new \DateTimeImmutable('2026-12-31'),
            new \DateTimeImmutable('2026-12-01'),  // before
            '0.00',
            $this->user,
        );
    }

    public function test_add_line_appends_with_increasing_line_number(): void
    {
        $stmt = $this->makeDraft();

        $l1 = $this->service->addLine($stmt, [
            'document_date' => '2026-12-05', 'document_kind' => 'invoice',
            'document_reference' => 'ACM-001', 'amount' => '1000.00',
        ]);
        $l2 = $this->service->addLine($stmt, [
            'document_date' => '2026-12-15', 'document_kind' => 'payment',
            'document_reference' => 'CHQ-999', 'amount' => '-500.00',
        ]);

        $this->assertSame(1, $l1->line_number);
        $this->assertSame(2, $l2->line_number);
        $this->assertSame('1000.00', (string) $l1->amount);
        $this->assertSame('-500.00', (string) $l2->amount);
        $this->assertSame(SupplierStatementLine::STATUS_UNMATCHED, $l1->match_status);
    }

    public function test_add_line_validates_kind(): void
    {
        $stmt = $this->makeDraft();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid document_kind');

        $this->service->addLine($stmt, [
            'document_date' => '2026-12-05', 'document_kind' => 'whatever',
            'document_reference' => 'X', 'amount' => '100',
        ]);
    }

    public function test_add_line_rejects_zero_amount(): void
    {
        $stmt = $this->makeDraft();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('non-zero');

        $this->service->addLine($stmt, [
            'document_date' => '2026-12-05', 'document_kind' => 'invoice',
            'document_reference' => 'X', 'amount' => '0',
        ]);
    }

    public function test_add_line_rejects_unparseable_date(): void
    {
        $stmt = $this->makeDraft();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid document_date');

        $this->service->addLine($stmt, [
            'document_date' => 'not-a-date', 'document_kind' => 'invoice',
            'document_reference' => 'X', 'amount' => '100',
        ]);
    }

    public function test_add_line_rejects_too_long_reference(): void
    {
        $stmt = $this->makeDraft();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('100 characters');

        $this->service->addLine($stmt, [
            'document_date' => '2026-12-05', 'document_kind' => 'invoice',
            'document_reference' => str_repeat('A', 101), 'amount' => '100',
        ]);
    }

    public function test_csv_import_atomic_rejects_whole_batch_on_any_error(): void
    {
        $stmt = $this->makeDraft();
        $rows = [
            ['document_date' => '2026-12-01', 'document_kind' => 'invoice', 'document_reference' => 'A1', 'amount' => '100'],
            ['document_date' => 'broken',    'document_kind' => 'invoice', 'document_reference' => 'A2', 'amount' => '200'], // bad
            ['document_date' => '2026-12-03', 'document_kind' => 'invoice', 'document_reference' => 'A3', 'amount' => '300'],
        ];

        try {
            $this->service->addLinesFromCsv($stmt, $rows);
            $this->fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('CSV import failed', $e->getMessage());
        }

        // None should have been added.
        $this->assertSame(0, $stmt->fresh()->lines()->count());
    }

    public function test_csv_import_succeeds_when_all_rows_valid(): void
    {
        $stmt = $this->makeDraft();
        $rows = [
            ['document_date' => '2026-12-01', 'document_kind' => 'invoice', 'document_reference' => 'A1', 'amount' => '100'],
            ['document_date' => '2026-12-03', 'document_kind' => 'payment', 'document_reference' => 'CHQ-1', 'amount' => '-50'],
        ];

        $result = $this->service->addLinesFromCsv($stmt, $rows);
        $this->assertSame(2, $result['added']);
        $this->assertSame(2, $stmt->fresh()->lines()->count());
    }

    public function test_remove_line_refuses_matched(): void
    {
        $stmt = $this->makeDraft();
        $line = $this->service->addLine($stmt, [
            'document_date' => '2026-12-05', 'document_kind' => 'invoice',
            'document_reference' => 'X', 'amount' => '100',
        ]);
        $line->update(['match_status' => SupplierStatementLine::STATUS_MATCHED]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('matched line');
        $this->service->removeLine($line->fresh());
    }

    public function test_mark_reconciled_refuses_when_unresolved_lines_remain(): void
    {
        $stmt = $this->makeDraft();
        $this->service->addLine($stmt, [
            'document_date' => '2026-12-05', 'document_kind' => 'invoice',
            'document_reference' => 'X', 'amount' => '100',
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unresolved');
        $this->service->markReconciled($stmt->fresh(), $this->user);
    }

    public function test_mark_reconciled_refuses_when_balances_disagree(): void
    {
        $stmt = $this->makeDraft();
        // No lines, but balance_difference is non-zero from createDraft.
        $stmt->update(['balance_difference' => '100.00']);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('balance_difference');
        $this->service->markReconciled($stmt->fresh(), $this->user);
    }

    public function test_mark_reconciled_succeeds_when_zero_lines_and_balances_agree(): void
    {
        $stmt = $this->makeDraft(supplierBalance: '0.00');
        $stmt->update(['balance_difference' => '0.00']);

        $result = $this->service->markReconciled($stmt->fresh(), $this->user);
        $this->assertSame(SupplierStatement::STATUS_RECONCILED, $result->status);
    }

    public function test_archive_locks_editing(): void
    {
        $stmt = $this->makeDraft();
        $archived = $this->service->archive($stmt, $this->user, 'duplicate of SSTMT-2026-000005');
        $this->assertSame(SupplierStatement::STATUS_ARCHIVED, $archived->status);
        $this->assertFalse($archived->canEdit());

        // Adding lines must fail.
        $this->expectException(\DomainException::class);
        $this->service->addLine($archived, [
            'document_date' => '2026-12-05', 'document_kind' => 'invoice',
            'document_reference' => 'X', 'amount' => '100',
        ]);
    }

    /* helpers */

    protected function makeDraft(string $supplierBalance = '5500.00'): SupplierStatement
    {
        return $this->service->createDraft(
            $this->supplier,
            new \DateTimeImmutable('2026-12-31'),
            new \DateTimeImmutable('2026-12-01'),
            new \DateTimeImmutable('2026-12-31'),
            $supplierBalance,
            $this->user,
        );
    }
}
