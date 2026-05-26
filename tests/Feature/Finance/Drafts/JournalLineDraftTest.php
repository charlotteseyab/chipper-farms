<?php

namespace Tests\Feature\Finance\Drafts;

use App\Finance\Drafts\JournalLineDraft;
use Tests\TestCase;

class JournalLineDraftTest extends TestCase
{
    public function test_debit_factory_builds_debit_line(): void
    {
        $line = JournalLineDraft::debit(1, '100.00');
        $this->assertTrue($line->isDebit());
        $this->assertFalse($line->isCredit());
        $this->assertSame('100.00', $line->debit);
        $this->assertSame('0', $line->credit);
    }

    public function test_credit_factory_builds_credit_line(): void
    {
        $line = JournalLineDraft::credit(1, '100.00');
        $this->assertTrue($line->isCredit());
        $this->assertFalse($line->isDebit());
        $this->assertSame('100.00', $line->credit);
        $this->assertSame('0', $line->debit);
    }

    public function test_zero_zero_line_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new JournalLineDraft(accountId: 1, debit: '0', credit: '0');
    }

    public function test_both_positive_line_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new JournalLineDraft(accountId: 1, debit: '50', credit: '50');
    }

    public function test_negative_debit_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new JournalLineDraft(accountId: 1, debit: '-10', credit: '0');
    }

    public function test_signed_amount_returns_positive_for_debit(): void
    {
        $line = JournalLineDraft::debit(1, '100.50');
        $this->assertSame('100.50', $line->signedAmount());
    }

    public function test_signed_amount_returns_negative_for_credit(): void
    {
        $line = JournalLineDraft::credit(1, '75.25');
        $this->assertSame('-75.25', $line->signedAmount());
    }

    public function test_dimensions_passed_through(): void
    {
        $line = JournalLineDraft::debit(1, '100', null, [
            'cost_centre_id'   => 5,
            'sales_channel_id' => 3,
            'counterparty_id'  => 9,
        ]);

        $this->assertSame(5, $line->costCentreId);
        $this->assertSame(3, $line->salesChannelId);
        $this->assertSame(9, $line->counterpartyId);
    }

    public function test_to_persisted_array_includes_all_fields(): void
    {
        $line = JournalLineDraft::debit(7, '100.00', 'test desc', [
            'cost_centre_id' => 2,
        ]);

        $arr = $line->toPersistedArray(
            journalId:       50,
            lineNumber:      1,
            organizationId:  10,
            defaultCurrency: 'GHS',
            defaultRate:     '1',
        );

        $this->assertSame(50, $arr['journal_id']);
        $this->assertSame(1, $arr['line_number']);
        $this->assertSame(10, $arr['organization_id']);
        $this->assertSame(7, $arr['account_id']);
        $this->assertSame('100.00', $arr['debit']);
        $this->assertSame('0', $arr['credit']);
        $this->assertSame('100.00', $arr['debit_tc']);
        $this->assertSame('GHS', $arr['currency_code']);
        $this->assertSame('1', $arr['exchange_rate']);
        $this->assertSame('test desc', $arr['description']);
        $this->assertSame(2, $arr['cost_centre_id']);
    }
}
