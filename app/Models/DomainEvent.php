<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Record in the domain_events outbox.
 *
 * Reads only are safe via the normal Eloquent API. Writes should NEVER happen
 * directly through the model — always use the DispatchDomainEvent service
 * because it enforces (a) transactional coupling to the source write and
 * (b) idempotency via unique key generation.
 *
 * Status transitions:
 *   pending -> processed  (success path)
 *   pending -> failed     (consumer error; may retry)
 *   pending -> skipped    (deliberately not posted)
 *
 * Only the finance consumer layer (Phase 1) should transition status.
 */
class DomainEvent extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    public const STATUS_PENDING   = 'pending';
    /**
     * Claimed by a worker and in flight (CF-P1-015).
     *
     * Before this existed there was no way to express "someone is working on
     * this", so the claim query could not exclude in-flight rows and a crashed
     * worker left no trace to recover from.
     */
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PROCESSED = 'processed';
    /**
     * Dead-lettered: retried up to OutboxClaimer::MAX_ATTEMPTS and still
     * failing. Requires operator attention — it will NOT be retried
     * automatically.
     *
     * Note this is a narrowing of meaning. Previously ANY failure landed here
     * permanently, including transient ones, so the status could not be used to
     * decide whether anything was actually wrong. Transient failures now return
     * to 'pending' with next_retry_at set, and 'failed' means "give up".
     */
    public const STATUS_FAILED    = 'failed';
    public const STATUS_SKIPPED   = 'skipped';

    protected $fillable = [
        'organization_id',
        'event_type',
        'idempotency_key',
        'source_module',
        'source_type',
        'source_id',
        'payload',
        'payload_version',
        'status',
        'occurred_at',
        'processed_at',
        'processing_error',
        'processing_attempts',
        'dispatched_by_user_id',
        // Outbox claiming and retry (CF-P1-015).
        'claimed_at',
        'claimed_by',
        'next_retry_at',
        'last_error_at',
    ];

    protected function casts(): array
    {
        return [
            'payload_version'     => 'integer',
            'occurred_at'         => 'datetime',
            'processed_at'        => 'datetime',
            'processing_attempts' => 'integer',
            'claimed_at'          => 'datetime',
            'next_retry_at'       => 'datetime',
            'last_error_at'       => 'datetime',
        ];
    }

    protected function payload(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if ($value === null || $value === '') {
                    return [];
                }
                $decoded = json_decode($value, true);
                return is_array($decoded) ? $decoded : [];
            },
            set: function ($value) {
                $array = is_array($value) ? $value : (array) $value;
                return json_encode($array, JSON_PRESERVE_ZERO_FRACTION);
            }
        );
    }

    public function source(): MorphTo
    {
        return $this->morphTo(null, 'source_type', 'source_id');
    }

    public function dispatchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by_user_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeOfType(Builder $query, string $eventType): Builder
    {
        return $query->where('event_type', $eventType);
    }

    public function scopeForSource(Builder $query, string $sourceType, int $sourceId): Builder
    {
        return $query->where('source_type', $sourceType)
                     ->where('source_id', $sourceId);
    }

    /**
     * Mark this event as successfully processed. Used by Phase 1 consumers.
     */
    public function markProcessed(): void
    {
        $this->forceFill([
            'status'       => self::STATUS_PROCESSED,
            'processed_at' => now(),
            'processing_error' => null,
        ])->save();
    }

    /**
     * Mark this event as failed with an error message. Consumers may retry.
     */
    public function markFailed(string $error): void
    {
        $this->forceFill([
            'status'              => self::STATUS_FAILED,
            'processing_error'    => $error,
            'processing_attempts' => $this->processing_attempts + 1,
        ])->save();
    }

    /**
     * Mark this event as deliberately skipped (e.g. a void that is posted
     * elsewhere). Does not indicate failure.
     */
    public function markSkipped(string $reason): void
    {
        $this->forceFill([
            'status'           => self::STATUS_SKIPPED,
            'processed_at'     => now(),
            'processing_error' => $reason,
        ])->save();
    }
}