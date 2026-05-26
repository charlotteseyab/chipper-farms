<?php

namespace App\Services\Deaths;

use App\Domain\Events\Lifecycle\PigDeathRecorded;
use App\Domain\Events\Support\SourceReference;
use App\Models\PigDeath;
use App\Models\PigManagement;
use App\Models\User;
use App\Services\DomainEvents\DispatchDomainEvent;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Encapsulates recording a pig death.
 *
 * Extracted from PigManagementController::recordDeath (was line 2585–2634).
 * The original implementation had no DB::transaction wrapping the death
 * save and the pig soft-delete — a latent bug where a mid-sequence failure
 * could leave a death record without the pig being soft-deleted, or vice
 * versa. This service fixes that by wrapping both in a single transaction.
 *
 * Also emits the PigDeathRecorded domain event for Phase 1 finance
 * (biological asset write-off posting).
 *
 * Does NOT do input validation — that's the controller's concern via
 * RecordPigDeathRequest. The service assumes the caller has:
 *   - confirmed the pig exists and is not already deceased
 *   - passed the already-normalised data (e.g. "other" collapsed to text)
 */
class RecordPigDeathService
{
    public function __construct(
        protected DispatchDomainEvent $dispatcher,
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    /**
     * Record a death.
     *
     * @param  PigManagement  $pig
     * @param  User           $actor
     * @param  array{
     *     death_date:string,
     *     death_cause:string,
     *     final_weight?:float|null,
     *     death_location?:string|null,
     *     veterinary_notes?:string|null,
     *     disposal_method:string,
     *     disposal_date?:string|null,
     *     death_notes?:string|null,
     * }  $data
     */
    public function execute(PigManagement $pig, User $actor, array $data): PigDeath
    {
        $organizationId = $this->currentOrganization->requireId();

        return DB::transaction(function () use ($pig, $actor, $data, $organizationId) {
            $death = new PigDeath([
                'pig_id'          => $pig->id,
                'death_date'      => $data['death_date'],
                'death_cause'     => $data['death_cause'],
                'final_weight'    => $data['final_weight']     ?? null,
                'death_location'  => $data['death_location']   ?? null,
                'veterinary_notes'=> $data['veterinary_notes'] ?? null,
                'disposal_method' => $data['disposal_method'],
                'disposal_date'   => $data['disposal_date']    ?? null,
                'death_notes'     => $data['death_notes']      ?? null,
                'recorded_by'     => $actor->id,
            ]);

            $death->setRelation('pig', $pig);
            $death->save();

            if (! $pig->trashed()) {
                $pig->delete();
            }

            $ageAtDeathDays = null;
            if ($pig->date_of_birth) {
                $ageAtDeathDays = (int) \Carbon\Carbon::parse($pig->date_of_birth)
                    ->diffInDays(\Carbon\Carbon::parse($data['death_date']));
            }

            $this->dispatcher->dispatch(new PigDeathRecorded(
                source: new SourceReference(
                    module:         'lifecycle',
                    sourceType:     PigDeath::class,
                    sourceId:       (int) $death->id,
                    organizationId: $organizationId,
                ),
                occurredAt: CarbonImmutable::parse($data['death_date'])->setTime(
                    (int) now()->hour, (int) now()->minute, (int) now()->second
                ),
                dispatchedByUserId: $actor->id,
                pigId:          (int) $pig->id,
                pigCode:        (string) ($pig->code ?? ''),
                ageAtDeathDays: $ageAtDeathDays,
                deathCause:     (string) $data['death_cause'],
                finalWeight:    isset($data['final_weight']) ? (float) $data['final_weight'] : null,
                disposalMethod: (string) $data['disposal_method'],
            ));

            return $death;
        });
    }
}