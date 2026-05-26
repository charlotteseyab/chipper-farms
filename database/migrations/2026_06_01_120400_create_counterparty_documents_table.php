<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2a — counterparty_documents.
 *
 * Compliance and reference documents attached to counterparties.
 * Distinct from the polymorphic Phase 0.8 attachments table because:
 *   - Documents here are TYPED (TIN cert, business reg, contract).
 *   - They have expiry dates, document numbers, issuing authorities.
 *   - They drive workflow: invoices to a customer with expired TIN
 *     can be flagged in Phase 2b/2d reports.
 *
 * The actual file lives in storage; this row records the metadata.
 * Phase 0.8's `attachments` table can co-exist for pure file-blob storage
 * without typed metadata — useful for ad-hoc PDFs.
 *
 * Idempotent and additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('counterparty_documents')) {
            return;
        }

        Schema::create('counterparty_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();

            $table->unsignedBigInteger('counterparty_id');
            $table->foreign('counterparty_id')
                  ->references('id')
                  ->on('counterparties')
                  ->cascadeOnDelete();

            $table->string('document_type', 50);
            // 'tin_certificate' | 'business_registration' | 'vat_certificate' |
            // 'contract' | 'credit_application' | 'kyc' | 'other'

            $table->string('document_number', 100)->nullable();
            $table->string('issuing_authority', 200)->nullable();
            // 'Ghana Revenue Authority', 'Registrar General', etc.

            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();

            // File storage. Path relative to the storage disk.
            $table->string('storage_disk', 50)->default('local');
            $table->string('storage_path', 500);
            $table->string('original_filename', 255)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();

            $table->text('notes')->nullable();

            $table->unsignedBigInteger('uploaded_by_user_id')->nullable();
            $table->foreign('uploaded_by_user_id')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['counterparty_id', 'document_type'], 'counterparty_docs_type_idx');
            $table->index(['organization_id', 'expiry_date'], 'counterparty_docs_expiry_idx');
            // Indexed for the "expiring soon" alerts in Phase 2d.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counterparty_documents');
    }
};
