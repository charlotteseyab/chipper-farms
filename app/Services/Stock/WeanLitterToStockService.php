<?php

namespace App\Services\Stock;

use App\Domain\Events\Lifecycle\LitterWeaned;
use App\Domain\Events\Lifecycle\PigAcquired;
use App\Domain\Events\Support\SourceReference;
use App\Models\Farrowing;
use App\Models\PigManagement;
use App\Models\PigWeight;
use App\Models\Room;
use App\Models\Weaning;
use App\Services\Audit\AuditTrailService;
use App\Services\DomainEvents\DispatchDomainEvent;
use App\Services\Numbering\NumberingService;
use App\Support\CurrentOrganization;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class WeanLitterToStockService
{
    public function __construct(
        protected CurrentOrganization $currentOrganization,
        protected NumberingService $numbering,
        protected DispatchDomainEvent $dispatcher,
        protected AuditTrailService $audit,
    ) {
    }

    public function execute(Farrowing $farrowing, array $data): Farrowing
    {
        $organizationId = $this->currentOrganization->requireId();

        return DB::transaction(function () use ($farrowing, $data, $organizationId) {
            $locked = Farrowing::withoutGlobalScopes()->whereKey($farrowing->id)->lockForUpdate()->firstOrFail();
            $locked->load(['sow', 'boar', 'pigletDeaths']);

            if (! $locked->sow || (int) $locked->sow->organization_id !== $organizationId) {
                throw new StockValidationException('litter', 'This litter does not belong to the current farm.');
            }
            if ($locked->status !== 'active' || $locked->weanings()->exists()) {
                throw new StockValidationException('litter', 'This litter has already been weaned or is no longer active.');
            }

            $surviving = max(0, (int) $locked->live_births - (int) $locked->pigletDeaths->sum('count'));
            if ($surviving <= 0 || count($data['animals']) !== $surviving) {
                throw new StockValidationException('animals', "The litter currently has {$surviving} surviving piglet(s). Refresh the preview and try again.");
            }

            $weaningDate = Carbon::parse($data['weaning_date']);
            $ageAtWeaning = (int) $locked->farrowing_date->copy()->startOfDay()->diffInDays($weaningDate->copy()->endOfDay());
            $minimumAge = (int) config('breeding.cycle.birth_to_weaning_days', 45);
            if ($ageAtWeaning < $minimumAge) {
                throw new StockValidationException('weaning_date', "Litter is too young for weaning. Minimum age is {$minimumAge} days.");
            }

            $assignmentCounts = collect($data['animals'])->countBy(fn (array $animal) => (int) $animal['room_id']);
            $rooms = Room::withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->whereIn('id', $assignmentCounts->keys())
                ->get()->keyBy('id');
            if ($rooms->count() !== $assignmentCounts->count()) {
                throw new StockValidationException('animals', 'One or more selected rooms do not belong to this farm.');
            }

            $activeCounts = PigManagement::withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->whereIn('room_id', $assignmentCounts->keys())
                ->active()
                ->selectRaw('room_id, COUNT(*) as aggregate')
                ->groupBy('room_id')
                ->pluck('aggregate', 'room_id');

            foreach ($assignmentCounts as $roomId => $incoming) {
                $room = $rooms->get((int) $roomId);
                $active = (int) ($activeCounts[$roomId] ?? 0);
                if ($room->capacity !== null && (int) $room->capacity > 0 && ($active + $incoming) > (int) $room->capacity) {
                    $available = max(0, (int) $room->capacity - $active);
                    throw new StockValidationException('animals', "{$room->name} has {$available} available space(s), but {$incoming} piglet(s) were assigned to it.");
                }
            }

            $created = collect();
            foreach (array_values($data['animals']) as $index => $animal) {
                $room = $rooms->get((int) $animal['room_id']);
                $sequence = $this->numbering->next(
                    sequenceType: 'pig_code', prefix: 'CFPG', year: 0,
                    organizationId: $organizationId, padding: 1, formatTemplate: '{number}',
                    initialNumber: ((int) PigManagement::withoutGlobalScopes()->withTrashed()->where('organization_id', $organizationId)->max('id')) + 1,
                );
                $code = 'CFPG-' . $locked->farrowing_date->format('ym') . '-' . $sequence;

                $pig = PigManagement::create([
                    'organization_id' => $organizationId,
                    'ear_tag_number' => trim($animal['ear_tag_number']),
                    'breed' => $animal['breed'],
                    'gender' => $animal['gender'],
                    'stock_type' => 'standard',
                    'date_of_birth' => $locked->farrowing_date->toDateString(),
                    'age_in_days' => $ageAtWeaning,
                    'code' => $code,
                    'father_id' => $locked->boar_id,
                    'mother_id' => $locked->sow_id,
                    'room_id' => $room->id,
                    'group_id' => $locked->sow->group_id,
                    'acquisition_source' => 'born_on_farm',
                    'acquisition_date' => $locked->farrowing_date->toDateString(),
                    'current_weight_kg_cache' => $animal['weaning_weight'] ?? null,
                    'weight_cache_recorded_at' => filled($animal['weaning_weight'] ?? null) ? $weaningDate : null,
                ]);

                $weaning = Weaning::create([
                    'farrowing_id' => $locked->id,
                    'pig_id' => $pig->id,
                    'mother_id' => $locked->sow_id,
                    'weaning_date' => $weaningDate->toDateString(),
                    'age_at_weaning_days' => $ageAtWeaning,
                    'weaning_weight' => $animal['weaning_weight'] ?? null,
                    'weaning_method' => $data['weaning_method'],
                    'weaning_reason' => $data['weaning_reason'],
                    'notes' => $data['notes'] ?? null,
                    'post_weaning_status' => $data['post_weaning_status'],
                    'recorded_by' => auth()->id(),
                    'maturity_completed' => false,
                ]);

                if (filled($animal['weaning_weight'] ?? null)) {
                    PigWeight::create([
                        'pig_id' => $pig->id,
                        'weight' => $animal['weaning_weight'],
                        'weigh_date' => $weaningDate->toDateString(),
                        'weight_unit' => 'kg',
                        'purpose' => 'health_check',
                        'notes' => 'Initial weight recorded during litter weaning.',
                        'weighed_by' => auth()->id(),
                    ]);
                }

                $this->dispatcher->dispatch(new PigAcquired(
                    source: new SourceReference('lifecycle', PigManagement::class, (int) $pig->id, $organizationId),
                    occurredAt: CarbonImmutable::parse($locked->farrowing_date),
                    dispatchedByUserId: auth()->id(), pigId: (int) $pig->id, pigCode: $code,
                    groupId: $pig->group_id ? (int) $pig->group_id : null, stockType: 'standard', gender: $pig->gender,
                    acquisitionCost: null, weightKg: filled($animal['weaning_weight'] ?? null) ? (float) $animal['weaning_weight'] : null,
                    acquisitionSource: 'born_on_farm',
                ));
                $created->push($pig);
            }

            $locked->update([
                'status' => 'completed',
                'date_weaned' => $weaningDate->toDateString(),
                'notes' => trim(collect([$locked->notes, 'Litter weaned and ' . $surviving . ' individual stock records created on ' . $weaningDate->toDateString(), $data['notes'] ?? null])->filter()->join(PHP_EOL)),
            ]);

            $this->dispatcher->dispatch(new LitterWeaned(
                source: new SourceReference('lifecycle', Farrowing::class, (int) $locked->id, $organizationId),
                occurredAt: CarbonImmutable::parse($weaningDate), dispatchedByUserId: auth()->id(),
                farrowingId: (int) $locked->id, sowId: (int) $locked->sow_id,
                groupId: $locked->sow->group_id ? (int) $locked->sow->group_id : null,
                survivingPiglets: $surviving,
                averageBirthWeightKg: $locked->average_birth_weight !== null ? (float) $locked->average_birth_weight : null,
            ));

            $this->audit->log('weaning', 'litter_added_to_stock', [
                'auditable' => $locked, 'organization_id' => $organizationId,
                'record_label' => 'Litter #' . $locked->id,
                'description' => "Litter from sow {$locked->sow->ear_tag_number} was weaned and {$surviving} animals were added to main stock.",
                'new_values' => ['weaning_date' => $weaningDate->toDateString(), 'quantity_added' => $surviving],
                'metadata' => [
                    'pig_ids' => $created->pluck('id')->all(),
                    'ear_tag_numbers' => $created->pluck('ear_tag_number')->all(),
                    'room_assignments' => $created->mapWithKeys(fn ($pig) => [$pig->ear_tag_number => $pig->room_id])->all(),
                ],
            ]);

            return $locked->fresh(['weanings.pig']);
        });
    }
}
