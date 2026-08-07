<?php

namespace Tests\Feature\Finance\Expenses;

use App\Models\Finance\Account;
use App\Models\Finance\FinanceAttachment;
use App\Models\Finance\QuickExpense;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\Expenses\RecordQuickExpenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * Receipt attachments on Quick Expense — Phase 6 (deferred from Phase 4).
 *
 * Phase 4 recorded expenses but could not hold their evidence. Wiring it up
 * means adding a parent type to `AttachmentController::PARENT_MAP`, which is a
 * change to a SECURITY-SENSITIVE SHARED COMPONENT — the same map that gates
 * payment, receipt, invoice and refund evidence. Two prior findings
 * (CF-P0-004 and CF-P1-019) were both defects in exactly this code.
 *
 * So the tests here are not "can a file be attached". They are the four ways
 * this goes wrong:
 *
 *   1. someone without the permission can attach;
 *   2. someone without the permission can download;
 *   3. someone in another organisation can download;
 *   4. the route accepts the new type but the resolver does not, or vice versa.
 */
class QuickExpenseAttachmentTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        Storage::fake('local');

        foreach ([
            ['6310', 'Electricity', 'expense', 'operating_expense'],
            ['1010', 'Cash on Hand', 'asset', 'current_asset'],
            ['2910', 'Suspense — Payments', 'liability', 'current_liability'],
        ] as [$code, $name, $type, $subtype]) {
            Account::firstOrCreate(
                ['organization_id' => $this->org->id, 'code' => $code],
                ['name' => $name, 'account_type' => $type, 'account_subtype' => $subtype,
                 'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => true],
            );
        }

        foreach (['record_expenses', 'view_finance'] as $name) {
            Permission::firstOrCreate(['name' => $name], ['description' => 'T', 'module' => 'finance']);
        }
    }

    protected function expense(): QuickExpense
    {
        return app(RecordQuickExpenseService::class)->record([
            'expense_date'       => '2026-06-15',
            'category_key'       => 'electricity',
            'description'        => 'ECG bill',
            'amount'             => '400.00',
            'settlement'         => 'paid_now',
            'payment_method_key' => 'cash',
        ], $this->user);
    }

    protected function userWith(array $permissions, ?Organization $org = null): User
    {
        $role = Role::create(['name' => 'r_' . uniqid()]);

        foreach ($permissions as $name) {
            $role->permissions()->attach(Permission::where('name', $name)->value('id'));
        }

        return User::forceCreate([
            'name' => 'U', 'email' => 'u_' . uniqid() . '@example.com',
            'password' => bcrypt('x'), 'role' => $role->name, 'role_id' => $role->id,
            'organization_id' => ($org ?? $this->org)->id, 'active_status' => true,
        ]);
    }

    protected function pdf(): UploadedFile
    {
        return UploadedFile::fake()->create('receipt.pdf', 40, 'application/pdf');
    }

    /* ------------------------------------------------------------------ */

    public function test_the_new_parent_type_is_reachable_through_the_route(): void
    {
        $expense = $this->expense();

        $this->actingAs($this->userWith(['record_expenses']))
            ->post(route('finance.attachments.store', [
                'type' => 'quick-expenses', 'id' => $expense->id,
            ]), ['file' => $this->pdf()])
            ->assertRedirect();

        $this->assertSame(
            1,
            $expense->attachments()->count(),
            'The route pattern derives from PARENT_MAP, so an entry there must make the type '
            . 'reachable. CF-P1-019 was three sources disagreeing about exactly this.',
        );
    }

    public function test_attaching_without_record_expenses_is_refused(): void
    {
        $expense = $this->expense();

        $this->actingAs($this->userWith(['view_finance']))
            ->post(route('finance.attachments.store', [
                'type' => 'quick-expenses', 'id' => $expense->id,
            ]), ['file' => $this->pdf()])
            ->assertForbidden();

        $this->assertSame(0, $expense->attachments()->count());
    }

    public function test_the_uploader_can_download_their_own_receipt_without_the_finance_module(): void
    {
        $expense = $this->expense();
        $officer = $this->userWith(['record_expenses']);

        $this->actingAs($officer)->post(route('finance.attachments.store', [
            'type' => 'quick-expenses', 'id' => $expense->id,
        ]), ['file' => $this->pdf()]);

        $attachment = $expense->attachments()->firstOrFail();

        // Switching identity mid-test: the upload request left a session for
        // the previous user, which would otherwise survive the next actingAs
        // and bounce the download to /login.
        $this->flushSession();

        $this->actingAs($officer)
            ->get(route('finance.attachments.download', $attachment))
            ->assertOk();
    }

    public function test_an_accountant_with_view_finance_can_download_it(): void
    {
        $expense = $this->expense();

        $this->actingAs($this->userWith(['record_expenses']))
            ->post(route('finance.attachments.store', [
                'type' => 'quick-expenses', 'id' => $expense->id,
            ]), ['file' => $this->pdf()]);

        $attachment = $expense->attachments()->firstOrFail();

        // Switching identity mid-test: the upload request left a session for
        // the previous user, which would otherwise survive the next actingAs
        // and bounce the download to /login.
        $this->flushSession();

        $this->actingAs($this->userWith(['view_finance']))
            ->get(route('finance.attachments.download', $attachment))
            ->assertOk();
    }

    public function test_a_user_with_neither_permission_cannot_download(): void
    {
        $expense = $this->expense();

        $this->actingAs($this->userWith(['record_expenses']))
            ->post(route('finance.attachments.store', [
                'type' => 'quick-expenses', 'id' => $expense->id,
            ]), ['file' => $this->pdf()]);

        $attachment = $expense->attachments()->firstOrFail();

        // Switching identity mid-test: the upload request left a session for
        // the previous user, which would otherwise survive the next actingAs
        // and bounce the download to /login.
        $this->flushSession();

        $this->actingAs($this->userWith([]))
            ->get(route('finance.attachments.download', $attachment))
            ->assertForbidden();
    }

    /**
     * The one that matters most. CF-P0-004 was a same-organisation user
     * streaming any attachment by id; the cross-tenant case must be closed for
     * the new parent type too, not just the old ones.
     */
    public function test_another_organisation_cannot_download_the_receipt(): void
    {
        $expense = $this->expense();

        $this->actingAs($this->userWith(['record_expenses']))
            ->post(route('finance.attachments.store', [
                'type' => 'quick-expenses', 'id' => $expense->id,
            ]), ['file' => $this->pdf()]);

        $attachment = $expense->attachments()->firstOrFail();

        // Switching identity mid-test: the upload request left a session for
        // the previous user, which would otherwise survive the next actingAs
        // and bounce the download to /login.
        $this->flushSession();

        $other  = Organization::create([
            'slug' => 'other-' . uniqid(), 'name' => 'Other Farm', 'is_active' => true,
        ]);
        $intruder = $this->userWith(['record_expenses', 'view_finance'], $other);

        $this->actingAs($intruder)
            ->get(route('finance.attachments.download', $attachment))
            ->assertForbidden();
    }

    public function test_another_organisation_cannot_attach_to_this_expense(): void
    {
        $expense = $this->expense();

        $other = Organization::create([
            'slug' => 'other-' . uniqid(), 'name' => 'Other Farm', 'is_active' => true,
        ]);

        $this->actingAs($this->userWith(['record_expenses'], $other))
            ->post(route('finance.attachments.store', [
                'type' => 'quick-expenses', 'id' => $expense->id,
            ]), ['file' => $this->pdf()])
            ->assertForbidden();

        $this->assertSame(0, $expense->attachments()->count());
    }

    public function test_an_expense_can_hold_more_than_one_document(): void
    {
        $expense = $this->expense();
        $officer = $this->userWith(['record_expenses']);

        foreach (['a.pdf', 'b.pdf'] as $name) {
            $this->actingAs($officer)->post(route('finance.attachments.store', [
                'type' => 'quick-expenses', 'id' => $expense->id,
            ]), ['file' => UploadedFile::fake()->create($name, 30, 'application/pdf')]);
        }

        $this->assertSame(2, $expense->attachments()->count());
    }

    public function test_the_attachment_is_linked_to_the_expense_and_the_organisation(): void
    {
        $expense = $this->expense();

        $this->actingAs($this->userWith(['record_expenses']))
            ->post(route('finance.attachments.store', [
                'type' => 'quick-expenses', 'id' => $expense->id,
            ]), ['file' => $this->pdf()]);

        $attachment = FinanceAttachment::firstOrFail();

        $this->assertSame(QuickExpense::class, $attachment->attachable_type);
        $this->assertSame($expense->id, (int) $attachment->attachable_id);
        $this->assertSame($this->org->id, (int) $attachment->organization_id);
    }
}
