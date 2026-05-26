<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class FinanceAttachment extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'attachable_type',
        'attachable_id',
        'storage_uuid',
        'disk',
        'storage_path',
        'original_name',
        'mime_type',
        'size_bytes',
        'purpose',
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
     * Generate a temporary signed URL for downloading. Falls back to the
     * raw URL on disks that don't support signed URLs (e.g. 'local' in dev).
     */
    public function downloadUrl(): string
    {
        $disk = Storage::disk($this->disk);
        $minutes = (int) config('finance.attachments.signed_url_minutes', 5);

        try {
            return $disk->temporaryUrl($this->storage_path, now()->addMinutes($minutes));
        } catch (\Throwable) {
            // Disks without signed-URL support fall back to a route-served stream.
            return route('finance.attachments.download', $this);
        }
    }

    /**
     * Stream content. Used by the fallback download route when the disk
     * doesn't support signed URLs.
     */
    public function streamContents(): mixed
    {
        return Storage::disk($this->disk)->readStream($this->storage_path);
    }

    public function exists(): bool
    {
        return Storage::disk($this->disk)->exists($this->storage_path);
    }

    public function humanSize(): string
    {
        $bytes = $this->size_bytes;
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
        return round($bytes / 1073741824, 1) . ' GB';
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }
}
