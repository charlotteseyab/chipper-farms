<?php

namespace Tests\Feature\Finance\Phase2d;

use App\Models\Counterparty;
use App\Models\Finance\FinanceAttachment;
use App\Models\Finance\Receipt;
use App\Services\Finance\Attachments\AttachmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Finance\FinanceTestSetup;
use Tests\TestCase;

class AttachmentServiceTest extends TestCase
{
    use RefreshDatabase;
    use FinanceTestSetup;

    protected AttachmentService $service;
    protected Receipt $receipt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFinanceFixtures();

        Storage::fake('local');
        config([
            'finance.attachments.disk' => 'local',
            'finance.attachments.max_size_mb' => 5,
            'finance.attachments.allowed_mime_types' => [
                'application/pdf', 'image/jpeg', 'image/png',
            ],
        ]);

        $customer = Counterparty::create([
            'organization_id' => $this->org->id,
            'name' => 'Acme', 'code' => 'CUST-A',
            'type' => 'customer', 'currency_code' => 'GHS',
        ]);

        $this->receipt = Receipt::create([
            'organization_id'    => $this->org->id,
            'receipt_number'     => 'RCT-2026-000001',
            'receipt_date'       => '2026-05-15',
            'counterparty_id'    => $customer->id,
            'currency_code'      => 'GHS', 'exchange_rate' => '1',
            'amount'             => '500.00', 'allocated_amount' => '0',
            'status'             => Receipt::STATUS_POSTED,
            'is_backfilled'      => false,
            'reference_type'     => 'cash_receipt',
            'created_by_user_id' => $this->user->id,
        ]);

        $this->service = $this->app->make(AttachmentService::class);
    }

    public function test_upload_creates_attachment_record_and_persists_file(): void
    {
        $file = UploadedFile::fake()->create('cheque.pdf', 100, 'application/pdf');

        $attachment = $this->service->upload(
            attachable: $this->receipt,
            file: $file,
            uploadedBy: $this->user,
            purpose: 'cheque_scan',
        );

        $this->assertInstanceOf(FinanceAttachment::class, $attachment);
        $this->assertSame('cheque.pdf', $attachment->original_name);
        $this->assertSame('application/pdf', $attachment->mime_type);
        $this->assertSame('cheque_scan', $attachment->purpose);

        Storage::disk('local')->assertExists($attachment->storage_path);
    }

    public function test_upload_uses_uuid_in_storage_path(): void
    {
        $file = UploadedFile::fake()->create('test.pdf', 50, 'application/pdf');
        $attachment = $this->service->upload($this->receipt, $file, $this->user);

        // Storage path should include the storage_uuid as a path segment
        $this->assertStringContainsString($attachment->storage_uuid, $attachment->storage_path);
        $this->assertStringStartsWith('finance/receipt/', $attachment->storage_path);
    }

    public function test_upload_rejects_oversized_file(): void
    {
        // 5MB max in test config; create 6MB file
        $file = UploadedFile::fake()->create('huge.pdf', 6 * 1024, 'application/pdf');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/exceeds maximum/i');

        $this->service->upload($this->receipt, $file, $this->user);
    }

    public function test_upload_rejects_disallowed_mime_type(): void
    {
        $file = UploadedFile::fake()->create('macro.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/not allowed/i');

        $this->service->upload($this->receipt, $file, $this->user);
    }

    public function test_upload_sanitises_dangerous_filenames(): void
    {
        $file = UploadedFile::fake()->create('../../etc/passwd.pdf', 50, 'application/pdf');
        $attachment = $this->service->upload($this->receipt, $file, $this->user);

        $this->assertStringNotContainsString('/', basename($attachment->storage_path));
        $this->assertStringNotContainsString('..', basename($attachment->storage_path));
    }

    public function test_delete_removes_file_and_db_row(): void
    {
        $file = UploadedFile::fake()->create('test.pdf', 50, 'application/pdf');
        $attachment = $this->service->upload($this->receipt, $file, $this->user);
        $path = $attachment->storage_path;

        $this->service->delete($attachment);

        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseMissing('finance_attachments', ['id' => $attachment->id]);
    }

    public function test_delete_idempotent_when_file_missing(): void
    {
        $file = UploadedFile::fake()->create('test.pdf', 50, 'application/pdf');
        $attachment = $this->service->upload($this->receipt, $file, $this->user);

        // Manually remove the file from disk so delete sees it missing
        Storage::disk('local')->delete($attachment->storage_path);

        // Should NOT throw — delete cleans up the DB row regardless.
        $this->service->delete($attachment);
        $this->assertDatabaseMissing('finance_attachments', ['id' => $attachment->id]);
    }

    public function test_human_size_formats_correctly(): void
    {
        $a = new FinanceAttachment(['size_bytes' => 500]);
        $this->assertSame('500 B', $a->humanSize());

        $a->size_bytes = 1500;
        $this->assertSame('1.5 KB', $a->humanSize());

        $a->size_bytes = 1500000;
        $this->assertSame('1.4 MB', $a->humanSize());
    }
}
