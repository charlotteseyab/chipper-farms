<?php

namespace App\Domain\Events\Lifecycle;

use App\Domain\Events\Contracts\DomainEvent;
use App\Domain\Events\Support\SourceReference;
use Carbon\CarbonImmutable;

class PigBirthRecorded extends DomainEvent
{
    public const TYPE = 'pig_birth.recorded';

    public function __construct(
        SourceReference $source,
        CarbonImmutable $occurredAt,
        ?int $dispatchedByUserId,
        public readonly int $farrowingId,
        public readonly int $sowId,
        public readonly ?int $groupId,
        public readonly int $liveBirths,
        public readonly int $stillbirths,
        public readonly ?float $averageBirthWeightKg,
    ) {
        parent::__construct($source, $occurredAt, $dispatchedByUserId);
    }

    public function payload(): array
    {
        return [
            'farrowing_id' => $this->farrowingId,
            'sow_id' => $this->sowId,
            'group_id' => $this->groupId,
            'live_births' => $this->liveBirths,
            'stillbirths' => $this->stillbirths,
            'average_birth_weight_kg' => $this->averageBirthWeightKg,
        ];
    }
}
