<?php

namespace Tests\Feature\Finance\Phase7;

use App\Models\Counterparty;
use App\Models\Finance\Invoice;
use App\Models\Finance\Receipt;
use App\Models\Finance\ReceiptAllocation;
use App\Models\Organization;
use App\Models\PaymentMethod;
use App\Services\Organizations\OrganizationFeatureService;
use App\Support\Features\FeatureCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * Phase 7 — the one-button payment (blueprint §28).
 *
 * This is a money path, so the tests are written as the ways it goes wrong:
 * money posted twice, money posted against the wrong invoice, money taken by
 * someone without the permission, another farm's invoice paid from this farm's
 * screen, and a cash sale receipted a second time.
 */
class MoneyInTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected Counterparty $customer;
    protected PaymentMethod $cashMethod;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        // The permission seeders that own the grants this workspace needs.
        // Seeded rather than hand-granted so the test exercises the same
        // role→permission map production ships with.
        $this->seed(\Database\Seeders\FinancePermissionsSeeder::class);
        $this->seed(\Database\Seeders\Phase2bInvoicePermissionsSeeder::class);
        $this->seed(\Database\Seeders\Phase2cReceiptPermissionsSeeder::class);

        $this->user = $this->makeRoleUser('accountant');

        // ReceiptService needs a suspense account when a receipt arrives with
        // no allocation yet — same fixture the receipt tests use.
        if (! \App\Models\Finance\Account::where('organization_id', $this->org->id)->where('code', '1910')->exists()) {
            \App\Models\Finance\Account::create([
                'organization_id' => $this->org->id, 'code' => '1910',
                'name' => 'Suspense - Receipts', 'account_type' => \App\Models\Finance\Account::TYPE_ASSET,
                'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => false,
            ]);
        }

        $this->customer = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Ama Mensah', 'code' => 'CUST-AMA',
            'type' => Counterparty::TYPE_CUSTOMER, 'currency_code' => 'GHS',
        ]);

        $this->cashMethod = PaymentMethod::create([
            'organization_id' => $this->org->id,
            'code' => 'CASH', 'name' => 'Cash',
            'is_cash' => true, 'is_bank' => false, 'is_mobile_money' => false,
            'is_active' => true,
        ]);
    }

    /* ---------------------------------------------------------------- */
    /* The happy path, and what it must post                            */
    /* ---------------------------------------------------------------- */

    public function test_one_button_records_the_money_and_clears_the_invoice(): void
    {
        $invoice = $this->makePostedInvoice('500.00');

        $this->actingAs($this->user)
            ->post(route('finance.money-in.record', $invoice), [
                'amount'            => '500.00',
                'payment_method_id' => $this->cashMethod->id,
            ])
            ->assertRedirect(route('finance.money-in.index'));

        $invoice->refresh();

        $this->assertSame('500.00', (string) $invoice->amount_paid);
        $this->assertSame('0.00', (string) $invoice->balance_due);
        $this->assertNotNull($invoice->fully_paid_at);

        // Exactly one receipt, and it is posted to the GL by ReceiptService.
        $receipts = Receipt::where('organization_id', $this->org->id)->get();
        $this->assertCount(1, $receipts);
        $this->assertSame(Receipt::STATUS_POSTED, $receipts->first()->status);
        $this->assertNotNull($receipts->first()->journal_batch_id);

        // And exactly one allocation, against THIS invoice.
        $allocations = ReceiptAllocation::where('organization_id', $this->org->id)->get();
        $this->assertCount(1, $allocations);
        $this->assertSame($invoice->id, $allocations->first()->invoice_id);
    }

    /**
     * The governing test for this phase's money path. The button orchestrates
     * two existing services; if it ever posted a journal of its own the cash
     * account would move twice for one payment.
     */
    public function test_the_button_posts_exactly_one_journal_batch(): void
    {
        $invoice = $this->makePostedInvoice('500.00');

        $before = \App\Models\Finance\JournalBatch::count();

        $this->actingAs($this->user)
            ->post(route('finance.money-in.record', $invoice), [
                'amount'            => '500.00',
                'payment_method_id' => $this->cashMethod->id,
            ])
            ->assertRedirect();

        $this->assertSame(
            $before + 1,
            \App\Models\Finance\JournalBatch::count(),
            'One payment must post one batch. A second batch means this controller '
            . 'is posting its own journal alongside ReceiptService.',
        );
    }

    public function test_a_part_payment_leaves_the_rest_owed(): void
    {
        $invoice = $this->makePostedInvoice('500.00');

        $this->actingAs($this->user)
            ->post(route('finance.money-in.record', $invoice), [
                'amount'            => '200.00',
                'payment_method_id' => $this->cashMethod->id,
            ])
            ->assertRedirect();

        $invoice->refresh();

        $this->assertSame('200.00', (string) $invoice->amount_paid);
        $this->assertSame('300.00', (string) $invoice->balance_due);
        $this->assertNull($invoice->fully_paid_at);
    }

    /**
     * The receipt screen's auto-allocate is FIFO across a customer's open
     * invoices. Here the farmer pressed the button on a specific row, so the
     * money must land there — paying an older invoice instead would be the
     * software disagreeing with the person about what just happened.
     */
    public function test_the_money_lands_on_the_chosen_invoice_not_the_oldest(): void
    {
        $older  = $this->makePostedInvoice('500.00', invoiceDate: '2026-05-01');
        $chosen = $this->makePostedInvoice('500.00', invoiceDate: '2026-05-20');

        $this->actingAs($this->user)
            ->post(route('finance.money-in.record', $chosen), [
                'amount'            => '500.00',
                'payment_method_id' => $this->cashMethod->id,
            ])
            ->assertRedirect();

        $this->assertSame('0.00', (string) $chosen->fresh()->balance_due);
        $this->assertSame('500.00', (string) $older->fresh()->balance_due);
    }

    /* ---------------------------------------------------------------- */
    /* Refusals                                                         */
    /* ---------------------------------------------------------------- */

    public function test_more_than_is_owed_is_refused(): void
    {
        $invoice = $this->makePostedInvoice('500.00');

        $this->actingAs($this->user)
            ->post(route('finance.money-in.record', $invoice), [
                'amount'            => '600.00',
                'payment_method_id' => $this->cashMethod->id,
            ])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, Receipt::count());
        $this->assertSame('500.00', (string) $invoice->fresh()->balance_due);
    }

    public function test_a_cash_invoice_cannot_be_receipted_again(): void
    {
        $invoice = $this->makePostedInvoice('500.00', isCash: true);

        // balance_due is 0 on a cash invoice, so validation stops it first —
        // which is the right answer for the same reason.
        $this->actingAs($this->user)
            ->post(route('finance.money-in.record', $invoice), [
                'amount'            => '500.00',
                'payment_method_id' => $this->cashMethod->id,
            ])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, Receipt::count());
    }

    public function test_a_draft_invoice_cannot_take_a_payment(): void
    {
        $invoice = $this->makePostedInvoice('500.00');
        $invoice->update(['status' => Invoice::STATUS_DRAFT]);

        $this->actingAs($this->user)
            ->post(route('finance.money-in.record', $invoice), [
                'amount'            => '500.00',
                'payment_method_id' => $this->cashMethod->id,
            ])
            ->assertRedirect();

        $this->assertSame(0, Receipt::count(), 'A draft invoice has posted nothing to be paid against.');
    }

    /**
     * A shortcut to an action must not be a shortcut around the permission
     * that action needs. The receipt screen requires create_receipt; so does
     * this.
     */
    public function test_recording_requires_the_receipt_permission(): void
    {
        $invoice = $this->makePostedInvoice('500.00');

        $clerk = $this->makeUserWithPermissions(['view_finance']);

        $this->actingAs($clerk)
            ->post(route('finance.money-in.record', $invoice), [
                'amount'            => '500.00',
                'payment_method_id' => $this->cashMethod->id,
            ])
            ->assertForbidden();

        $this->assertSame(0, Receipt::count());
    }

    public function test_another_organisations_invoice_cannot_be_paid_from_this_farm(): void
    {
        $other = Organization::create(['slug' => 'other', 'name' => 'Other Farm']);

        $foreignCustomer = Counterparty::create([
            'organization_id' => $other->id,
            'name' => 'Their customer', 'code' => 'CUST-X',
            'type' => Counterparty::TYPE_CUSTOMER, 'currency_code' => 'GHS',
        ]);

        $foreignInvoice = Invoice::create([
            'organization_id'  => $other->id,
            'invoice_number'   => 'INV-OTHER-1',
            'invoice_date'     => '2026-05-15',
            'counterparty_id'  => $foreignCustomer->id,
            'currency_code'    => 'GHS',
            'exchange_rate'    => '1',
            'status'           => Invoice::STATUS_POSTED,
            'is_cash_invoice'  => false,
            'subtotal' => '500.00', 'discount_total' => '0', 'tax_total' => '0',
            'total' => '500.00', 'amount_paid' => '0', 'balance_due' => '500.00',
            'created_by_user_id' => $this->user->id,
            'posted_by_user_id'  => $this->user->id,
            'posted_at'          => now(),
        ]);

        $this->actingAs($this->user)
            ->post(route('finance.money-in.record', $foreignInvoice), [
                'amount'            => '500.00',
                'payment_method_id' => $this->cashMethod->id,
            ])
            ->assertNotFound();

        $this->assertSame(0, Receipt::count());
        $this->assertSame('500.00', (string) $foreignInvoice->fresh()->balance_due);
    }

    public function test_another_organisations_payment_method_is_refused(): void
    {
        $other = Organization::create(['slug' => 'other', 'name' => 'Other Farm']);

        $foreignMethod = PaymentMethod::create([
            'organization_id' => $other->id,
            'code' => 'CASH', 'name' => 'Their cash box',
            'is_cash' => true, 'is_bank' => false, 'is_mobile_money' => false,
            'is_active' => true,
        ]);

        $invoice = $this->makePostedInvoice('500.00');

        $this->actingAs($this->user)
            ->post(route('finance.money-in.record', $invoice), [
                'amount'            => '500.00',
                'payment_method_id' => $foreignMethod->id,
            ])
            ->assertSessionHasErrors('payment_method_id');

        $this->assertSame(0, Receipt::count());
    }

    /* ---------------------------------------------------------------- */
    /* The screen                                                       */
    /* ---------------------------------------------------------------- */

    public function test_the_workspace_shows_what_is_owed(): void
    {
        $this->makePostedInvoice('500.00');
        $this->makePostedInvoice('250.00');

        $this->actingAs($this->user)
            ->get(route('finance.money-in.index'))
            ->assertOk()
            ->assertSee('Ama Mensah')
            ->assertSee('750.00');
    }

    /**
     * Phase 7 gates its own surfaces. Before this phase, `finance.sales_money_in`
     * — like every other finance flag except three — was enforced nowhere, so a
     * farm that switched Sales & Money In off kept every one of its URLs.
     */
    public function test_the_workspace_closes_when_the_feature_is_off(): void
    {
        $this->app->make(OrganizationFeatureService::class)
            ->disable(FeatureCatalog::FINANCE_SALES_MONEY_IN, $this->org);

        $this->actingAs($this->user)
            ->get(route('finance.money-in.index'))
            ->assertNotFound();

        $this->actingAs($this->user)
            ->get(route('finance.invoices.index'))
            ->assertNotFound();
    }

    /* ---------------------------------------------------------------- */

    protected function makePostedInvoice(
        string $total,
        bool $isCash = false,
        string $invoiceDate = '2026-05-15',
    ): Invoice {
        static $counter = 0;

        return Invoice::create([
            'organization_id'  => $this->org->id,
            'invoice_number'   => 'INV-2026-' . str_pad((string) (++$counter), 6, '0', STR_PAD_LEFT),
            'invoice_date'     => $invoiceDate,
            'due_date'         => $invoiceDate,
            'counterparty_id'  => $this->customer->id,
            'currency_code'    => 'GHS',
            'exchange_rate'    => '1',
            'status'           => Invoice::STATUS_POSTED,
            'is_cash_invoice'  => $isCash,
            'subtotal' => $total, 'discount_total' => '0', 'tax_total' => '0',
            'total' => $total,
            'amount_paid' => '0', 'balance_due' => $isCash ? '0' : $total,
            'fully_paid_at' => $isCash ? now() : null,
            'created_by_user_id' => $this->user->id,
            'posted_by_user_id'  => $this->user->id,
            'posted_at'          => now(),
        ]);
    }

    protected function makeRoleUser(string $roleName): \App\Models\User
    {
        $role = \App\Models\Role::firstOrCreate(['name' => $roleName]);

        return \App\Models\User::forceCreate([
            'name'              => ucfirst($roleName),
            'email'             => $roleName . '-' . uniqid() . '@example.com',
            'password'          => bcrypt('secret'),
            'role'              => $roleName,
            'role_id'           => $role->id,
            'organization_id'   => $this->org->id,
            'active_status'     => true,
            'email_verified_at' => now(),
        ]);
    }

    /**
     * A user holding exactly the permissions named — never a broad role, whose
     * other grants would make a permission test vacuous.
     *
     * @param  list<string>  $permissions
     */
    protected function makeUserWithPermissions(array $permissions): \App\Models\User
    {
        $role = \App\Models\Role::firstOrCreate(['name' => 'phase7-limited']);
        $role->permissions()->detach();

        foreach ($permissions as $name) {
            $permission = \App\Models\Permission::firstOrCreate(
                ['name' => $name],
                ['name' => $name, 'description' => $name, 'module' => 'finance'],
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        return \App\Models\User::forceCreate([
            'name'              => 'Limited',
            'email'             => 'limited-' . uniqid() . '@example.com',
            'password'          => bcrypt('secret'),
            'role'              => $role->name,
            'role_id'           => $role->id,
            'organization_id'   => $this->org->id,
            'active_status'     => true,
            'email_verified_at' => now(),
        ]);
    }
}
