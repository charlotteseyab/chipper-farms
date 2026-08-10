<?php

namespace Tests\Feature\Storefront;

use App\Models\Finance\CreditNote;
use App\Models\Finance\JournalLine;
use App\Models\Finance\TaxProfile;
use App\Models\Finance\TaxProfileComponent;
use App\Models\Storefront\StorefrontSetting;
use App\Services\Finance\Tax\TaxCalculation;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Phase 13 — a return credits the tax that was charged (blueprint §44).
 *
 * §44's own note said it: "current return support exists; future implementation
 * should add tax snapshot reversal." `CreditNoteService` hard-coded
 * `tax_total => '0.00'`, which was honest while sales carried no tax.
 *
 * Phase 12b changed that. With storefront sales now charging VAT, a zero here
 * would strand it — the farm having collected tax on goods that came back and
 * never reversed it, and the customer credited the net while having paid the
 * gross.
 *
 * THE REVERSAL USES THE ORIGINAL REGIME
 * -------------------------------------
 * `TaxCalculation::proportion()` scales the stored components rather than
 * re-running today's profile. IMP-014 established why: a 2024 sale taxed under
 * Ghana's old cascading regime must be credited at that regime's figure in
 * 2026, or the difference is stranded permanently.
 */
class ReturnTaxReversalTest extends StorefrontTestCase
{
    use RefreshDatabase;

    protected function ghanaSnapshot(string $base): array
    {
        $profile = TaxProfile::create([
            'organization_id' => $this->org->id,
            'code'            => 'GH-2026',
            'name'            => 'Ghana standard 2026',
            'applies_to'      => 'sales',
            'treatment'       => TaxProfile::TREATMENT_STANDARD,
            'price_mode'      => TaxProfile::PRICE_EXCLUSIVE,
            'effective_from'  => '2020-01-01',
            'is_active'       => true,
        ]);

        foreach ([['VAT', '15'], ['NHIL', '2.5'], ['GETFUND', '2.5']] as $i => [$code, $rate]) {
            TaxProfileComponent::create([
                'tax_profile_id'    => $profile->id,
                'code'              => $code,
                'label'             => $code,
                'rate_type'         => TaxProfileComponent::RATE_PERCENT,
                'rate'              => $rate,
                'base_mode'         => TaxProfileComponent::BASE_NET,
                'calculation_order' => $i + 1,
            ]);
        }

        StorefrontSetting::updateOrCreate(
            ['organization_id' => $this->org->id],
            ['default_tax_profile_id' => $profile->id],
        );

        return $this->app->make(\App\Services\Finance\Tax\TaxCalculator::class)
            ->apply($profile->load('components'), $base)
            ->toSnapshot();
    }

    /**
     * A full return credits all of the tax.
     */
    public function test_a_full_return_reverses_the_whole_tax(): void
    {
        $snapshot = $this->ghanaSnapshot('100.00');

        $reversed = TaxCalculation::fromSnapshot($snapshot)->proportion('1')->totalTax;

        $this->assertSame('20.00', $reversed);
    }

    /**
     * Half the goods back means half the tax back — scaled from the original
     * components, not recomputed.
     */
    public function test_a_partial_return_reverses_a_proportional_share(): void
    {
        $snapshot = $this->ghanaSnapshot('100.00');

        $this->assertSame(
            '10.00',
            TaxCalculation::fromSnapshot($snapshot)->proportion('0.5')->totalTax,
        );
    }

    /**
     * The point of the snapshot. A sale taxed under an OLD regime must be
     * credited at that regime's figure, whatever the farm charges today.
     */
    public function test_the_reversal_uses_the_rate_that_was_charged_not_todays(): void
    {
        // A sale taxed under the old CASCADING regime, recorded as a snapshot:
        // NHIL 2.5 + GETFund 2.5 on 100 = 5.00, then VAT 15% on 105 = 15.75.
        // Total 20.75 — more than today's same-base 20.00 on the same goods,
        // which is the whole reason the snapshot exists.
        //
        // (The programme's "21.9%" figure for the old regime includes the
        // COVID-19 levy, which this profile does not carry. Corrected here
        // after the first version of this fixture asserted a total its own
        // components did not add up to.)
        $historic = [
            'profile_id'    => null,
            'profile_code'  => 'GH-PRE-2026',
            'treatment'     => 'standard',
            'price_mode'    => 'exclusive',
            'taxable_base'  => '100.00',
            'total_tax'     => '20.75',
            'gross_total'   => '120.75',
            'effective_on'  => '2024-06-01',
            'components'    => [
                ['code' => 'NHIL', 'label' => 'NHIL', 'rate' => '2.5', 'rate_type' => 'percent', 'base' => '100.00', 'amount' => '2.50'],
                ['code' => 'GETFUND', 'label' => 'GETFund', 'rate' => '2.5', 'rate_type' => 'percent', 'base' => '100.00', 'amount' => '2.50'],
                ['code' => 'VAT', 'label' => 'VAT', 'rate' => '15', 'rate_type' => 'percent', 'base' => '105.00', 'amount' => '15.75'],
            ],
        ];

        // Today's profile charges 20%. The reversal must not use it.
        $this->ghanaSnapshot('100.00');

        $this->assertSame(
            '20.75',
            TaxCalculation::fromSnapshot($historic)->proportion('1')->totalTax,
            'Crediting 20.00 against a 20.75 charge strands 0.75 of tax permanently.',
        );
    }

    /**
     * A sale from before Phase 12b, or by a farm that charges no tax, has no
     * snapshot. Reversing nothing is correct — there was nothing to give back.
     */
    public function test_a_sale_with_no_snapshot_reverses_no_tax(): void
    {
        $sale = new \App\Models\FeedSale(['tax_snapshot' => null]);

        $service = $this->app->make(\App\Services\Finance\CreditNotes\CreditNoteService::class);

        $method = new \ReflectionMethod($service, 'reversedTax');
        $method->setAccessible(true);

        $this->assertSame('0.00', $method->invoke($service, $sale, '1'));
    }

    /* ---------------------------------------------------------------- */
    /* The posting                                                      */
    /* ---------------------------------------------------------------- */

    /**
     * Revenue is reversed NET; the tax comes off the liability.
     *
     * Debiting the whole gross to revenue would reduce income by more than was
     * ever earned — the tax was never income, it was money held for the revenue
     * authority.
     */
    public function test_the_posting_splits_revenue_from_the_tax_reversal(): void
    {
        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);

        $revenue = \App\Models\Finance\Account::where('code', '4110')->firstOrFail();
        $offset  = \App\Models\Finance\Account::where('code', '1120')->firstOrFail();
        $vat     = $this->app->make(\App\Finance\Resolvers\FinanceAccountResolver::class)->byHint('VAT_CONTROL');

        $note = CreditNote::create([
            'organization_id'    => $this->org->id,
            'credit_note_number' => 'CN-TAX-1',
            'customer_id'        => null,
            'currency_code'      => 'GHS',
            'subtotal'           => '100.00',
            'tax_total'          => '20.00',
            'total'              => '120.00',
            'issue_date'         => '2026-06-01',
            'accounting_date'    => '2026-06-01',
            'status'             => CreditNote::STATUS_APPROVED,
            'idempotency_key'    => 'cn-tax-1',
            'offset_account_id'  => $offset->id,
        ]);

        $note->lines()->create([
            'organization_id'   => $this->org->id,
            'description'       => 'Returned feed',
            'quantity'          => '1',
            'unit_price'        => '100.00',
            'tax_amount'        => '20.00',
            'line_total'        => '120.00',
            'revenue_account_id' => $revenue->id,
        ]);

        $draft = $this->app->make(\App\Services\Finance\CreditNotes\CreditNoteService::class)
            ->buildPostingDraft($note->fresh('lines'));

        $lines = $draft->journals[0]->lines;

        $byAccount = [];
        foreach ($lines as $line) {
            $byAccount[$line->accountId] = [
                'debit'  => $line->debit ?? '0',
                'credit' => $line->credit ?? '0',
            ];
        }

        $this->assertSame(
            '100.00',
            bcadd((string) $byAccount[$revenue->id]['debit'], '0', 2),
            'Revenue must be reversed net of tax — the tax was never income.',
        );
        $this->assertSame(
            '20.00',
            bcadd((string) $byAccount[$vat->id]['debit'], '0', 2),
            'The tax reversal must come off the VAT liability.',
        );
        $this->assertSame(
            '120.00',
            bcadd((string) $byAccount[$offset->id]['credit'], '0', 2),
            'The customer is credited the gross — what they actually paid.',
        );
    }

    public function test_a_credit_note_with_no_tax_posts_no_vat_line(): void
    {
        $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);

        $revenue = \App\Models\Finance\Account::where('code', '4110')->firstOrFail();
        $offset  = \App\Models\Finance\Account::where('code', '1120')->firstOrFail();
        $vat     = $this->app->make(\App\Finance\Resolvers\FinanceAccountResolver::class)->byHint('VAT_CONTROL');

        $note = CreditNote::create([
            'organization_id'    => $this->org->id,
            'credit_note_number' => 'CN-NOTAX-1',
            'currency_code'      => 'GHS',
            'subtotal'           => '100.00',
            'tax_total'          => '0.00',
            'total'              => '100.00',
            'issue_date'         => '2026-06-01',
            'accounting_date'    => '2026-06-01',
            'status'             => CreditNote::STATUS_APPROVED,
            'idempotency_key'    => 'cn-notax-1',
            'offset_account_id'  => $offset->id,
        ]);

        $note->lines()->create([
            'organization_id'    => $this->org->id,
            'description'        => 'Returned feed',
            'quantity'           => '1',
            'unit_price'         => '100.00',
            'tax_amount'         => '0.00',
            'line_total'         => '100.00',
            'revenue_account_id' => $revenue->id,
        ]);

        $draft = $this->app->make(\App\Services\Finance\CreditNotes\CreditNoteService::class)
            ->buildPostingDraft($note->fresh('lines'));

        foreach ($draft->journals[0]->lines as $line) {
            $this->assertNotSame(
                $vat->id,
                $line->accountId,
                'A untaxed return must not touch the VAT account at all.',
            );
        }
    }
}
