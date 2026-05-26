<?php

namespace App\Domain\Events\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Value object describing what a domain event points at.
 *
 * Every domain event carries exactly one SourceReference. It answers:
 *   - which module originated this event (routing/reporting)
 *   - which model class is the authoritative source record
 *   - which primary key identifies that record
 *   - which organization the event belongs to (tenant scope)
 *
 * The idempotency_key is computed from these fields plus the event type,
 * guaranteeing stable uniqueness across retries and replays.
 */
final class SourceReference
{
    public function __construct(
        public readonly string $module,
        public readonly string $sourceType,
        public readonly int $sourceId,
        public readonly int $organizationId,
    ) {
    }

    /**
     * Build a SourceReference from an Eloquent model. The model class name
     * is used as sourceType; organization_id is read from the model if it
     * has one, otherwise it must be passed explicitly.
     */
    public static function fromModel(
        Model $model,
        string $module,
        ?int $organizationId = null
    ): self {
        $resolvedOrgId = $organizationId
            ?? ($model->organization_id ?? null);

        if ($resolvedOrgId === null) {
            throw new \InvalidArgumentException(
                'organization_id could not be resolved from the model "'
                . $model::class
                . '". Pass it explicitly or ensure the model has the column.'
            );
        }

        return new self(
            module:         $module,
            sourceType:     $model::class,
            sourceId:       (int) $model->getKey(),
            organizationId: (int) $resolvedOrgId,
        );
    }

    /**
     * Compute the idempotency key for this source + event type.
     *
     * Format: {event_type}:{short_type}:{source_id}[:suffix]
     *   short_type is the class basename, not the FQCN, to keep keys short
     *   suffix allows multiple events from the same source (e.g. a sale
     *     record dispatches both pig_sale.recorded AND ar_invoice.raisable —
     *     same source, different events, different keys).
     *
     * Key length capped at 200 per the migration schema.
     */
    public function idempotencyKeyFor(string $eventType, ?string $suffix = null): string
    {
        $shortType = class_basename($this->sourceType);
        $base = "{$eventType}:{$shortType}:{$this->sourceId}";

        if ($suffix !== null && $suffix !== '') {
            $base .= ':' . $suffix;
        }

        if (strlen($base) > 200) {
            // Extremely unlikely, but protect the schema contract anyway.
            $base = substr($base, 0, 160) . ':' . substr(hash('sha256', $base), 0, 32);
        }

        return $base;
    }

    public function toArray(): array
    {
        return [
            'module'         => $this->module,
            'source_type'    => $this->sourceType,
            'source_id'      => $this->sourceId,
            'organization_id'=> $this->organizationId,
        ];
    }
}