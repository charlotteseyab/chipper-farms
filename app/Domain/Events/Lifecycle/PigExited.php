<?php

namespace App\Domain\Events\Lifecycle;

use App\Domain\Events\Contracts\DomainEvent;
use App\Domain\Events\Support\SourceReference;
use Carbon\CarbonImmutable;

/**
 * A pig left the herd by transfer out or cull. No finance subscriber yet:
 * writing off an animal's carrying value without a sale needs an approved
 * account mapping first.
 */
class PigExited extends DomainEvent
{
    public const TYPE = 'pig.exited';

    public function __construct(
        SourceReference $source,
        CarbonImmutable $occurredAt,
        ?int $dispatchedByUserId,
        public readonly int $pigId,
        public readonly string $pigCode,
        public readonly string $exitType,
        public readonly string $reason,
        public readonly ?string $disposal,
        public readonly ?float $finalWeight,
    ) {
        parent::__construct($source, $occurredAt, $dispatchedByUserId);
    }

    public function payload(): array
    {
        return [
            'pig_id' => $this->pigId, 'pig_code' => $this->pigCode, 'exit_type' => $this->exitType,
            'reason' => $this->reason, 'disposal' => $this->disposal, 'final_weight' => $this->finalWeight,
        ];
    }
}
