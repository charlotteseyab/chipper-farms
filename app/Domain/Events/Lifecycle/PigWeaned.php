<?php

namespace App\Domain\Events\Lifecycle;

use App\Domain\Events\Contracts\DomainEvent;
use App\Domain\Events\Support\SourceReference;
use Carbon\CarbonImmutable;

class PigWeaned extends DomainEvent
{
    public const TYPE = 'pig.weaned';

    public function __construct(
        SourceReference $source,
        CarbonImmutable $occurredAt,
        ?int $dispatchedByUserId,
        public readonly int $pigId,
        public readonly ?int $groupId,
        public readonly ?float $weaningWeightKg,
    ) {
        parent::__construct($source, $occurredAt, $dispatchedByUserId);
    }

    public function payload(): array
    {
        return [
            'pig_id' => $this->pigId,
            'group_id' => $this->groupId,
            'weaning_weight_kg' => $this->weaningWeightKg,
        ];
    }
}
