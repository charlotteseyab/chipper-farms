<?php

namespace App\Services\Finance\Attachments;

use App\Models\Finance\FinanceAttachment;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Handles file uploads/downloads/deletes for finance attachments.
 *
 * Validates:
 *   - file size <= configured max (default 20MB)
 *   - MIME type in configured allowlist (PDF + common image formats)
 *   - parent entity belongs to the current organisation (caller responsibility)
 *
 * Storage path:
 *   finance/{type_slug}/{org_id}/{uuid}/{sanitised_original_name}
 *
 *   The uuid prevents enumeration; the org_id segment supports per-org disk
 *   scoping if multi-tenant disks are configured later.
 *
 * Audit:
 *   uploaded_by_user_id captured on every upload. Deletions are hard
 *   (file removed from disk) — finance attachments are operational
 *   evidence, and the parent entity (receipt/invoice/refund) is the
 *   audit anchor. If retention regulations require keeping deleted
 *   attachment metadata, Phase 6+ adds soft-delete.
 */
class AttachmentService
{
    public function __construct(
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    /**
     * Upload a file as an attachment to an attachable entity.
     *
     * @param  Model         $attachable  Receipt | Invoice | Refund (anything with an id)
     * @param  UploadedFile  $file
     * @param  User          $uploadedBy
     * @param  string|null   $purpose     Free-form tag (e.g. 'cheque_scan')
     * @param  string|null   $description User-provided notes
     */
    public function upload(
        Model $attachable,
        UploadedFile $file,
        User $uploadedBy,
        ?string $purpose = null,
        ?string $description = null,
    ): FinanceAttachment {
        $this->validate($file);

        $disk = config('finance.attachments.disk', 'local');
        $orgId = $this->currentOrganization->resolve()?->id ?? 'shared';
        $typeSlug = $this->slugForAttachable($attachable);
        $storageUuid = (string) Str::uuid();

        $sanitisedName = $this->sanitiseFilename($file->getClientOriginalName());

        $relativePath = sprintf(
            'finance/%s/%s/%s/%s',
            $typeSlug,
            $orgId,
            $storageUuid,
            $sanitisedName,
        );

        // Stream upload to disk.
        $diskInstance = Storage::disk($disk);
        $stored = $diskInstance->putFileAs(
            dirname($relativePath),
            $file,
            basename($relativePath),
        );

        if (! $stored) {
            throw new \RuntimeException("Failed to upload attachment to disk '{$disk}'");
        }

        return FinanceAttachment::create([
            'organization_id'      => $this->currentOrganization->resolve()?->id,
            'attachable_type'      => $attachable->getMorphClass(),
            'attachable_id'        => $attachable->getKey(),
            'storage_uuid'         => $storageUuid,
            'disk'                 => $disk,
            'storage_path'         => $relativePath,
            'original_name'        => $file->getClientOriginalName(),
            'mime_type'            => $file->getMimeType() ?: 'application/octet-stream',
            'size_bytes'           => $file->getSize() ?: 0,
            'purpose'              => $purpose,
            'description'          => $description,
            'uploaded_by_user_id'  => $uploadedBy->id,
        ]);
    }

    /**
     * Delete an attachment — removes the file from disk AND the row from the table.
     *
     * Idempotent: missing files don't throw (the file may have been
     * removed manually from S3 by an admin). The DB row is always removed.
     */
    public function delete(FinanceAttachment $attachment): void
    {
        try {
            Storage::disk($attachment->disk)->delete($attachment->storage_path);
        } catch (\Throwable $e) {
            // Log but proceed — DB cleanup is the priority.
            Log::warning("Failed to delete attachment file {$attachment->storage_path} from disk {$attachment->disk}: " . $e->getMessage());
        }

        $attachment->delete();
    }

    /* internals */

    protected function validate(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new \DomainException("Uploaded file is not valid: " . $file->getErrorMessage());
        }

        $maxBytes = ((int) config('finance.attachments.max_size_mb', 20)) * 1024 * 1024;
        if ($file->getSize() > $maxBytes) {
            $maxMb = config('finance.attachments.max_size_mb', 20);
            $actualMb = round($file->getSize() / 1024 / 1024, 1);
            throw new \DomainException("File size {$actualMb}MB exceeds maximum {$maxMb}MB");
        }

        $allowed = config('finance.attachments.allowed_mime_types', []);
        $mime = $file->getMimeType();
        if (! in_array($mime, $allowed, true)) {
            throw new \DomainException(
                "File type '{$mime}' not allowed. Allowed: " . implode(', ', $allowed)
            );
        }
    }

    protected function slugForAttachable(Model $attachable): string
    {
        $morphClass = $attachable->getMorphClass();

        // Use just the class basename for the path. Easier to navigate manually.
        return Str::slug(class_basename($morphClass));
    }

    /**
     * Sanitise filename: keep alphanumeric + dot + dash + underscore.
     * Replace runs of unsafe characters with single underscore.
     * Preserve extension.
     */
    protected function sanitiseFilename(string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9.\-_]+/', '_', $name);
        $name = trim($name, '_');
        if ($name === '') {
            $name = 'file';
        }
        if (strlen($name) > 200) {
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $base = pathinfo($name, PATHINFO_FILENAME);
            $name = substr($base, 0, 190) . ($ext ? '.' . $ext : '');
        }
        return $name;
    }
}
