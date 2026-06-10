<?php

namespace App\Domain\Events\Lifecycle;

use App\Domain\Events\Contracts\DomainEvent;
use App\Domain\Events\Support\SourceReference;
use Carbon\CarbonImmutable;

class LitterWeaned extends DomainEvent
{
    public const TYPE = 'litter.weaned';

    public function __construct(
        SourceReference $source,
        CarbonImmutable $occurredAt,
        ?int $dispatchedByUserId,
        public readonly int $farrowingId,
        public readonly int $sowId,
        public readonly ?int $groupId,
        public readonly int $survivingPiglets,
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
            'surviving_piglets' => $this->survivingPiglets,
            'average_birth_weight_kg' => $this->averageBirthWeightKg,
        ];
    }
}
