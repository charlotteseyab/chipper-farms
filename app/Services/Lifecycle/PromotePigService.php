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
 * Promotes a pig from standard stock to breeding stock.
 *
 * Extracted from PigManagementController::promoteToBreeding (was line 1977).
 *
 * Guards preserved exactly:
 *   - Rejects if pig is already breeding stock.
 *   - Rejects if pig does not meet minimum age (readiness->age_status).
 *   - Rejects if weight is "too_light".
 *
 * Guards return a PromotionResult with eligible=false and a reason message;
 * the controller translates that into a user-facing error. This keeps the
 * service free of HTTP concerns (back()->withErrors). Also makes it testable
 * without HTTP plumbing.
 *
 * Emits PigLifecycleStageChanged on successful promotion — Phase 1 finance
 * will translate stock-type transitions into biological asset category
 * reclassifications.
 */
class PromotePigService
{
    public function __construct(
        protected DispatchDomainEvent $dispatcher,
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    public function execute(PigManagement $pig, User $actor): PromotionResult
    {
        if ($pig->stock_type === 'breeding') {
            return PromotionResult::ineligible('This pig is already breeding stock.');
        }

        $readiness = $pig->breeding_readiness;

        if (! in_array($readiness['age_status'] ?? null, ['minimum_met', 'optimal'], true)) {
            $msg = $readiness['age_message'] ?? 'not old enough for breeding';
            return PromotionResult::ineligible(
                "Cannot promote {$pig->code} to breeding stock: {$msg}"
            );
        }

        $weightStatus = $readiness['factors']['weight']['status'] ?? null;
        if ($weightStatus === 'too_light') {
            $msg = $readiness['factors']['weight']['message'] ?? 'weight too low for breeding';
            return PromotionResult::ineligible(
                "Cannot promote {$pig->code} to breeding stock: {$msg}"
            );
        }

        $organizationId = $this->currentOrganization->requireId();
        $fromStockType  = (string) $pig->stock_type;

        DB::transaction(function () use ($pig, $actor, $organizationId, $fromStockType) {
            $pig->update(['stock_type' => 'breeding']);

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
                toStockType:   'breeding',
                reason:        'Promoted to breeding stock',
            ));
        });

        $message = $pig->latestWeight
            ? "Pig {$pig->code} promoted to breeding stock successfully!"
            : "Pig {$pig->code} promoted to breeding stock successfully! Consider adding weight data for better breeding management.";

        return PromotionResult::success($message);
    }
}