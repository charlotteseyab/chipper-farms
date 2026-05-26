<?php

namespace App\Services\Lifecycle;

use App\Domain\Events\Lifecycle\PigLifecycleStageChanged;
use App\Domain\Events\Support\SourceReference;
use App\Models\PigManagement;
use App\Models\User;
use App\Services\DomainEvents\DispatchDomainEvent;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Demotes a pig from breeding stock back to standard stock.
 *
 * Extracted from PigManagementController::demoteToStandard (was line 2018).
 *
 * Guards preserved exactly:
 *   - Rejects if pig is already standard stock.
 *   - Rejects if pig has active or completed matings (male or female).
 *
 * Emits PigLifecycleStageChanged on successful demotion.
 */
class DemotePigService
{
    public function __construct(
        protected DispatchDomainEvent $dispatcher,
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    public function execute(PigManagement $pig, User $actor): PromotionResult
    {
        if ($pig->stock_type === 'standard') {
            return PromotionResult::ineligible('This pig is already standard stock.');
        }

        $hasActiveMating = $pig->maleMatings()->whereIn('status', ['planned', 'completed'])->exists()
            || $pig->femaleMatings()->whereIn('status', ['planned', 'completed'])->exists();

        if ($hasActiveMating) {
            return PromotionResult::ineligible(
                "Cannot demote {$pig->code} to standard stock: pig has active or completed matings."
            );
        }

        $organizationId = $this->currentOrganization->requireId();
        $fromStockType  = (string) $pig->stock_type;

        DB::transaction(function () use ($pig, $actor, $organizationId, $fromStockType) {
            $pig->update(['stock_type' => 'standard']);

            $this->dispatcher->dispatch(new PigLifecycleStageChanged(
                source: new SourceReference(
                    module:         'lifecycle',
                    sourceType:     PigManagement::class,
                    sourceId:       (int) $pig->id,
                    organizationId: $organizationId,
                ),
                occurredAt: CarbonImmutable::now(),
                dispatchedByUserId: $actor->id,
                pigId:         (int) $pig->id,
                pigCode:       (string) ($pig->code ?? ''),
                fromStockType: $fromStockType,
                toStockType:   'standard',
                reason:        'Demoted to standard stock',
            ));
        });

        return PromotionResult::success(
            "Pig {$pig->code} demoted to standard stock successfully."
        );
    }
}