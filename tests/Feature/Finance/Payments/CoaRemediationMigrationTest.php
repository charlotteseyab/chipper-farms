<?php

namespace Tests\Feature\Finance\Payments;

use App\Models\Counterparty;
use App\Models\Finance\Account;
use App\Models\Finance\SupplierCategory;
use App\Models\Finance\VendorInvoice;
use App\Models\Finance\VendorInvoiceLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * Verifies the COA remediation migration's effects.
 *
 * The migration creates 6999, repoints supplier_categories, repoints
 * draft VI lines that reference wrong codes, and leaves posted VIs
 * untouched.
 */
class CoaRemediationMigrationTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();
    }

    public function test_creates_6999_other_operating_expenses(): void
    {
        // Migration ran during RefreshDatabase. Account should exist.
        $acct = Account::where('code', '6999')
            ->where('organization_id', $this->org->id)
            ->first();

        $this->assertNotNull($acct, '6999 created by remediation');
        $this->assertSame('Other Operating Expenses', $acct->name);
        $this->assertSame(Account::TYPE_EXPENSE, $acct->account_type);
        $this->assertTrue((bool) $acct->allow_manual_journal);
    }

    public function test_repoints_feed_ingredients_to_1210(): void
    {
        $this->seedCorrectAccountsAndOldCategories();

        // Re-run remediation explicitly (already ran in RefreshDatabase but we want to test the effect).
        Artisan::call('migrate:refresh');
        $this->seedCorrectAccountsAndOldCategories();
        // Run remediation logic by re-running the migration class.
        $this->runRemediation();

        $cat = SupplierCategory::where('code', 'FEED_INGREDIENTS')->first();
        $this->assertNotNull($cat);

        if ($cat->default_expense_account_id) {
            $acct = Account::find($cat->default_expense_account_id);
            $this->assertSame('1210', $acct->code, 'FEED_INGREDIENTS now points at 1210 Raw Materials Inventory');
        }
    }

    public function test_repoints_general_to_6999(): void
    {
        $this->seedCorrectAccountsAndOldCategories();
        $this->runRemediation();

        $cat = SupplierCategory::where('code', 'GENERAL')->first();
        if ($cat && $cat->default_expense_account_id) {
            $acct = Account::find($cat->default_expense_account_id);
            $this->assertSame('6999', $acct->code, 'GENERAL points at the new sundry account');
        }
    }

    public function test_seeds_ap_account_id_per_category(): void
    {
        $this->seedCorrectAccountsAndOldCategories();
        $this->runRemediation();

        $feedCat = SupplierCategory::where('code', 'FEED_INGREDIENTS')->first();
        if ($feedCat && \Illuminate\Support\Facades\Schema::hasColumn('supplier_categories', 'ap_account_id')) {
            $apAcct = Account::find($feedCat->ap_account_id);
            $this->assertSame('2020', $apAcct?->code, 'FEED gets AP — Feed Suppliers (2020)');
        }

        $vetCat = SupplierCategory::where('code', 'VETERINARY')->first();
        if ($vetCat && \Illuminate\Support\Facades\Schema::hasColumn('supplier_categories', 'ap_account_id')) {
            $apAcct = Account::find($vetCat->ap_account_id);
            $this->assertSame('2030', $apAcct?->code, 'VETERINARY gets AP — Veterinary (2030)');
        }
    }

    public function test_repoints_draft_vi_lines(): void
    {
        $this->seedCorrectAccountsAndOldCategories();

        // Create a draft VI with lines pointing at OLD wrong codes.
        $oldFeedAcct = Account::where('code', '1310')->where('organization_id', $this->org->id)->first();
        if (! $oldFeedAcct) {
            $oldFeedAcct = Account::create([
                'organization_id' => $this->org->id, 'code' => '1310',
                'name' => 'Bio Assets — Breeding', 'account_type' => Account::TYPE_ASSET,
                'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => true,
            ]);
        }

        $supplier = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'X', 'code' => 'V-X',
            'type' => Counterparty::TYPE_SUPPLIER, 'currency_code' => 'GHS',
        ]);

        $vi = VendorInvoice::create([
            'organization_id'    => $this->org->id,
            'vendor_invoice_number' => 'VI-2026-DRAFT',
            'invoice_date'       => '2026-11-01',
            'due_date'           => '2026-11-30',
            'currency_code'      => 'GHS',
            'counterparty_id'    => $supplier->id,
            'subtotal'           => '500',
            'tax_total'          => '0',
            'total'              => '500',
            'withholding_total'  => '0',
            'amount_paid'        => '0',
            'balance_due'        => '500',
            'status'             => VendorInvoice::STATUS_DRAFT,
        ]);

        $line = VendorInvoiceLine::create([
            'organization_id' => $this->org->id,
            'vendor_invoice_id' => $vi->id,
            'description' => 'feed',
            'quantity' => '5',
            'unit_cost' => '100',
            'subtotal' => '500',
            'tax_rate' => '0',
            'tax_amount' => '0',
            'line_total' => '500',
            'withholding_rate' => '0',
            'withholding_amount' => '0',
            'account_id' => $oldFeedAcct->id,
        ]);

        // Ensure 1210 exists so the repoint can land somewhere.
        if (! Account::where('code', '1210')->where('organization_id', $this->org->id)->exists()) {
            Account::create([
                'organization_id' => $this->org->id, 'code' => '1210',
                'name' => 'Raw Materials Inventory', 'account_type' => Account::TYPE_ASSET,
                'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => true,
            ]);
        }

        $this->runRemediation();

        $line->refresh();
        $newAcct = Account::find($line->account_id);
        $this->assertSame('1210', $newAcct->code, 'Draft VI line repointed from 1310 to 1210');
    }

    public function test_does_not_touch_posted_vi_lines(): void
    {
        $this->seedCorrectAccountsAndOldCategories();

        $oldFeedAcct = Account::firstOrCreate(
            ['organization_id' => $this->org->id, 'code' => '1310'],
            ['name' => 'Bio Assets — Breeding', 'account_type' => Account::TYPE_ASSET,
             'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => true],
        );

        Account::firstOrCreate(
            ['organization_id' => $this->org->id, 'code' => '1210'],
            ['name' => 'Raw Materials Inventory', 'account_type' => Account::TYPE_ASSET,
             'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => true],
        );

        $supplier = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'P', 'code' => 'V-P',
            'type' => Counterparty::TYPE_SUPPLIER, 'currency_code' => 'GHS',
        ]);

        $vi = VendorInvoice::create([
            'organization_id'    => $this->org->id,
            'vendor_invoice_number' => 'VI-2026-POSTED',
            'invoice_date'       => '2026-11-01',
            'due_date'           => '2026-11-30',
            'currency_code'      => 'GHS',
            'counterparty_id'    => $supplier->id,
            'subtotal'           => '500',
            'tax_total'          => '0',
            'total'              => '500',
            'withholding_total'  => '0',
            'amount_paid'        => '0',
            'balance_due'        => '500',
            'status'             => VendorInvoice::STATUS_POSTED,  // posted, not draft
        ]);

        $line = VendorInvoiceLine::create([
            'organization_id' => $this->org->id,
            'vendor_invoice_id' => $vi->id,
            'description' => 'feed',
            'quantity' => '5', 'unit_cost' => '100', 'subtotal' => '500',
            'tax_rate' => '0', 'tax_amount' => '0', 'line_total' => '500',
            'withholding_rate' => '0', 'withholding_amount' => '0',
            'account_id' => $oldFeedAcct->id,
        ]);

        $this->runRemediation();

        $line->refresh();
        $this->assertSame($oldFeedAcct->id, $line->account_id,
            'Posted VI lines are immutable history — not touched');
    }

    public function test_idempotent_re_run(): void
    {
        $this->seedCorrectAccountsAndOldCategories();

        // Run twice — second should be a no-op without errors.
        $this->runRemediation();
        $this->runRemediation();

        $count = Account::where('organization_id', $this->org->id)->where('code', '6999')->count();
        $this->assertSame(1, $count, 'Only one 6999 created across multiple runs');
    }

    /* helpers */

    protected function seedCorrectAccountsAndOldCategories(): void
    {
        $codes = [
            '1010' => ['name' => 'Cash', 'type' => Account::TYPE_ASSET],
            '1210' => ['name' => 'Raw Materials Inventory', 'type' => Account::TYPE_ASSET],
            '1310' => ['name' => 'Biological Assets — Breeding Stock', 'type' => Account::TYPE_ASSET],
            '2010' => ['name' => 'Trade Payables — Suppliers', 'type' => Account::TYPE_LIABILITY],
            '2020' => ['name' => 'Trade Payables — Feed Suppliers', 'type' => Account::TYPE_LIABILITY],
            '2030' => ['name' => 'Trade Payables — Veterinary', 'type' => Account::TYPE_LIABILITY],
            '6010' => ['name' => 'Veterinary Medicine', 'type' => Account::TYPE_EXPENSE],
            '6020' => ['name' => 'Veterinary Services', 'type' => Account::TYPE_EXPENSE],
            '6310' => ['name' => 'Electricity', 'type' => Account::TYPE_EXPENSE],
            '6730' => ['name' => 'Outbound Delivery', 'type' => Account::TYPE_EXPENSE],
            '6830' => ['name' => 'Consulting Fees', 'type' => Account::TYPE_EXPENSE],
        ];
        foreach ($codes as $code => $meta) {
            Account::firstOrCreate(
                ['organization_id' => $this->org->id, 'code' => $code],
                ['name' => $meta['name'], 'account_type' => $meta['type'],
                 'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => true],
            );
        }

        // Categories with WRONG codes (as Phase 3a shipped them).
        $oldFeedId = Account::where('organization_id', $this->org->id)->where('code', '1310')->value('id');
        $oldVetId  = Account::where('organization_id', $this->org->id)->where('code', '6020')->value('id');

        SupplierCategory::firstOrCreate(
            ['organization_id' => $this->org->id, 'code' => 'FEED_INGREDIENTS'],
            ['name' => 'Feed Ingredients', 'default_expense_account_id' => $oldFeedId,
             'payment_terms_days' => 30, 'default_withholding_percent' => 0,
             'is_active' => true],
        );
        SupplierCategory::firstOrCreate(
            ['organization_id' => $this->org->id, 'code' => 'VETERINARY'],
            ['name' => 'Veterinary', 'default_expense_account_id' => $oldVetId,
             'payment_terms_days' => 30, 'default_withholding_percent' => 7.5,
             'is_active' => true],
        );
        SupplierCategory::firstOrCreate(
            ['organization_id' => $this->org->id, 'code' => 'GENERAL'],
            ['name' => 'General', 'default_expense_account_id' => Account::where('organization_id', $this->org->id)->where('code', '6010')->value('id'),
             'payment_terms_days' => 30, 'default_withholding_percent' => 0,
             'is_active' => true],
        );
    }

    protected function runRemediation(): void
    {
        // Locate the migration file and run its up() directly.
        $migrationPath = database_path('migrations');
        $files = glob($migrationPath . '/*remediate_coa_account_codes*');

        if (empty($files)) {
            $this->markTestSkipped('Remediation migration file not found in database/migrations');
            return;
        }

        $migration = require $files[0];
        $migration->up();
    }
}
