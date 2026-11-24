<?php

namespace App\Services\Stock;

use App\Domain\Events\Lifecycle\PigAcquired;
use App\Domain\Events\Support\SourceReference;
use App\Models\EarTag;
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
 * Code generation: CFPG-{YM}-{n}.
 *
 * This previously used MAX(id)+1 without locking, and the docblock here
 * acknowledged the race: "marginally susceptible to race conflicts under heavy
 * concurrency... A Phase 1.x improvement would wrap in SELECT ... FOR UPDATE on
 * a counter table, but that's out of scope for this decomposition."
 *
 * pigs_management.code carries a UNIQUE key, so the consequence was not a
 * cosmetic duplicate: two concurrent registrations compute the same MAX(id)+1
 * and the second insert is rejected outright. The counter table the docblock
 * asked for now exists (document_sequences) with a locking allocator
 * (NumberingService), so the deferred improvement is applied here — this is the
 * repository-wide sweep for MAX()+1 identifiers that CF-P1-014 requires.
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
     * $assignEarTag false creates an animal that carries no physical tag; it
     * never enters the tag register, so it cannot later appear as a tag
     * awaiting recovery.
     *
     * @throws StockValidationException
     */
    public function create(array $data, bool $assignEarTag = true): PigManagement
    {
        $tagColorId = $data['ear_tag_color_id'] ?? null;
        unset($data['ear_tag_color_id']);
        if (! empty($data['room_id'])) {
            $room = Room::find($data['room_id']);
            if (! $room) {
                throw new StockValidationException('room_id', 'Selected room not found. Please choose another room.');
            }
            if (! $room->canAccommodatePig()) {
                throw new StockValidationException('room_id', 'Selected room is full. Please choose another room.');
            }
        }

        $data['ear_tag_number'] = trim((string) ($data['ear_tag_number'] ?? ''));

        if ($this->relationships->hasCriticalRisk($data['father_id'] ?? null, $data['mother_id'] ?? null)) {
            throw new StockValidationException('inbreeding', 'Inbreeding risk detected.');
        }

        $birthDate = Carbon::parse($data['date_of_birth']);

        $organizationId = $this->currentOrganization->requireId();

        // Code generation: CFPG-{YM}-{n}, with the numeric part allocated under
        // a row lock instead of MAX(id)+1 (see class docblock).
        //
        // padding: 1 keeps the suffix unpadded, matching codes already issued.
        // year: 0 marks a non-annual, continuous series — the {YM} segment
        // already varies by birth month, so resetting the counter annually would
        // reissue suffixes and collide on the unique key.
        // initialNumber seeds from MAX(id)+1, which is >= every suffix any
        // existing code can hold, so adoption cannot collide with live data.
        $sequenceNumber = app(\App\Services\Numbering\NumberingService::class)->next(
            sequenceType: 'pig_code',
            prefix: 'CFPG',
            year: 0,
            organizationId: $organizationId,
            padding: 1,
            formatTemplate: '{number}',
            initialNumber: ((int) PigManagement::withTrashed()->max('id')) + 1,
        );

        $data['code']        = 'CFPG-' . $birthDate->format('ym') . '-' . $sequenceNumber;
        $data['age_in_days'] = (int) $birthDate->diffInDays(now());

        if ($organizationId !== null) {
            $data['organization_id'] = $organizationId;
        }

        if (! $assignEarTag) {
            $data['ear_tag_number'] = '';
        }

        return DB::transaction(function () use ($data, $organizationId, $tagColorId, $assignEarTag) {
            $pig = PigManagement::create($data);

            if ($assignEarTag) {
                $shadow = app(\App\Services\Stock\Tags\EarTagShadowService::class);
                $color = $tagColorId
                    ? \App\Models\EarTagColor::whereKey($tagColorId)->where('is_active', true)->firstOrFail()
                    : $shadow->legacyColor((int) $organizationId, auth()->user());
                if ($data['ear_tag_number'] === '') {
                    app(\App\Services\Stock\Tags\EarTagAssignmentService::class)->assignAutomatically($pig, $color, auth()->user());
                    $pig->refresh();
                } else {
                    $shadow->syncNewPig($pig, auth()->user(), $color);
                }
            }

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

        $data['ear_tag_number'] = trim((string) ($data['ear_tag_number'] ?? ''));

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
            \App\Models\Organization::whereKey($pig->organization_id)->lockForUpdate()->firstOrFail();
            $pig = PigManagement::whereKey($pig->id)->lockForUpdate()->firstOrFail();
            $this->guardManagedTagAgainstInlineChange($pig, $data['ear_tag_number']);
            $data['ear_tag_number'] = $pig->ear_tag_number;
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

        $data['ear_tag_number'] = trim((string) ($data['ear_tag_number'] ?? ''));

        // Recalculate age only if DOB changed — preserves original behaviour.
        $currentDob = $pig->date_of_birth ? $pig->date_of_birth->format('Y-m-d') : null;
        if ($currentDob !== $data['date_of_birth']) {
            $data['age_in_days'] = (int) Carbon::parse($data['date_of_birth'])->diffInDays(now());
        }

        return DB::transaction(function () use ($pig, $data) {
            \App\Models\Organization::whereKey($pig->organization_id)->lockForUpdate()->firstOrFail();
            $pig = PigManagement::whereKey($pig->id)->lockForUpdate()->firstOrFail();
            $this->guardManagedTagAgainstInlineChange($pig, $data['ear_tag_number']);
            $data['ear_tag_number'] = $pig->ear_tag_number;
            $pig->update($data);
            return $pig->fresh();
        });
    }

    private function guardManagedTagAgainstInlineChange(PigManagement $pig, string $requestedNumber): void
    {
        $current = $pig->currentEarTagAssignment()->with('tag')->first();
        $number = $current?->tag?->normalized_tag_number ?? EarTag::normalizeNumber((string) $pig->ear_tag_number);
        if (EarTag::normalizeNumber($requestedNumber) !== $number) {
            throw new StockValidationException(
                'ear_tag_number',
                'Use the physical tag action to assign or replace a tag and preserve its history.'
            );
        }
    }
}
