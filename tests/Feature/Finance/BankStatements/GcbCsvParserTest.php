<?php

namespace Tests\Feature\Finance\BankStatements;

use App\Services\Finance\BankStatements\Parsers\GcbCsvParser;
use Tests\TestCase;

/**
 * Phase 3e — GcbCsvParser unit tests.
 */
class GcbCsvParserTest extends TestCase
{
    protected GcbCsvParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new GcbCsvParser();
    }

    public function test_parses_full_gcb_export_with_metadata(): void
    {
        $csv = "GCB Bank Ghana - Statement of Account\n" .
               "Account Number: 1234567890\n" .
               "Period: 01/12/2026 to 31/12/2026\n" .
               "Opening Balance: 12500.00\n" .
               "Closing Balance: 18250.00\n" .
               "\n" .
               "Date,Value Date,Description,Reference,Debit,Credit,Balance\n" .
               "01/12/2026,01/12/2026,DEPOSIT,DEP001,,5000.00,17500.00\n" .
               "15/12/2026,15/12/2026,WITHDRAWAL,W001,500.00,,17000.00\n" .
               "31/12/2026,31/12/2026,INTEREST,INT-Q4,,1250.00,18250.00\n";

        $r = $this->parser->parse($csv);

        $this->assertSame('1234567890', $r['header']['account_number']);
        $this->assertSame('2026-12-01', $r['header']['period_from']);
        $this->assertSame('2026-12-31', $r['header']['period_to']);
        $this->assertSame('12500.00',  $r['header']['opening_balance_per_bank']);
        $this->assertSame('18250.00',  $r['header']['closing_balance_per_bank']);
        $this->assertSame('GHS',       $r['header']['currency_code']);

        $this->assertCount(3, $r['lines']);
        $this->assertSame('5000.00', $r['lines'][0]['credit_amount']);
        $this->assertSame('500.00',  $r['lines'][1]['debit_amount']);
        $this->assertSame('DEP001',  $r['lines'][0]['bank_reference']);
    }

    public function test_detect_picks_up_gcb_signal(): void
    {
        $sample = "GCB Bank Ghana - Statement of Account\nAccount Number: 1\n" .
                  "Date,Value Date,Description,Reference,Debit,Credit,Balance\n";
        $this->assertTrue($this->parser->detect($sample));
    }

    public function test_detect_rejects_non_gcb_csv(): void
    {
        $sample = "Date,Description,Debit,Credit\n01/12/2026,Test,,100.00\n";
        $this->assertFalse($this->parser->detect($sample));
    }

    public function test_throws_when_no_table_header_found(): void
    {
        $csv = "GCB Bank Ghana\nNo table here at all\n";
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Could not locate the transaction table header');
        $this->parser->parse($csv);
    }

    public function test_throws_when_both_debit_and_credit_nonzero(): void
    {
        $csv = "GCB Bank Ghana\n\n" .
               "Date,Value Date,Description,Reference,Debit,Credit,Balance\n" .
               "01/12/2026,01/12/2026,Bad,X,100.00,50.00,0.00\n";
        $this->expectException(\InvalidArgumentException::class);
        $this->parser->parse($csv);
    }

    public function test_handles_metadata_with_thousands_separator(): void
    {
        $csv = "GCB Bank Ghana\n" .
               "Account Number: 1234\n" .
               "Period: 01/12/2026 to 31/12/2026\n" .
               "Opening Balance: 1,234,567.89\n" .
               "Closing Balance: 9,876,543.21\n" .
               "\n" .
               "Date,Value Date,Description,Reference,Debit,Credit,Balance\n" .
               "01/12/2026,01/12/2026,Test,X,,100.00,1234667.89\n";

        $r = $this->parser->parse($csv);
        $this->assertSame('1234567.89', $r['header']['opening_balance_per_bank']);
        $this->assertSame('9876543.21', $r['header']['closing_balance_per_bank']);
    }
}
