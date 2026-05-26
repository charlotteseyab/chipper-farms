<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0.8 — attachments polymorphic table.
 *
 * For future finance documents: supplier invoices, payment receipts, bank
 * statements, cheque images, customer PO scans. The prompt calls for
 * "attachment support where needed for future finance documents."
 *
 * Polymorphic: any source document can have attachments. Concrete usage in
 * Phase 1 will include journal entries, purchase requests, receipts, etc.
 * For now we seed the infrastructure only.
 *
 * File storage uses Laravel's filesystem abstraction. The `path` column
 * holds the path relative to the configured disk (NOT an absolute URL).
 * The controller responsible for serving attachments signs URLs at read time.
 *
 * Size and mime captured for integrity checks. Original filename preserved
 * so downloaded files look right to users (storage path uses hashed names).
 *
 * Safe and idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attachments')) {
            return;
        }

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('organization_id')->nullable();

            // Polymorphic source document reference.
            $table->string('attachable_type', 120);
            $table->unsignedBigInteger('attachable_id');

            $table->string('disk', 30)->default('local');     // 'local', 's3', 'public', etc.
            $table->string('path', 500);                       // Relative path within the disk
            $table->string('original_filename', 255);
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();

            // Optional categorisation: 'invoice', 'receipt', 'statement', 'contract',
            // 'other'. Free-string because categories evolve; validation via
            // application layer.
            $table->string('category', 50)->nullable();

            $table->string('title', 255)->nullable();
            $table->text('description')->nullable();

            $table->unsignedBigInteger('uploaded_by_user_id')->nullable();
            $table->foreign('uploaded_by_user_id')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['attachable_type', 'attachable_id'], 'attachments_attachable_idx');
            $table->index(['organization_id', 'category'], 'attachments_org_category_idx');
            $table->index('uploaded_by_user_id', 'attachments_uploader_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};