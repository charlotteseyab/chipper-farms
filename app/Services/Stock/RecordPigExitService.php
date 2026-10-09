<?php

namespace App\Services\Stock;

use App\Domain\Events\Lifecycle\PigExited;
use App\Domain\Events\Support\SourceReference;
use App\Models\EarTag;
use App\Models\PigExit;
use App\Models\PigManagement;
use App\Models\User;
use App\Services\Audit\AuditTrailService;
use App\Services\DomainEvents\DispatchDomainEvent;
use App\Services\Health\Exceptions\PigUnderWithdrawalException;
use App\Services\Health\WithdrawalStatusService;
use App\Services\Stock\Tags\EarTagAssignmentService;
use App\Services\Stock\Tags\EarTagShadowService;
use App\Storefront\Services\StorefrontPigPoolService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Records a pig leaving the herd by transfer out or cull — the third exit
 * beside a sale and a death. Like those, it releases the ear tag (so the
 * number can be reused), soft-deletes the pig and keeps every history record
 * on it. A cull slaughtered for food is refused during medicine withdrawal.
 */
class RecordPigExitService
{
    public function __construct(
        protected CurrentOrganization $currentOrganization,
        protected AuditTrailService $audit,
        protected EarTagAssignmentService $tagAssignments,
        protected EarTagShadowService $tagShadow,
        protected DispatchDomainEvent $dispatcher,
    ) {
    }

    public function execute(PigManagement $pig, User $actor, array $data): PigExit
    {
        $organizationId = $this->currentOrganization->requireId();

        return DB::transaction(function () use ($pig, $actor, $data, $organizationId) {
            // Lock the animal first, as sales and deaths do, so two exits
            // (or an exit racing a sale) cannot both succeed.
            $locked = PigManagement::withTrashed()->whereKey($pig->getKey())->lockForUpdate()->first();
            if ($locked === null || $locked->trashed() || $locked->sales()->exists()
                || $locked->deaths()->exists() || $locked->herdExit()->exists()) {
                throw new \DomainException('This pig has already left the herd (sold, died, transferred or culled).');
            }

            $committed = \App\Models\Storefront\StorefrontOrderItemPigAllocation::query()
                ->where('pig_id', $locked->getKey())
                ->whereIn('status', \App\Models\Storefront\StorefrontOrderItemPigAllocation::ACTIVE_STATUSES)
                ->exists();
            if ($committed) {
                throw new \DomainException('This pig is committed to a storefront order. Release or cancel that order first.');
            }

            if ($data['exit_type'] === PigExit::CULL && in_array($data['disposal'] ?? null, PigExit::FOOD_CHAIN_DISPOSALS, true)) {
                $withdrawal = app(WithdrawalStatusService::class);
                $status = $withdrawal->forPig($locked, $data['exit_date']);
                if ($status['restricted']) {
                    throw new PigUnderWithdrawalException($withdrawal->message($status));
                }
            }

            $exit = PigExit::create([
                'organization_id' => $organizationId,
                'pig_id'          => $locked->id,
                'exit_type'       => $data['exit_type'],
                'exit_date'       => $data['exit_date'],
                'reason'          => $data['reason'],
                'reason_other'    => $data['reason'] === 'other' ? ($data['reason_other'] ?? null) : null,
                'destination'     => $data['exit_type'] === PigExit::TRANSFER_OUT ? ($data['destination'] ?? null) : null,
                'disposal'        => $data['exit_type'] === PigExit::CULL ? ($data['disposal'] ?? null) : null,
                'final_weight'    => $data['final_weight'] ?? null,
                'notes'           => $data['notes'] ?? null,
                'tag_outcome'     => $data['tag_outcome'] ?? EarTag::PENDING_RECOVERY,
                'recorded_by'     => $actor->id,
            ]);

            $tagAssignment = $locked->currentEarTagAssignment()->first() ?? $this->tagShadow->syncNewPig($locked, $actor);
            if ($tagAssignment) {
                $this->tagAssignments->release(
                    $tagAssignment, $exit->tag_outcome, $exit->exit_type, $actor,
                    $data['exit_date'], $data['tag_condition_notes'] ?? null,
                );
            }

            app(StorefrontPigPoolService::class)->markPigLeftHerd(
                $locked, "Left the herd: {$exit->type_label} on {$exit->exit_date->format('d M Y')}.", $actor,
            );

            $locked->delete();

            $this->audit->log('stock', $exit->exit_type === PigExit::CULL ? 'pig_culled' : 'pig_transferred_out', [
                'user'            => $actor,
                'auditable'       => $exit,
                'organization_id' => $organizationId,
                'record_label'    => $locked->code,
                'description'     => "{$exit->type_label} recorded for pig {$locked->code}: {$exit->reason_label}.",
                'new_values'      => $exit->only(['exit_type', 'exit_date', 'reason', 'reason_other', 'destination', 'disposal', 'final_weight', 'tag_outcome']),
            ]);

            $this->dispatcher->dispatch(new PigExited(
                source: new SourceReference(module: 'lifecycle', sourceType: PigExit::class, sourceId: (int) $exit->id, organizationId: $organizationId),
                occurredAt: CarbonImmutable::parse($data['exit_date'])->setTime((int) now()->hour, (int) now()->minute, (int) now()->second),
                dispatchedByUserId: $actor->id,
                pigId: (int) $locked->id,
                pigCode: (string) $locked->code,
                exitType: $exit->exit_type,
                reason: $exit->reason,
                disposal: $exit->disposal,
                finalWeight: $exit->final_weight !== null ? (float) $exit->final_weight : null,
            ));

            return $exit;
        });
    }
}
