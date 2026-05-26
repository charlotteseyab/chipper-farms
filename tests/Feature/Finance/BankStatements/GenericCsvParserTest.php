<?php

namespace Tests\Feature\Finance\BankStatements;

use App\Services\Finance\BankStatements\Parsers\GenericCsvParser;
use Tests\TestCase;

/**
 * Phase 3e — GenericCsvParser unit tests.
 */
class GenericCsvParserTest extends TestCase
{
    protected GenericCsvParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new GenericCsvParser();
    }

    public function test_parses_basic_5_column_csv(): void
    {
        $csv = "Date,Description,Debit,Credit,Balance\n" .
               "01/12/2026,Salary,,3500.00,3500.00\n" .
               "02/12/2026,Rent,1200.00,,2300.00\n";

        $r = $this->parser->parse($csv);

        $this->assertCount(2, $r['lines']);
        $this->assertSame('2026-12-01', $r['lines'][0]['value_date']);
        $this->assertSame('0.00', $r['lines'][0]['debit_amount']);
        $this->assertSame('3500.00', $r['lines'][0]['credit_amount']);
        $this->assertSame('1200.00', $r['lines'][1]['debit_amount']);
        $this->assertSame('Rent', $r['lines'][1]['description']);
    }

    public function test_parses_single_amount_column_with_signs(): void
    {
        $csv = "Date,Description,Amount,Balance\n" .
               "01/12/2026,Salary,3500.00,3500.00\n" .
               "02/12/2026,Rent,-1200.00,2300.00\n";

        $r = $this->parser->parse($csv);

        $this->assertCount(2, $r['lines']);
        $this->assertSame('3500.00', $r['lines'][0]['credit_amount']);
        $this->assertSame('0.00',    $r['lines'][0]['debit_amount']);
        $this->assertSame('1200.00', $r['lines'][1]['debit_amount']);
        $this->assertSame('0.00',    $r['lines'][1]['credit_amount']);
    }

    public function test_parses_with_currency_codes_and_thousands(): void
    {
        $csv = "Date,Description,Debit,Credit,Balance\n" .
               "01/12/2026,Big Deposit,,\"GHS 1,234,567.89\",1234567.89\n";

        $r = $this->parser->parse($csv);

        $this->assertSame('1234567.89', $r['lines'][0]['credit_amount']);
    }

    public function test_parses_parenthesis_negative_as_debit(): void
    {
        $csv = "Date,Description,Amount\n" .
               "01/12/2026,Refund,(1500.00)\n";

        $r = $this->parser->parse($csv);

        $this->assertSame('1500.00', $r['lines'][0]['debit_amount']);
        $this->assertSame('0.00',    $r['lines'][0]['credit_amount']);
    }

    public function test_handles_alternative_column_names(): void
    {
        $csv = "Posting Date,Narration,Money Out,Money In\n" .
               "2026-12-01,Test transfer,500.00,\n";

        $r = $this->parser->parse($csv);

        $this->assertCount(1, $r['lines']);
        $this->assertSame('500.00', $r['lines'][0]['debit_amount']);
        $this->assertSame('Test transfer', $r['lines'][0]['description']);
    }

    public function test_handles_european_decimal_format(): void
    {
        $csv = "Date,Description,Debit,Credit\n" .
               "01-12-2026,Test,,\"1234,56\"\n";

        $r = $this->parser->parse($csv);

        $this->assertSame('1234.56', $r['lines'][0]['credit_amount']);
    }

    public function test_strips_utf8_bom(): void
    {
        $csv = "\xEF\xBB\xBFDate,Description,Debit,Credit\n01/12/2026,Test,,100.00\n";

        $r = $this->parser->parse($csv);
        $this->assertSame('100.00', $r['lines'][0]['credit_amount']);
    }

    public function test_throws_when_required_column_missing(): void
    {
        $csv = "Foo,Bar\n1,2\n";

        $this->expectException(\InvalidArgumentException::class);
        $this->parser->parse($csv);
    }

    public function test_throws_when_both_debit_and_credit_nonzero(): void
    {
        $csv = "Date,Description,Debit,Credit\n01/12/2026,Bad row,100.00,50.00\n";

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not a valid bank line');
        $this->parser->parse($csv);
    }

    public function test_skips_blank_rows(): void
    {
        $csv = "Date,Description,Debit,Credit\n" .
               "01/12/2026,Real row,,100.00\n" .
               ",,,\n" .
               "02/12/2026,Another,,200.00\n";

        $r = $this->parser->parse($csv);
        $this->assertCount(2, $r['lines']);
    }

    public function test_detect_recognises_standard_csv(): void
    {
        $csv = "Date,Description,Debit,Credit,Balance\n01/12/2026,Test,,100.00,100.00\n";
        $this->assertTrue($this->parser->detect($csv));
    }

    public function test_detect_rejects_non_bank_csv(): void
    {
        $csv = "name,email\nAlice,alice@example.com\n";
        $this->assertFalse($this->parser->detect($csv));
    }

    public function test_throws_on_too_few_rows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->parser->parse("Date,Description,Debit,Credit\n");
    }

    public function test_throws_on_unparseable_amount(): void
    {
        $csv = "Date,Description,Debit,Credit\n01/12/2026,Bad,abc,\n";
        $this->expectException(\InvalidArgumentException::class);
        $this->parser->parse($csv);
    }
}
