<?php

namespace App\Services\Stock;

use App\Domain\Events\Lifecycle\PigAcquired;
use App\Domain\Events\Support\SourceReference;
use App\Models\PigManagement;
use App\Models\Room;
use App\Services\DomainEvents\DispatchDomainEvent;
use App\Support\CurrentOrganization;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Stock CRUD write path (create / standardUpdate / breedingUpdate).
 *
 * Extracted from PigManagementController::store (line 285), ::standardUpdate
 * (line 366), and ::update (line 628). Each of those methods duplicated the
 * same concerns: room capacity check, ear tag normalisation, code generation,
 * age calculation, inbreeding guard, persist, redirect.
 *
 * The service exposes three operations that mirror the three controller
 * methods. Each returns the persisted PigManagement or throws a
 * StockValidationException carrying the field+message pair the controller
 * should feed back into session errors.
 *
 * Code generation: preserved exactly from store() — CFPG-{YM}-{next_id}.
 * This uses MAX(id)+1 without locking which is marginally susceptible to
 * race conflicts under heavy concurrency. Acceptable for the current scale.
 * A Phase 1.x improvement would wrap in SELECT ... FOR UPDATE on a counter
 * table, but that's out of scope for this decomposition.
 */
class PigStockService
{
    public function __construct(
        protected PigRelationshipAnalysisService $relationships,
        protected DispatchDomainEvent $dispatcher,
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    /**
     * Create a new pig (breeding or standard).
     *
     * Payload expected already-validated by the controller with these keys:
     *   breed, gender, stock_type, date_of_birth, ear_tag_number,
     *   father_id (nullable), mother_id (nullable), room_id (nullable)
     *
     * @throws StockValidationException
     */
    public function create(array $data): PigManagement
    {
        if (! empty($data['room_id'])) {
            $room = Room::find($data['room_id']);
            if (! $room) {
                throw new StockValidationException('room_id', 'Selected room not found. Please choose another room.');
            }
            if (! $room->canAccommodatePig()) {
                throw new StockValidationException('room_id', 'Selected room is full. Please choose another room.');
            }
        }

        $data['ear_tag_number'] = trim((string) $data['ear_tag_number']);

        if ($this->relationships->hasCriticalRisk($data['father_id'] ?? null, $data['mother_id'] ?? null)) {
            throw new StockValidationException('inbreeding', 'Inbreeding risk detected.');
        }

        $birthDate = Carbon::parse($data['date_of_birth']);

        // Code generation: CFPG-{YM}-{next_id}. The max(id)+1 approach matches
        // the original implementation; see class docblock for caveat.
        $nextId = (int) PigManagement::max('id') + 1;
        $data['code']        = 'CFPG-' . $birthDate->format('ym') . '-' . $nextId;
        $data['age_in_days'] = (int) $birthDate->diffInDays(now());

        $organizationId = $this->currentOrganization->id();
        if ($organizationId !== null) {
            $data['organization_id'] = $organizationId;
        }

        return DB::transaction(function () use ($data, $organizationId) {
            $pig = PigManagement::create($data);

            if ($organizationId !== null) {
                $this->dispatcher->dispatch(new PigAcquired(
                    source: new SourceReference(
                        module: 'lifecycle',
                        sourceType: PigManagement::class,
                        sourceId: (int) $pig->id,
                        organizationId: $organizationId,
                    ),
                    occurredAt: CarbonImmutable::parse($data['acquisition_date'] ?? $data['date_of_birth'] ?? now()),
                    dispatchedByUserId: auth()->id(),
                    pigId: (int) $pig->id,
                    pigCode: (string) ($pig->code ?? ''),
                    groupId: $pig->group_id ? (int) $pig->group_id : null,
                    stockType: (string) $pig->stock_type,
                    gender: $pig->gender,
                    acquisitionCost: isset($data['acquisition_cost']) ? (float) $data['acquisition_cost'] : null,
                    weightKg: isset($data['current_weight_kg_cache']) ? (float) $data['current_weight_kg_cache'] : null,
                    acquisitionSource: $data['acquisition_source'] ?? null,
                ));
            }

            return $pig;
        });
    }

    /**
     * Update a standard-stock pig. Differs from updateBreeding() in that
     * standard updates accept structure_id and notes and do not allow
     * stock_type to be changed here.
     *
     * @throws StockValidationException
     */
    public function updateStandard(PigManagement $pig, array $data): PigManagement
    {
        if ($pig->stock_type !== 'standard') {
            throw new StockValidationException('stock_type', 'Only standard stock can be edited here.');
        }

        $data['ear_tag_number'] = trim((string) $data['ear_tag_number']);

        $birthDate = Carbon::parse($data['date_of_birth']);
        $data['age_in_days'] = (int) $birthDate->diffInDays(now());

        if ($this->relationships->hasCriticalRiskForUpdate(
            $data['father_id'] ?? null,
            $data['mother_id'] ?? null,
            $pig->id,
        )) {
            throw new StockValidationException('inbreeding', 'Inbreeding risk detected.');
        }

        return DB::transaction(function () use ($pig, $data) {
            $pig->update($data);
            return $pig->fresh();
        });
    }

    /**
     * Update a breeding-stock pig via the legacy breedingStocks update path.
     * The original method also allowed stock_type reassignment; that
     * behaviour is preserved here exactly.
     *
     * @throws StockValidationException
     */
    public function updateBreeding(PigManagement $pig, array $data): PigManagement
    {
        if ($this->relationships->hasCriticalRiskForUpdate(
            $data['father_id'] ?? null,
            $data['mother_id'] ?? null,
            $pig->id,
        )) {
            throw new StockValidationException('inbreeding', 'Inbreeding risk detected.');
        }

        if (! empty($data['room_id'])) {
            $room = Room::find($data['room_id']);
            if (! $room) {
                throw new StockValidationException('room_id', 'Selected room not found or inactive.');
            }
            if (! $room->canAccommodatePig($pig->id)) {
                throw new StockValidationException('room_id', 'Selected room is full. Please choose another room.');
            }
        }

        $data['ear_tag_number'] = trim((string) $data['ear_tag_number']);

        // Recalculate age only if DOB changed — preserves original behaviour.
        $currentDob = $pig->date_of_birth ? $pig->date_of_birth->format('Y-m-d') : null;
        if ($currentDob !== $data['date_of_birth']) {
            $data['age_in_days'] = (int) Carbon::parse($data['date_of_birth'])->diffInDays(now());
        }

        return DB::transaction(function () use ($pig, $data) {
            $pig->update($data);
            return $pig->fresh();
        });
    }
}
