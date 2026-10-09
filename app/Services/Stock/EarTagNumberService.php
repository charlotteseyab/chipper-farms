<?php

namespace App\Services\Stock;

use App\Services\Stock\Tags\EarTagAllocator;
use App\Services\Stock\Tags\EarTagShadowService;
use App\Support\CurrentOrganization;

/** Compatibility entry point; all numeric suggestions use the tag register. */
class EarTagNumberService
{
    public function __construct(protected CurrentOrganization $currentOrganization) {}

    public function propose(int $quantity, ?int $organizationId = null): array
    {
        if ($quantity <= 0) {
            return [];
        }
        $organizationId ??= $this->currentOrganization->requireId();
        $color = app(EarTagShadowService::class)->legacyColor($organizationId);
        return array_column(app(EarTagAllocator::class)->propose($organizationId, $color, $quantity), 'tag_number');
    }
}
