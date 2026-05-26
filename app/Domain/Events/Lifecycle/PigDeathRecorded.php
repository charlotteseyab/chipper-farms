<?php

namespace App\Domain\Events\Lifecycle;

use App\Domain\Events\Contracts\DomainEvent;
use App\Domain\Events\Support\SourceReference;
use Carbon\CarbonImmutable;

/**
 * Emitted when a pig death is recorded. Phase 1 finance will translate this
 * into a biological asset write-off journal (dr: mortality loss, cr: biological
 * asset at accumulated cost).
 */
class PigDeathRecorded extends DomainEvent
{
    public const TYPE = 'pig_death.recorded';

    public function __construct(
        SourceReference $source,
        CarbonImmutable $occurredAt,
        ?int $dispatchedByUserId,
        public readonly int $pigId,
        public readonly string $pigCode,
        public readonly ?int $ageAtDeathDays,
        public readonly string $deathCause,
        public readonly ?float $finalWeight,
        public readonly ?string $disposalMethod,
    ) {
        parent::__construct($source, $occurredAt, $dispatchedByUserId);
    }

    public function payload(): array
    {
        return [
            'pig_id'            => $this->pigId,
            'pig_code'          => $this->pigCode,
            'age_at_death_days' => $this->ageAtDeathDays,
            'death_cause'       => $this->deathCause,
            'final_weight'      => $this->finalWeight,
            'disposal_method'   => $this->disposalMethod,
        ];
    }
}