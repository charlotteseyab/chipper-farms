<?php

namespace App\Services\Health;

use App\Models\Attachment;
use App\Models\HealthFollowUp;
use App\Models\HealthRecord;
use App\Models\User;
use App\Models\VetExamination;
use App\Models\VetVisit;
use App\Services\Audit\AuditTrailService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Files on vet visits, examinations, cases and follow-ups, kept in the shared
 * attachments table. Adding and removing are audited against the record the
 * file belongs to. Removing marks the attachment removed and keeps the file,
 * because it may be the evidence behind a clinical note.
 */
class HealthAttachmentService
{
    /** URL segment => model. */
    public const PARENTS = [
        'visit' => VetVisit::class,
        'examination' => VetExamination::class,
        'case' => HealthRecord::class,
        'follow-up' => HealthFollowUp::class,
    ];

    public function __construct(protected AuditTrailService $audit)
    {
    }

    public static function typeOf(Model $parent): string
    {
        return array_search($parent::class, self::PARENTS, true) ?: throw new \InvalidArgumentException('Files cannot be attached to '.$parent::class.'.');
    }

    /** May this user add a file to this record? Visits and examinations need manage_vet_visits; cases and follow-ups manage_health_records. */
    public function canAdd(User $user, Model $parent): bool
    {
        $permission = in_array(self::typeOf($parent), ['visit', 'examination'], true) ? 'manage_vet_visits' : 'manage_health_records';

        return $user->hasPermission($permission) && empty($parent->voided_at);
    }

    public function add(User $actor, Model $parent, UploadedFile $file, ?string $title = null): Attachment
    {
        $this->validate($file);
        $type = self::typeOf($parent);
        $disk = config('health.attachments.disk', 'local');
        $name = Str::limit(preg_replace('/[^A-Za-z0-9._-]+/', '-', $file->getClientOriginalName()), 150, '');
        $path = $file->storeAs("health/{$type}/{$parent->organization_id}/".Str::uuid(), $name ?: 'file', $disk);
        if (! $path) {
            throw new \RuntimeException('The file could not be saved.');
        }

        return DB::transaction(function () use ($actor, $parent, $file, $title, $type, $disk, $path) {
            $attachment = $parent->attachments()->create([
                'organization_id' => $parent->organization_id, 'disk' => $disk, 'path' => $path,
                'original_filename' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(), 'category' => 'health_'.$type, 'title' => $title,
                'uploaded_by_user_id' => $actor->id,
            ]);
            $this->audit->log('health', 'health_attachment_added', [
                'user' => $actor, 'auditable' => $parent, 'organization_id' => $parent->organization_id,
                'description' => "File \"{$attachment->original_filename}\" attached to {$type} #{$parent->getKey()}.",
                'new_values' => ['attachment_id' => $attachment->id, 'file' => $attachment->original_filename, 'title' => $title],
            ]);

            return $attachment;
        });
    }

    public function remove(User $actor, Attachment $attachment, string $reason): void
    {
        DB::transaction(function () use ($actor, $attachment, $reason) {
            $parent = $attachment->attachable;
            $attachment->update(['description' => trim(($attachment->description ? $attachment->description."\n" : '').'Removed: '.$reason)]);
            $attachment->delete();
            $this->audit->log('health', 'health_attachment_removed', [
                'user' => $actor, 'auditable' => $parent ?? $attachment, 'organization_id' => $attachment->organization_id,
                'description' => "File \"{$attachment->original_filename}\" removed: {$reason}. The file is kept for the record.",
                'old_values' => ['attachment_id' => $attachment->id, 'file' => $attachment->original_filename],
            ]);
        });
    }

    public function stream(Attachment $attachment)
    {
        $disk = Storage::disk($attachment->disk ?: 'local');
        abort_unless($disk->exists($attachment->path), 404, 'The file is missing from storage.');

        return $disk->download($attachment->path, $attachment->original_filename, ['Content-Type' => $attachment->mime_type ?: 'application/octet-stream']);
    }

    private function validate(UploadedFile $file): void
    {
        $max = (int) config('health.attachments.max_size_mb', 10);
        if (! $file->isValid()) {
            throw ValidationException::withMessages(['file' => 'The file did not upload. Try again.']);
        }
        if ($file->getSize() > $max * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => "Files can be up to {$max} MB."]);
        }
        if (! in_array($file->getMimeType(), config('health.attachments.allowed_mime_types', []), true)) {
            throw ValidationException::withMessages(['file' => 'Attach a PDF or a photo (JPEG, PNG, WebP or HEIC).']);
        }
    }
}
