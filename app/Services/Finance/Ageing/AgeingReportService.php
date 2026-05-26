<?php

namespace App\Services\Finance\Ageing;

use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;

/**
 * Backward-compatibility adapter.
 *
 * Phase 2d shipped this as the AR ageing service. Phase 3d refactors the
 * shared ageing logic into AbstractAgeingService and introduces the
 * symmetric ApAgeingService for vendor invoices.
 *
 * This class now delegates to ArAgeingService so any existing controller,
 * scheduled command, or test that injects AgeingReportService continues
 * to work without changes.
 *
 * NEW CODE should inject ArAgeingService directly. This adapter is
 * retained indefinitely for safety and is not deprecated — it carries
 * zero runtime cost.
 */
class AgeingReportService
{
    public function __construct(
        protected ArAgeingService $arService,
    ) {
    }

    public function build(?CarbonImmutable $asOf = null, ?int $counterpartyId = null): AgeingReport
    {
        return $this->arService->build($asOf, $counterpartyId);
    }
}