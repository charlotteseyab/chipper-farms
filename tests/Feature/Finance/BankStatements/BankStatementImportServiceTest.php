<?php

namespace Tests\Feature\Finance\BankStatements;

use App\Models\Finance\BankAccount;
use App\Models\Finance\BankStatement;
use App\Models\Finance\BankStatementLine;
use App\Services\Finance\BankStatements\BankStatementImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * Phase 3e — BankStatementImportService tests.
 */
class BankStatementImportServiceTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected BankStatementImportService $service;
    protected BankAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        Storage::fake('local');
        Config::set('finance.attachments.disk', 'local');

        $this->bankAccount = BankAccount::create([
            'organization_id'      => $this->org->id,
            'account_id'           => $this->cashAccount->id,
            'label'                => 'Operations',
            'bank_name'            => 'GCB',
            'account_number'       => '1234567890',
            'currency_code'        => 'GHS',
            'opening_balance'      => '0.00',
            'opening_balance_date' => '2026-01-01',
            'is_active'            => true,
            'created_by_user_id'   => $this->user->id,
        ]);

        $this->service = $this->app->make(BankStatementImportService::class);
    }

    public function test_imports_generic_csv_into_statement_and_lines(): void
    {
        $csv = "Date,Description,Debit,Credit,Balance\n" .
               "01/12/2026,Salary,,3500.00,3500.00\n" .
               "02/12/2026,Rent,1200.00,,2300.00\n";

        $stmt = $this->service->import(
            $this->bankAccount,
            $csv,
            'december-2026.csv',
            [
                'opening_balance_per_bank' => '0.00',
                'closing_balance_per_bank' => '2300.00',
            ],
            $this->user,
        );

        $this->assertInstanceOf(BankStatement::class, $stmt);
        $this->assertMatchesRegularExpression('/^BSTMT-\d{4}-\d{6}$/', $stmt->statement_number);
        $this->assertSame(2, $stmt->line_count);
        $this->assertSame('3500.00', (string) $stmt->total_credits);
        $this->assertSame('1200.00', (string) $stmt->total_debits);
        $this->assertSame(BankStatement::STATUS_IMPORTED, $stmt->status);
        $this->assertSame(2, BankStatementLine::where('bank_statement_id', $stmt->id)->count());
        $this->assertSame('generic_csv', $stmt->source_format);
        $this->assertNotNull($stmt->file_sha256);
    }

    public function test_blocks_duplicate_import_of_same_file(): void
    {
        $csv = "Date,Description,Debit,Credit\n01/12/2026,Test,,100.00\n";
        $this->service->import(
            $this->bankAccount, $csv, 'a.csv',
            ['opening_balance_per_bank' => '0', 'closing_balance_per_bank' => '100'],
            $this->user,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already been imported');
        $this->service->import(
            $this->bankAccount, $csv, 'b.csv',
            ['opening_balance_per_bank' => '0', 'closing_balance_per_bank' => '100'],
            $this->user,
        );
    }

    public function test_infers_period_from_line_dates_when_not_supplied(): void
    {
        $csv = "Date,Description,Debit,Credit\n" .
               "05/12/2026,A,,100.00\n" .
               "20/12/2026,B,50.00,\n";

        $stmt = $this->service->import(
            $this->bankAccount, $csv, 'x.csv',
            ['opening_balance_per_bank' => '0', 'closing_balance_per_bank' => '50'],
            $this->user,
        );

        $this->assertSame('2026-12-05', $stmt->period_from->toDateString());
        $this->assertSame('2026-12-20', $stmt->period_to->toDateString());
    }

    public function test_throws_on_missing_balances(): void
    {
        $csv = "Date,Description,Debit,Credit\n01/12/2026,Test,,100.00\n";

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Opening AND closing balance');
        $this->service->import(
            $this->bankAccount, $csv, 'x.csv', [],
            $this->user,
        );
    }

    public function test_void_blocks_when_lines_matched(): void
    {
        $csv = "Date,Description,Debit,Credit\n01/12/2026,Test,,100.00\n";
        $stmt = $this->service->import(
            $this->bankAccount, $csv, 'x.csv',
            ['opening_balance_per_bank' => '0', 'closing_balance_per_bank' => '100'],
            $this->user,
        );

        // Forcibly set a line to matched.
        $line = $stmt->lines->first();
        $line->update(['match_status' => BankStatementLine::STATUS_MATCHED, 'matched_type' => 'payment', 'matched_id' => 1]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot void');
        $this->service->void($stmt, $this->user, 'wrong file');
    }

    public function test_void_succeeds_with_unmatched_lines(): void
    {
        $csv = "Date,Description,Debit,Credit\n01/12/2026,Test,,100.00\n";
        $stmt = $this->service->import(
            $this->bankAccount, $csv, 'x.csv',
            ['opening_balance_per_bank' => '0', 'closing_balance_per_bank' => '100'],
            $this->user,
        );

        $voided = $this->service->void($stmt, $this->user, 'mistake');
        $this->assertTrue($voided->isVoid());
        $this->assertSame('mistake', $voided->void_reason);
    }

    public function test_void_requires_reason(): void
    {
        $csv = "Date,Description,Debit,Credit\n01/12/2026,Test,,100.00\n";
        $stmt = $this->service->import(
            $this->bankAccount, $csv, 'x.csv',
            ['opening_balance_per_bank' => '0', 'closing_balance_per_bank' => '100'],
            $this->user,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->service->void($stmt, $this->user, '');
    }

    public function test_persists_file_to_disk(): void
    {
        $csv = "Date,Description,Debit,Credit\n01/12/2026,Test,,100.00\n";
        $stmt = $this->service->import(
            $this->bankAccount, $csv, 'mybank.csv',
            ['opening_balance_per_bank' => '0', 'closing_balance_per_bank' => '100'],
            $this->user,
        );

        $this->assertSame('local', $stmt->file_disk);
        $this->assertNotNull($stmt->file_path);
        $this->assertTrue(Storage::disk('local')->exists($stmt->file_path));
    }

    public function test_rejects_inactive_bank_account(): void
    {
        $this->bankAccount->update(['is_active' => false]);

        $csv = "Date,Description,Debit,Credit\n01/12/2026,Test,,100.00\n";
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('not usable');
        $this->service->import(
            $this->bankAccount, $csv, 'x.csv',
            ['opening_balance_per_bank' => '0', 'closing_balance_per_bank' => '100'],
            $this->user,
        );
    }

    public function test_rejects_empty_file(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Empty file');
        $this->service->import(
            $this->bankAccount, '', 'x.csv',
            ['opening_balance_per_bank' => '0', 'closing_balance_per_bank' => '0'],
            $this->user,
        );
    }
}
