<?php

namespace App\Services\Finance\Costing;

use App\Models\Finance\GroupCostMovement;
use App\Models\Finance\GroupCostPool;
use App\Models\Group;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Phase 5 — read-only facade over group cost pools.
 *
 * Mirrors Phase 4's InventoryLedgerReader pattern.
 */
class GroupCostPoolReader
{
    public function __construct(
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    /**
     * @return array{total_carrying_value: string, total_weight_kg: string, total_pig_count: int, open_pool_count: int}
     */
    public function totals(bool $openOnly = true): array
    {
        $orgId = $this->currentOrganization->id();

        $query = GroupCostPool::query()
            ->when($orgId !== null,
                fn ($q) => $q->where('organization_id', $orgId),
                fn ($q) => $q->whereNull('organization_id'),
            );

        if ($openOnly) {
            $query->whereNull('closed_at');
        }

        $row = $query->selectRaw(
            'COALESCE(SUM(total_carrying_value), 0) AS v, '
            . 'COALESCE(SUM(total_weight_kg), 0) AS w, '
            . 'COALESCE(SUM(on_hand_pig_count), 0) AS c, '
            . 'COUNT(*) AS p'
        )->first();

        return [
            'total_carrying_value' => (string) ($row?->v ?? '0'),
            'total_weight_kg'      => (string) ($row?->w ?? '0'),
            'total_pig_count'      => (int) ($row?->c ?? 0),
            'open_pool_count'      => (int) ($row?->p ?? 0),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function summary(bool $includeClosed = false): Collection
    {
        $orgId = $this->currentOrganization->id();

        $query = GroupCostPool::query()
            ->with('group')
            ->when($orgId !== null,
                fn ($q) => $q->where('organization_id', $orgId),
                fn ($q) => $q->whereNull('organization_id'),
            )
            ->orderByDesc('total_carrying_value');

        if (! $includeClosed) {
            $query->whereNull('closed_at');
        }

        return $query->get()->map(function (GroupCostPool $p) {
            return [
                'pool_id'              => $p->id,
                'group_id'             => (int) $p->group_id,
                'group_name'           => $p->group?->name ?? $p->group_name_cache ?? '(unknown)',
                'group_type'           => $p->group?->type ?? $p->group_type_cache,
                'on_hand_pig_count'    => (int) $p->on_hand_pig_count,
                'total_carrying_value' => (string) $p->total_carrying_value,
                'total_weight_kg'      => (string) $p->total_weight_kg,
                'per_kg_basis'         => $p->perKgBasis(),
                'per_pig_average'      => $p->perPigAverage(),
                'opened_at'            => $p->opened_at?->toIso8601String(),
                'closed_at'            => $p->closed_at?->toIso8601String(),
                'last_movement_at'     => $p->last_movement_at?->toIso8601String(),
            ];
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public function summaryForGroup(int $groupId): ?array
    {
        $orgId = $this->currentOrganization->id();

        $pool = GroupCostPool::query()
            ->with('group')
            ->when($orgId !== null,
                fn ($q) => $q->where('organization_id', $orgId),
                fn ($q) => $q->whereNull('organization_id'),
            )
            ->where('group_id', $groupId)
            ->first();

        if (! $pool) {
            return null;
        }

        return [
            'pool_id'              => $pool->id,
            'group_id'             => $groupId,
            'group_name'           => $pool->group?->name ?? $pool->group_name_cache,
            'group_type'           => $pool->group?->type,
            'on_hand_pig_count'    => (int) $pool->on_hand_pig_count,
            'total_carrying_value' => (string) $pool->total_carrying_value,
            'total_weight_kg'      => (string) $pool->total_weight_kg,
            'per_kg_basis'         => $pool->perKgBasis(),
            'per_pig_average'      => $pool->perPigAverage(),
            'opened_at'            => $pool->opened_at?->toIso8601String(),
            'closed_at'            => $pool->closed_at?->toIso8601String(),
            'last_movement_at'     => $pool->last_movement_at?->toIso8601String(),
        ];
    }

    /**
     * @return Collection<int, GroupCostMovement>
     */
    public function recentMovementsForGroup(int $groupId, int $limit = 50): Collection
    {
        $orgId = $this->currentOrganization->id();

        return GroupCostMovement::query()
            ->when($orgId !== null,
                fn ($q) => $q->where('organization_id', $orgId),
                fn ($q) => $q->whereNull('organization_id'),
            )
            ->where('group_id', $groupId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function poolValueAt(int $groupId, CarbonImmutable $cutoff): string
    {
        $orgId = $this->currentOrganization->id();

        $movement = GroupCostMovement::query()
            ->when($orgId !== null,
                fn ($q) => $q->where('organization_id', $orgId),
                fn ($q) => $q->whereNull('organization_id'),
            )
            ->where('group_id', $groupId)
            ->where('occurred_at', '<=', $cutoff)
            ->orderByDesc('id')
            ->first();

        return $movement?->value_after !== null
            ? (string) $movement->value_after
            : '0';
    }
}
