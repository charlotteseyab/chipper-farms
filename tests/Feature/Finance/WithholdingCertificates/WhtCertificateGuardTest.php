<?php

namespace Tests\Feature\Finance\WithholdingCertificates;

use App\Models\Counterparty;
use App\Models\Finance\Account;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentAllocation;
use App\Models\Finance\SupplierCategory;
use App\Models\Finance\VendorInvoice;
use App\Models\Finance\WhtCertificate;
use App\Models\PaymentMethod;
use App\Services\Finance\WithholdingCertificates\WhtCertificateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

/**
 * D40 — Allocation reversal must be blocked while an issued WHT
 * certificate covers the allocation.
 *
 * The test focuses on the truth source for the guard:
 * `WhtCertificateService::issuedCertificatesCovering(allocation)`. The
 * PATCH_PAYMENT_ALLOCATION_SERVICE_3D patch wires this into
 * PaymentAllocationService::reverse() with a thrown exception when the
 * return is non-empty.
 *
 * In addition: end-to-end check that PaymentAllocationService::reverse()
 * throws when the dependency is wired AND a covering issued cert exists,
 * subject to the patch being applied (markTestSkipped if it isn't).
 */
class WhtCertificateGuardTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected Counterparty $supplier;
    protected WhtCertificateService $service;
    protected PaymentMethod $bankMethod;
    protected Account $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        Storage::fake('local');
        Config::set('finance.attachments.disk', 'local');
        Config::set('finance.statements.company_tin', 'C0009999999');
        Config::set('finance.statements.company_name', 'Chipper Farms Ltd');

        $expense = Account::firstOrCreate(
            ['organization_id' => $this->org->id, 'code' => '6010'],
            ['name' => 'Sundry Expense', 'account_type' => Account::TYPE_EXPENSE,
             'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => true],
        );
        $cat = SupplierCategory::create([
            'organization_id' => $this->org->id,
            'code' => 'GEN', 'name' => 'General',
            'default_expense_account_id' => $expense->id,
            'payment_terms_days' => 30, 'default_withholding_percent' => 5,
            'is_active' => true,
        ]);
        $this->supplier = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Acme Vendor', 'code' => 'VEND-A',
            'type' => Counterparty::TYPE_SUPPLIER, 'currency_code' => 'GHS',
            'supplier_category_id' => $cat->id, 'tax_id' => 'P0001234567',
        ]);

        $this->bankAccount = Account::firstOrCreate(
            ['organization_id' => $this->org->id, 'code' => '1020'],
            ['name' => 'Bank GCB', 'account_type' => Account::TYPE_ASSET,
             'currency_code' => 'GHS', 'is_active' => true, 'allow_manual_journal' => true],
        );
        $this->bankMethod = PaymentMethod::firstOrCreate(
            ['organization_id' => $this->org->id, 'code' => 'BANK'],
            ['name' => 'Bank Transfer', 'is_active' => true,
             'default_source_account_id' => $this->bankAccount->id],
        );

        $this->service = $this->app->make(WhtCertificateService::class);
    }

    public function test_active_alloc_with_no_cert_is_unlocked(): void
    {
        $alloc = $this->makeAllocationScenario();
        $covering = $this->service->issuedCertificatesCovering($alloc);
        $this->assertCount(0, $covering, 'No certs ever issued — alloc is reversible');
    }

    public function test_alloc_under_draft_cert_is_unlocked(): void
    {
        $alloc = $this->makeAllocationScenario();

        $cert = $this->service->generateDraft($this->supplier, 'quarter', 2026, 4, $this->user);

        $covering = $this->service->issuedCertificatesCovering($alloc->fresh());
        $this->assertCount(0, $covering, 'Draft certs do NOT lock allocations');
    }

    public function test_alloc_under_issued_cert_is_locked(): void
    {
        $alloc = $this->makeAllocationScenario();

        $cert = $this->service->generateDraft($this->supplier, 'quarter', 2026, 4, $this->user);
        $issued = $this->service->issue($cert, $this->user);

        $covering = $this->service->issuedCertificatesCovering($alloc->fresh());
        $this->assertCount(1, $covering, 'Issued cert MUST lock the contributing allocation');
        $this->assertSame($issued->id, $covering[0]->id);
        $this->assertSame(WhtCertificate::STATUS_ISSUED, $covering[0]->status);
    }

    public function test_alloc_unlocked_after_cert_voided(): void
    {
        $alloc = $this->makeAllocationScenario();

        $cert = $this->service->generateDraft($this->supplier, 'quarter', 2026, 4, $this->user);
        $issued = $this->service->issue($cert, $this->user);
        $this->assertCount(1, $this->service->issuedCertificatesCovering($alloc->fresh()));

        $this->service->void($issued, $this->user, 'Reissuing under correct period.');

        $covering = $this->service->issuedCertificatesCovering($alloc->fresh());
        $this->assertCount(0, $covering, 'Voided cert no longer locks');
    }

    public function test_payment_allocation_service_reverse_throws_when_locked(): void
    {
        $patServiceClass = \App\Services\Finance\Payments\PaymentAllocationService::class;
        if (! class_exists($patServiceClass)) {
            $this->markTestSkipped('PaymentAllocationService not present.');
        }

        // Inspect ctor to see if WhtCertificateService is injected.
        $ctor = (new \ReflectionClass($patServiceClass))->getConstructor();
        if (! $ctor) {
            $this->markTestSkipped('PaymentAllocationService has no ctor — patch not applied.');
        }
        $hasWht = false;
        foreach ($ctor->getParameters() as $p) {
            $type = $p->getType();
            if ($type && method_exists($type, 'getName')
                && $type->getName() === WhtCertificateService::class) {
                $hasWht = true; break;
            }
        }
        if (! $hasWht) {
            $this->markTestSkipped(
                'PATCH_PAYMENT_ALLOCATION_SERVICE_3D not applied. ' .
                'Apply it so reverse() consults the WHT guard, then re-run.'
            );
        }

        $alloc = $this->makeAllocationScenario();
        $cert = $this->service->generateDraft($this->supplier, 'quarter', 2026, 4, $this->user);
        $this->service->issue($cert, $this->user);

        // Cannot rely on resolving via DI without injecting all 5 ctor deps,
        // so pull from container.
        $patService = $this->app->make($patServiceClass);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/cert|WHT|certificate/i');
        $patService->reverse(
            $alloc->fresh(),
            $this->user,
            'try to reverse while locked',
        );
    }

    public function test_payment_allocation_service_reverse_succeeds_after_void(): void
    {
        $patServiceClass = \App\Services\Finance\Payments\PaymentAllocationService::class;
        if (! class_exists($patServiceClass)) {
            $this->markTestSkipped('PaymentAllocationService not present.');
        }
        $ctor = (new \ReflectionClass($patServiceClass))->getConstructor();
        $hasWht = false;
        if ($ctor) {
            foreach ($ctor->getParameters() as $p) {
                $type = $p->getType();
                if ($type && method_exists($type, 'getName')
                    && $type->getName() === WhtCertificateService::class) {
                    $hasWht = true; break;
                }
            }
        }
        if (! $hasWht) {
            $this->markTestSkipped('PATCH_PAYMENT_ALLOCATION_SERVICE_3D not applied.');
        }

        $alloc = $this->makeAllocationScenario();
        $cert = $this->service->generateDraft($this->supplier, 'quarter', 2026, 4, $this->user);
        $issued = $this->service->issue($cert, $this->user);
        $this->service->void($issued, $this->user, 'fixing period');

        $patService = $this->app->make($patServiceClass);
        $reversed = $patService->reverse(
            $alloc->fresh(),
            $this->user,
            'now we can',
        );

        $this->assertSame(PaymentAllocation::STATUS_REVERSED, $reversed->status);
    }

    /* helpers */

    protected function makeAllocationScenario(): PaymentAllocation
    {
        $vi = VendorInvoice::create([
            'organization_id' => $this->org->id,
            'vendor_invoice_number' => 'VI-2026-000001',
            'supplier_invoice_number' => 'SUPREF-001',
            'invoice_date' => '2026-10-01', 'due_date' => '2026-10-30',
            'counterparty_id' => $this->supplier->id,
            'currency_code' => 'GHS', 'exchange_rate' => '1',
            'status' => VendorInvoice::STATUS_POSTED,
            'subtotal' => '1000.00', 'tax_total' => '0', 'withholding_total' => '0',
            'total' => '1000.00', 'amount_paid' => '0', 'balance_due' => '1000.00',
        ]);
        $payment = Payment::create([
            'organization_id' => $this->org->id,
            'payment_number' => 'P-2026-000001',
            'payment_date' => '2026-10-15',
            'currency_code' => 'GHS', 'exchange_rate' => '1',
            'counterparty_id' => $this->supplier->id,
            'amount' => '500.00',
            'allocated_amount' => '500.00',
            'withholding_amount' => '25.00', 'net_amount' => '475.00',
            'payment_method_id' => $this->bankMethod->id,
            'source_account_id' => $this->bankAccount->id,
            'status' => Payment::STATUS_POSTED,
            'is_backfilled' => false,
            'created_by_user_id' => $this->user->id,
        ]);
        return PaymentAllocation::create([
            'organization_id' => $this->org->id,
            'payment_id' => $payment->id,
            'vendor_invoice_id' => $vi->id,
            'amount' => '500.00',
            'withholding_amount' => '25.00',
            'net_amount' => '475.00',
            'status' => PaymentAllocation::STATUS_ACTIVE,
            'allocated_by_user_id' => $this->user->id,
        ]);
    }
}
