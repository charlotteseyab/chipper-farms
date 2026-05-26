<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2d — finance attachments.
 *
 * Polymorphic attachments table for finance documents: scanned cheques,
 * MoMo payment screenshots, signed delivery notes, customer correspondence,
 * supplier invoices, etc.
 *
 * Polymorphic linkage:
 *   attachable_type + attachable_id pair points at the parent. Currently
 *   used for Receipt, Invoice, Refund. Scoped to finance entities only
 *   (no general-purpose file uploader tied to operations).
 *
 * Storage:
 *   Files live on the configured filesystem disk (default: 's3' in production,
 *   'local' in dev — see config/finance.php). The 'storage_path' column stores
 *   the disk-relative path. Downloads serve via temporary signed URLs (5 min
 *   expiry). Path layout:
 *       finance/{type}/{org_id}/{uuid}/{sanitised_original_name}
 *   The UUID prevents enumeration attacks; the org_id segment enables disk-level
 *   isolation if multi-tenant disks are configured later.
 *
 * Audit:
 *   uploaded_by_user_id captures who uploaded. There's no soft delete — files
 *   removed via the UI are hard-deleted from disk to free storage. The audit
 *   record on the parent entity (Receipt/Invoice/Refund show pages) shows
 *   "X attachments" historically; if a deleted attachment matters for audit,
 *   it should never have been uploaded in the first place. (Phase 6+ may add
 *   a soft-delete + retention policy if regulators require it.)
 *
 * Limits:
 *   max_file_size_mb is configurable (default 20MB). MIME-type allowlist:
 *   PDF, JPG, PNG, WEBP, HEIC. Office formats are NOT allowed — they're
 *   often used for malware delivery and finance has no use case for them
 *   on a receipt/invoice attachment.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('finance_attachments')) {
            return;
        }

        Schema::create('finance_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();

            // Polymorphic parent
            $table->string('attachable_type');
            $table->unsignedBigInteger('attachable_id');

            $table->uuid('storage_uuid');
            // Stable identifier in the storage path. Survives renames of
            // the original filename.

            $table->string('disk', 30);
            // 's3', 'local', etc. Captured at upload so subsequent retrieval
            // works even if config default changes.

            $table->string('storage_path', 500);
            // Disk-relative path. e.g. 'finance/receipts/12/abc-uuid/cheque.pdf'

            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');

            $table->string('purpose', 50)->nullable();
            // Free-form tag: 'cheque_scan', 'momo_screenshot', 'signed_delivery',
            // 'supplier_invoice', 'correspondence', etc. Not enforced — operations
            // uses it for filtering. Per-org conventions are fine.

            $table->text('description')->nullable();

            $table->unsignedBigInteger('uploaded_by_user_id')->nullable();
            $table->foreign('uploaded_by_user_id')
                  ->references('id')->on('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['attachable_type', 'attachable_id'], 'finance_attachments_attachable_idx');
            $table->index(['organization_id', 'created_at'], 'finance_attachments_org_recent_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_attachments');
    }
};
