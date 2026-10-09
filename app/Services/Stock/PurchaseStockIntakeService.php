<?php

namespace App\Services\Stock;

use App\Models\HealthRecord;
use App\Models\EarTag;
use App\Models\PigManagement;
use App\Models\PigWeight;
use App\Models\PurchaseStockIntake;
use App\Models\Room;
use App\Services\Audit\AuditTrailService;
use App\Services\DomainEvents\DispatchDomainEvent;
use App\Support\CurrentOrganization;
use App\Domain\Events\Lifecycle\PigAcquired;
use App\Domain\Events\Support\SourceReference;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class PurchaseStockIntakeService
{
    public function __construct(
        protected DispatchDomainEvent $dispatcher,
        protected CurrentOrganization $currentOrganization,
        protected PurchaseStockFinanceService $finance,
        protected StockMovementService $stockMovements,
        protected AuditTrailService $audit,
        protected EarTagNumberService $earTags,
    ) {
    }

    public function create(array $data): PurchaseStockIntake
    {
        $purchaseDate = Carbon::parse($data['purchase_date']);

        $data['status'] = PurchaseStockIntake::STATUS_PENDING_RECEIVE;
        $data['created_by'] = auth()->id();
        $data['organization_id'] = $this->currentOrganization->requireId();

        if (! empty($data['estimated_age_days']) && empty($data['estimated_date_of_birth'])) {
            $data['estimated_date_of_birth'] = $purchaseDate->copy()->subDays((int) $data['estimated_age_days'])->toDateString();
        }

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $data['batch_number'] = PurchaseStockIntake::nextBatchNumber((int) $purchaseDate->year);

                $intake = PurchaseStockIntake::create($data);
                $this->audit->log('purchased_stock', 'created', [
                    'auditable' => $intake,
                    'organization_id' => $intake->organization_id,
                    'record_label' => $intake->batch_number,
                    'description' => "Purchased stock intake {$intake->batch_number} was created.",
                    'new_values' => $intake->only(['supplier_name', 'purchase_date', 'animal_category', 'quantity_ordered', 'total_purchase_cost', 'status']),
                ]);

                return $intake;
            } catch (QueryException $e) {
                if (! $this->isDuplicateBatchNumberException($e) || $attempt === 3) {
                    throw $e;
                }
            }
        }

        throw new StockValidationException('batch_number', 'Could not generate a unique purchased stock batch number. Please try again.');
    }

    public function receive(PurchaseStockIntake $intake, array $data): PurchaseStockIntake
    {
        $this->assertStatus($intake, [PurchaseStockIntake::STATUS_PENDING_RECEIVE], 'Only pending purchases can be received.');

        $received = (int) $data['quantity_received'];
        $rejected = (int) ($data['quantity_rejected'] ?? 0);
        if ($received + $rejected > (int) $intake->quantity_ordered) {
            throw new StockValidationException('quantity_received', 'Received plus rejected quantity cannot exceed ordered quantity.');
        }

        $intake->update([
            'quantity_received' => $received,
            'quantity_rejected' => $rejected,
            'health_status' => $data['health_status'] ?? $intake->health_status,
            'health_notes' => $data['health_notes'] ?? $intake->health_notes,
            'received_by' => auth()->id(),
            'received_at' => now(),
            'status' => PurchaseStockIntake::STATUS_RECEIVED,
        ]);

        $this->audit->log('purchased_stock', 'received', [
            'auditable' => $intake,
            'organization_id' => $intake->organization_id,
            'record_label' => $intake->batch_number,
            'description' => "Purchased stock intake {$intake->batch_number} was received.",
            'new_values' => [
                'quantity_received' => $received,
                'quantity_rejected' => $rejected,
                'status' => PurchaseStockIntake::STATUS_RECEIVED,
            ],
        ]);

        return $intake->fresh();
    }

    public function quarantine(PurchaseStockIntake $intake, array $data): PurchaseStockIntake
    {
        $this->assertStatus(
            $intake,
            [PurchaseStockIntake::STATUS_RECEIVED, PurchaseStockIntake::STATUS_QUARANTINED],
            'Only received or quarantined purchases can be assigned quarantine housing.'
        );

        $intake->update([
            'room_id' => $data['room_id'] ?? $intake->room_id,
            'health_status' => $data['health_status'] ?? $intake->health_status,
            'health_notes' => $data['health_notes'] ?? $intake->health_notes,
            'quarantine_release_date' => $data['quarantine_release_date'] ?? $intake->quarantine_release_date,
            'quarantined_by' => $intake->quarantined_by ?: auth()->id(),
            'quarantined_at' => $intake->quarantined_at ?: now(),
            'status' => PurchaseStockIntake::STATUS_QUARANTINED,
        ]);

        $this->audit->log('purchased_stock', 'quarantined', [
            'auditable' => $intake,
            'organization_id' => $intake->organization_id,
            'record_label' => $intake->batch_number,
            'description' => "Purchased stock intake {$intake->batch_number} entered quarantine/intake housing.",
            'new_values' => [
                'room_id' => $data['room_id'] ?? $intake->room_id,
                'health_status' => $data['health_status'] ?? $intake->health_status,
                'status' => PurchaseStockIntake::STATUS_QUARANTINED,
            ],
        ]);

        return $intake->fresh();
    }

    public function approve(PurchaseStockIntake $intake): PurchaseStockIntake
    {
        $this->assertStatus($intake, [PurchaseStockIntake::STATUS_QUARANTINED], 'Only a quarantined purchase can be approved.');

        if (! $intake->hasPassedQuarantineReview()) {
            throw new StockValidationException('quarantine_outcome', 'Complete the quarantine review and clear at least one animal before approval.');
        }

        if ($intake->receivedQuantity() <= 0) {
            throw new StockValidationException('quantity_received', 'Received quantity is required before approval.');
        }

        if ($intake->total_purchase_cost === null) {
            throw new StockValidationException('total_purchase_cost', 'Purchase cost is required before approval.');
        }

        if (! $intake->room_id) {
            throw new StockValidationException('room_id', 'Assign a physical quarantine room before approval.');
        }

        $intake->update([
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'status' => PurchaseStockIntake::STATUS_APPROVED,
        ]);

        $this->audit->log('purchased_stock', 'approved', [
            'auditable' => $intake,
            'organization_id' => $intake->organization_id,
            'record_label' => $intake->batch_number,
            'description' => "Purchased stock intake {$intake->batch_number} was approved.",
            'new_values' => [
                'approved_by' => auth()->id(),
                'status' => PurchaseStockIntake::STATUS_APPROVED,
            ],
        ]);

        return $intake->fresh();
    }

    public function reviewQuarantine(PurchaseStockIntake $intake, array $data): PurchaseStockIntake
    {
        $this->assertStatus($intake, [PurchaseStockIntake::STATUS_QUARANTINED], 'Only quarantined purchases can be reviewed.');

        if (! $intake->room_id) {
            throw new StockValidationException('room_id', 'Assign a physical quarantine room before completing the review.');
        }

        $outcome = $data['quarantine_outcome'];
        $rejectedQuantity = match ($outcome) {
            PurchaseStockIntake::QUARANTINE_REJECTED => $intake->receivedQuantity(),
            PurchaseStockIntake::QUARANTINE_PARTIALLY_CLEARED => (int) $data['quarantine_rejected_quantity'],
            default => 0,
        };

        if ($outcome === PurchaseStockIntake::QUARANTINE_PARTIALLY_CLEARED
            && ($rejectedQuantity <= 0 || $rejectedQuantity >= $intake->receivedQuantity())) {
            throw new StockValidationException('quarantine_rejected_quantity', 'For partial clearance, rejected animals must be at least one and fewer than the received quantity.');
        }

        $status = $outcome === PurchaseStockIntake::QUARANTINE_REJECTED
            ? PurchaseStockIntake::STATUS_REJECTED
            : PurchaseStockIntake::STATUS_QUARANTINED;

        $intake->update([
            'quarantine_outcome' => $outcome,
            'quarantine_rejected_quantity' => $rejectedQuantity,
            'quarantine_disposition' => $data['quarantine_disposition'] ?? null,
            'quarantine_review_notes' => $data['quarantine_review_notes'] ?? null,
            'health_status' => $data['health_status'] ?? $intake->health_status,
            'health_notes' => $data['health_notes'] ?? $intake->health_notes,
            'quarantine_release_date' => $outcome === PurchaseStockIntake::QUARANTINE_EXTENDED
                ? $data['quarantine_release_date']
                : $intake->quarantine_release_date,
            'quarantine_reviewed_by' => auth()->id(),
            'quarantine_reviewed_at' => now(),
            'status' => $status,
        ]);

        $description = match ($outcome) {
            PurchaseStockIntake::QUARANTINE_CLEARED => "Purchased stock intake {$intake->batch_number} passed quarantine.",
            PurchaseStockIntake::QUARANTINE_PARTIALLY_CLEARED => "Purchased stock intake {$intake->batch_number} was partially cleared after quarantine.",
            PurchaseStockIntake::QUARANTINE_EXTENDED => "Quarantine was extended for purchased stock intake {$intake->batch_number}.",
            default => "Purchased stock intake {$intake->batch_number} was rejected after quarantine.",
        };

        $this->audit->log('purchased_stock', 'quarantine_reviewed', [
            'auditable' => $intake,
            'organization_id' => $intake->organization_id,
            'record_label' => $intake->batch_number,
            'description' => $description,
            'new_values' => [
                'quarantine_outcome' => $outcome,
                'quantity_cleared' => $intake->clearedQuantity(),
                'quarantine_rejected_quantity' => $rejectedQuantity,
                'quarantine_disposition' => $data['quarantine_disposition'] ?? null,
                'status' => $status,
            ],
        ]);

        return $intake->fresh();
    }

    public function addToStock(PurchaseStockIntake $intake, array $earTagNumbers = [], array $housing = []): PurchaseStockIntake
    {
        return DB::transaction(function () use ($intake, $earTagNumbers, $housing) {
            $lockedIntake = PurchaseStockIntake::whereKey($intake->id)
                ->lockForUpdate()
                ->firstOrFail();

            $legacyPigletConversion = $lockedIntake->status === PurchaseStockIntake::STATUS_ADDED_TO_STOCK
                && $lockedIntake->isPigletBatch()
                && ! $lockedIntake->pigs()->exists()
                && \App\Models\StockMovement::where('source_type', \App\Models\StockMovement::SOURCE_PURCHASE_STOCK_INTAKE)
                    ->where('source_id', $lockedIntake->id)
                    ->exists();

            if ($lockedIntake->status === PurchaseStockIntake::STATUS_ADDED_TO_STOCK && ! $legacyPigletConversion) {
                throw new StockValidationException('status', 'This purchased stock intake has already been added to stock.');
            }

            if (! $legacyPigletConversion) {
                $this->assertStatus($lockedIntake, [PurchaseStockIntake::STATUS_APPROVED], 'Only approved purchases can be added to stock.');
            }
            $this->applyFinalHousing($lockedIntake, $housing);

            $journalBatchId = $legacyPigletConversion
                ? $lockedIntake->acquisition_journal_batch_id
                : $this->finance->recordAcquisition($lockedIntake);

            if ($journalBatchId && ! $lockedIntake->acquisition_journal_batch_id) {
                $lockedIntake->forceFill(['acquisition_journal_batch_id' => $journalBatchId])->save();
            }

            $this->assertNoExistingPigRecords($lockedIntake);
            $this->assertRoomCapacity($lockedIntake);
            $this->createIndividualPigs(
                $lockedIntake,
                $this->normalizeEarTagNumbers($lockedIntake, $earTagNumbers, $housing['tag_color_ids'] ?? []),
                $housing['tag_color_ids'] ?? [],
            );

            if ($legacyPigletConversion) {
                \App\Models\StockMovement::where('source_type', \App\Models\StockMovement::SOURCE_PURCHASE_STOCK_INTAKE)
                    ->where('source_id', $lockedIntake->id)
                    ->delete();
            }

            $lockedIntake->update([
                'added_to_stock_by' => auth()->id(),
                'added_to_stock_at' => now(),
                'status' => PurchaseStockIntake::STATUS_ADDED_TO_STOCK,
            ]);

            $this->audit->log('purchased_stock', 'added_to_stock', [
                'auditable' => $lockedIntake,
                'organization_id' => $lockedIntake->organization_id,
                'record_label' => $lockedIntake->batch_number,
                'description' => "Purchased stock intake {$lockedIntake->batch_number} was added to stock.",
                'new_values' => [
                    'quantity_added' => $lockedIntake->clearedQuantity(),
                    'animal_category' => $lockedIntake->animal_category,
                    'status' => PurchaseStockIntake::STATUS_ADDED_TO_STOCK,
                ],
                'metadata' => [
                    'ear_tag_numbers' => $earTagNumbers,
                ],
            ]);

            return $lockedIntake->fresh(['pigs']);
        });
    }

    /** @return array<int,string> */
    public function proposeEarTagNumbers(int $quantity, ?int $organizationId = null): array
    {
        return $this->earTags->propose($quantity, $organizationId);
    }

    protected function applyFinalHousing(PurchaseStockIntake $intake, array $housing): void
    {
        if (array_key_exists('room_id', $housing)) {
            $intake->forceFill([
                'room_id' => $housing['room_id'] !== '' ? ($housing['room_id'] ?? null) : null,
            ])->save();

            $intake->refresh();
        }

        if (! $intake->room_id) {
            throw new StockValidationException('room_id', 'Assign a physical room before adding purchased stock.');
        }
    }

    protected function assertRoomCapacity(PurchaseStockIntake $intake): void
    {
        if (! $intake->room_id) {
            return;
        }

        $room = Room::whereKey($intake->room_id)->lockForUpdate()->first();

        if (! $room || $room->capacity === null || (int) $room->capacity <= 0) {
            return;
        }

        $activeCount = (int) $room->pigs()
            ->whereDoesntHave('sales')
            ->whereDoesntHave('deaths')
            ->count();
        $available = max(0, (int) $room->capacity - $activeCount);

        if ($intake->clearedQuantity() > $available) {
            throw new StockValidationException('room_id', "Selected room only has {$available} available space(s) for {$intake->clearedQuantity()} cleared animal(s).");
        }
    }

    protected function assertNoExistingPigRecords(PurchaseStockIntake $intake): void
    {
        if ($intake->pigs()->exists()) {
            throw new StockValidationException('status', 'Pig records already exist for this purchased stock intake.');
        }
    }

    protected function assertNoExistingStockMovement(PurchaseStockIntake $intake): void
    {
        if (\App\Models\StockMovement::where('source_type', \App\Models\StockMovement::SOURCE_PURCHASE_STOCK_INTAKE)
            ->where('source_id', $intake->id)
            ->exists()) {
            throw new StockValidationException('status', 'A stock movement already exists for this purchased stock intake.');
        }
    }

    protected function normalizeEarTagNumbers(PurchaseStockIntake $intake, array $earTagNumbers, array $tagColorIds = []): array
    {
        $tags = array_values(array_filter(array_map(
            fn ($tag) => trim((string) $tag),
            $earTagNumbers
        )));

        if (count($tags) !== $intake->clearedQuantity()) {
            throw new StockValidationException('ear_tag_numbers', 'Enter one ear tag number for each animal cleared after quarantine.');
        }

        $physicalTags = [];
        foreach ($tags as $index => $number) {
            $identity = EarTag::normalizeNumber($number);
            if (isset($physicalTags[$identity])) {
                throw new StockValidationException(
                    'ear_tag_numbers',
                    'The same tag number cannot be assigned to more than one animal.'
                );
            }
            $physicalTags[$identity] = true;
        }

        return $tags;
    }

    protected function createIndividualPigs(PurchaseStockIntake $intake, array $earTagNumbers, array $tagColorIds = []): void
    {
        $count = $intake->clearedQuantity();
        $costPerPig = $intake->costPerReceivedAnimal();
        $dob = $intake->estimated_date_of_birth
            ? Carbon::parse($intake->estimated_date_of_birth)
            : Carbon::parse($intake->purchase_date)->subDays((int) ($intake->estimated_age_days ?? 0));
        $organizationId = $this->currentOrganization->id();
        $maleRemaining = $intake->male_quantity;
        $femaleRemaining = $intake->female_quantity;

        for ($i = 1; $i <= $count; $i++) {
            $gender = $this->nextGender($maleRemaining, $femaleRemaining);
            $pig = PigManagement::create([
                'organization_id' => $organizationId,
                'ear_tag_number' => $earTagNumbers[$i - 1],
                'breed' => $intake->breed ?: 'unknown',
                'gender' => $gender,
                'stock_type' => $intake->animal_category === PurchaseStockIntake::CATEGORY_BREEDING_STOCK ? 'breeding' : 'standard',
                'date_of_birth' => $dob->toDateString(),
                'code' => $this->purchasedPigCode($intake, $i, $dob),
                'age_in_days' => (int) $dob->diffInDays(now()),
                'room_id' => $intake->room_id,
                'group_id' => $intake->group_id,
                'acquisition_cost' => $costPerPig,
                'acquisition_date' => $intake->purchase_date,
                'acquisition_source' => 'purchased',
                'acquisition_journal_batch_id' => $intake->acquisition_journal_batch_id,
                'purchase_stock_intake_id' => $intake->id,
                'current_weight_kg_cache' => $intake->average_weight_kg,
                'weight_cache_recorded_at' => $intake->average_weight_kg ? now() : null,
            ]);

            app(\App\Services\Stock\Tags\EarTagShadowService::class)
                ->syncNewPig(
                    $pig,
                    auth()->user(),
                    filled($tagColorIds[$i - 1] ?? null)
                        ? \App\Models\EarTagColor::whereKey($tagColorIds[$i - 1])->where('is_active', true)->first()
                        : null,
                );

            if ($intake->average_weight_kg) {
                $weightHealthStatus = in_array($intake->health_status, ['excellent', 'good', 'fair', 'poor'], true)
                    ? $intake->health_status
                    : null;

                PigWeight::create([
                    'pig_id' => $pig->id,
                    'weight' => $intake->average_weight_kg,
                    'weigh_date' => $intake->purchase_date,
                    'weight_unit' => 'kg',
                    'purpose' => 'health_check',
                    'is_estimated' => true,
                    'health_status' => $weightHealthStatus,
                    'notes' => 'Initial purchased stock intake weight from ' . $intake->batch_number,
                ]);
            }

            if ($intake->health_status || $intake->health_notes) {
                HealthRecord::create([
                    'pig_id' => $pig->id,
                    'record_date' => $intake->purchase_date,
                    'condition' => $intake->health_status ?: 'intake',
                    'severity' => 'low',
                    'diagnosis' => $intake->health_notes,
                    'recorded_by' => auth()->id(),
                ]);
            }

            if ($organizationId !== null) {
                $this->dispatcher->dispatch(new PigAcquired(
                    source: new SourceReference(
                        module: 'purchase_stock_intake',
                        sourceType: PurchaseStockIntake::class,
                        sourceId: (int) $intake->id,
                        organizationId: $organizationId,
                    ),
                    occurredAt: CarbonImmutable::parse($intake->purchase_date),
                    dispatchedByUserId: auth()->id(),
                    pigId: (int) $pig->id,
                    pigCode: (string) $pig->code,
                    groupId: $pig->group_id ? (int) $pig->group_id : null,
                    stockType: (string) $pig->stock_type,
                    gender: $pig->gender,
                    acquisitionCost: $costPerPig,
                    weightKg: $intake->average_weight_kg ? (float) $intake->average_weight_kg : null,
                    acquisitionSource: 'purchased',
                ));
            }
        }
    }

    protected function purchasedPigCode(PurchaseStockIntake $intake, int $sequence, Carbon $dob): string
    {
        return 'CFPG-' . $dob->format('ym') . '-' . $intake->id . '-' . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }

    protected function nextGender(?int &$maleRemaining, ?int &$femaleRemaining): string
    {
        if ($femaleRemaining !== null && $femaleRemaining > 0) {
            $femaleRemaining--;
            return 'F';
        }

        if ($maleRemaining !== null && $maleRemaining > 0) {
            $maleRemaining--;
            return 'M';
        }

        return 'M';
    }

    protected function assertStatus(PurchaseStockIntake $intake, array $allowed, string $message): void
    {
        if (! in_array($intake->status, $allowed, true)) {
            throw new StockValidationException('status', $message);
        }
    }

    protected function isDuplicateBatchNumberException(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'purchase_stock_intakes_batch_number_unique')
            || str_contains($message, 'batch_number')
            || str_contains($message, 'duplicate')
            || str_contains($message, 'unique constraint');
    }
}
