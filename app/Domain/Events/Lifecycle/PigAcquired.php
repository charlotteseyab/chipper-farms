<?php

namespace App\Domain\Events\Lifecycle;

use App\Domain\Events\Contracts\DomainEvent;
use App\Domain\Events\Support\SourceReference;
use Carbon\CarbonImmutable;

class PigAcquired extends DomainEvent
{
    public const TYPE = 'pig.acquired';

    public function __construct(
        SourceReference $source,
        CarbonImmutable $occurredAt,
        ?int $dispatchedByUserId,
        public readonly int $pigId,
        public readonly string $pigCode,
        public readonly ?int $groupId,
        public readonly string $stockType,
        public readonly ?string $gender,
        public readonly ?float $acquisitionCost,
        public readonly ?float $weightKg,
        public readonly ?string $acquisitionSource = null,
    ) {
        parent::__construct($source, $occurredAt, $dispatchedByUserId);
    }

    public function payload(): array
    {
        return [
            'pig_id' => $this->pigId,
            'pig_code' => $this->pigCode,
            'group_id' => $this->groupId,
            'stock_type' => $this->stockType,
            'gender' => $this->gender,
            'acquisition_cost' => $this->acquisitionCost,
            'weight_kg' => $this->weightKg,
            'acquisition_source' => $this->acquisitionSource,
        ];
    }
}
