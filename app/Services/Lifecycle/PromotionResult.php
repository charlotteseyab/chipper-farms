<?php

namespace App\Services\Lifecycle;

/**
 * Value object returned by PromotePigService and DemotePigService.
 *
 * Keeps the service free of HTTP concerns. Controllers translate the
 * eligible=false case into a redirect-with-error and the success case
 * into a redirect-with-success.
 */
final class PromotionResult
{
    private function __construct(
        public readonly bool $eligible,
        public readonly string $message,
    ) {
    }

    public static function success(string $message): self
    {
        return new self(true, $message);
    }

    public static function ineligible(string $reason): self
    {
        return new self(false, $reason);
    }
}