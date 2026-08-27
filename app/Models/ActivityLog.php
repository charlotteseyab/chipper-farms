<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    use HasFactory, \App\Models\Concerns\BelongsToOrganization;

    protected $fillable = [
        'user_id', 'organization_id', 'name', 'action', 'module',
        'auditable_type', 'auditable_id', 'record_label', 'description',
        'old_values', 'new_values', 'ip_address', 'user_agent', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (ActivityLog $log): void {
            $user = auth()->user();
            $log->user_id ??= $user?->id;
            $log->organization_id ??= $user?->organization_id;
            $log->name ??= $user?->name;
            $log->ip_address ??= request()?->ip();
            $log->user_agent ??= request()?->userAgent();
            $log->old_values = static::redactSensitiveValues($log->old_values);
            $log->new_values = static::redactSensitiveValues($log->new_values);
            $log->metadata = static::redactSensitiveValues($log->metadata);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class)->withTrashed();
    }

    public function auditable()
    {
        return $this->morphTo();
    }

    private static function redactSensitiveValues(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $sensitive = ['password', 'token', 'secret', 'authorization', 'api_key', 'card_number', 'cvv', 'two_factor'];
        foreach ($value as $key => $item) {
            $normalized = strtolower((string) $key);
            $value[$key] = collect($sensitive)->contains(fn (string $term) => str_contains($normalized, $term))
                ? '[REDACTED]'
                : static::redactSensitiveValues($item);
        }

        return $value;
    }
}
