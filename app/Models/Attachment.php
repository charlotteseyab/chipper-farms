<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    use HasFactory, \App\Models\Concerns\BelongsToOrganization;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'attachable_type',
        'attachable_id',
        'disk',
        'path',
        'original_filename',
        'mime_type',
        'size_bytes',
        'category',
        'title',
        'description',
        'uploaded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    /**
     * Convenience: does this attachment's file still exist on disk?
     * Useful for integrity reports.
     */
    public function exists(): bool
    {
        return Storage::disk((string) ($this->disk ?: 'local'))->exists((string) $this->path);
    }

    /**
     * Generate a temporary signed URL for the file.
     * Only works on disks that support it (s3, gcs). For local, returns null.
     */
    public function temporaryUrl(\DateTimeInterface $expiresAt): ?string
    {
        $disk = Storage::disk((string) ($this->disk ?: 'local'));
        try {
            return method_exists($disk, 'temporaryUrl')
                ? $disk->temporaryUrl((string) $this->path, $expiresAt)
                : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
