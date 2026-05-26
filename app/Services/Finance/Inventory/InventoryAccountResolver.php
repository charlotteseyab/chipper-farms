<?php

namespace App\Services\Finance\Inventory;

use App\Finance\Resolvers\FinanceAccountResolver;
use App\Models\FeedType;
use App\Models\Finance\InventoryAccountMapping;
use App\Models\RawMaterial;
use App\Support\CurrentOrganization;

/**
 * Phase 4 — Resolves the correct GL account for a given inventory
 * operation, walking the hierarchy:
 *
 *   1. Per-item override on the source record
 *      (raw_material.inventory_account_id; for finished feed kinds
 *      this step is skipped — feed types don't carry per-item account
 *      overrides today).
 *
 *   2. Category-specific mapping in inventory_account_mappings
 *      WHERE category = the source item's category.
 *
 *   3. Default mapping (category = DEFAULT_CATEGORY_TOKEN,
 *      is_default = true) for that item_kind.
 *
 *   4. Hardcoded fallback by COA code, via FinanceAccountResolver.
 *      Codes come from config('finance.inventory.fallback_account_codes')
 *      with hardcoded defaults baked in.
 *
 * Per-request cache:
 *   The mappings table is rarely changed; each request hydrates the
 *   relevant rows once and reuses them. Code-fallback lookups go
 *   through FinanceAccountResolver which has its own per-request cache.
 *
 * Returns: the chart_of_accounts id (int). NEVER nullable. If no
 * resolution can be made an InventoryAccountResolutionException is
 * thrown with diagnostic context.
 */
class InventoryAccountResolver
{
    /** @var array<string, ?InventoryAccountMapping>  category-keyed cache per kind */
    protected array $mappingCache = [];

    /** @var array<string, int> */
    protected array $codeCache = [];

    public function __construct(
        protected FinanceAccountResolver $financeResolver,
        protected CurrentOrganization $currentOrganization,
    ) {
    }

    // ============================================================
    // Raw material — role accessors
    // ============================================================

    public function inventoryAccountForRawMaterial(RawMaterial $material): int
    {
        // 1. Per-item override.
        if (! empty($material->inventory_account_id)) {
            return (int) $material->inventory_account_id;
        }

        $m = $this->resolveMapping(
            InventoryAccountMapping::KIND_RAW_MATERIAL,
            $this->categoryFor($material),
        );
        if ($m?->inventory_account_id) {
            return (int) $m->inventory_account_id;
        }

        return $this->resolveCode(
            $this->fallbackCode(InventoryAccountMapping::KIND_RAW_MATERIAL, 'inventory', '1210'),
        );
    }

    public function grIrAccountForRawMaterial(RawMaterial $material): int
    {
        $m = $this->resolveMapping(
            InventoryAccountMapping::KIND_RAW_MATERIAL,
            $this->categoryFor($material),
        );
        if ($m?->gr_ir_account_id) {
            return (int) $m->gr_ir_account_id;
        }

        return $this->resolveCode(
            $this->fallbackCode(InventoryAccountMapping::KIND_RAW_MATERIAL, 'gr_ir', '2050'),
        );
    }

    public function suspenseAccountForRawMaterial(RawMaterial $material): int
    {
        $m = $this->resolveMapping(
            InventoryAccountMapping::KIND_RAW_MATERIAL,
            $this->categoryFor($material),
        );
        if ($m?->suspense_account_id) {
            return (int) $m->suspense_account_id;
        }

        return $this->resolveCode(
            $this->fallbackCode(InventoryAccountMapping::KIND_RAW_MATERIAL, 'suspense', '2055'),
        );
    }

    public function adjustmentAccountForRawMaterial(RawMaterial $material): int
    {
        $m = $this->resolveMapping(
            InventoryAccountMapping::KIND_RAW_MATERIAL,
            $this->categoryFor($material),
        );
        if ($m?->adjustment_account_id) {
            return (int) $m->adjustment_account_id;
        }

        return $this->resolveCode(
            $this->fallbackCode(InventoryAccountMapping::KIND_RAW_MATERIAL, 'adjustment', '5300'),
        );
    }

    public function ppvAccountForRawMaterial(RawMaterial $material): int
    {
        $m = $this->resolveMapping(
            InventoryAccountMapping::KIND_RAW_MATERIAL,
            $this->categoryFor($material),
        );
        if ($m?->ppv_account_id) {
            return (int) $m->ppv_account_id;
        }

        return $this->resolveCode(
            $this->fallbackCode(InventoryAccountMapping::KIND_RAW_MATERIAL, 'ppv', '5310'),
        );
    }

    // ============================================================
    // Finished feed — role accessors
    // ============================================================

    public function inventoryAccountForFeedType(FeedType $feedType): int
    {
        $m = $this->resolveMapping(
            InventoryAccountMapping::KIND_FINISHED_FEED,
            null,  // FeedType has no category column today
        );
        if ($m?->inventory_account_id) {
            return (int) $m->inventory_account_id;
        }

        return $this->resolveCode(
            $this->fallbackCode(InventoryAccountMapping::KIND_FINISHED_FEED, 'inventory', '1220'),
        );
    }

    public function adjustmentAccountForFeedType(FeedType $feedType): int
    {
        $m = $this->resolveMapping(InventoryAccountMapping::KIND_FINISHED_FEED, null);
        if ($m?->adjustment_account_id) {
            return (int) $m->adjustment_account_id;
        }

        return $this->resolveCode(
            $this->fallbackCode(InventoryAccountMapping::KIND_FINISHED_FEED, 'adjustment', '5300'),
        );
    }

    public function cogsAccountForFeedType(FeedType $feedType): int
    {
        $m = $this->resolveMapping(InventoryAccountMapping::KIND_FINISHED_FEED, null);
        if ($m?->cogs_account_id) {
            return (int) $m->cogs_account_id;
        }

        return $this->resolveCode(
            $this->fallbackCode(InventoryAccountMapping::KIND_FINISHED_FEED, 'cogs', '5100'),
        );
    }

    // ============================================================
    // Mapping resolver — walks the hierarchy
    // ============================================================

    /**
     * Resolve the mapping row for (kind, category). Returns null if
     * neither a category-specific nor a default row exists.
     */
    protected function resolveMapping(string $kind, ?string $category): ?InventoryAccountMapping
    {
        $orgId = $this->currentOrganization->id();
        $cacheKey = $kind . '|' . ($category ?? '') . '|' . ($orgId ?? 'null');

        if (array_key_exists($cacheKey, $this->mappingCache)) {
            return $this->mappingCache[$cacheKey];
        }

        $query = InventoryAccountMapping::query()
            ->where('item_kind', $kind)
            ->when(
                $orgId !== null,
                fn ($q) => $q->where('organization_id', $orgId),
                fn ($q) => $q->whereNull('organization_id'),
            );

        // First: try category-specific.
        $byCategory = null;
        if ($category !== null && $category !== '' && $category !== InventoryAccountMapping::DEFAULT_CATEGORY_TOKEN) {
            $byCategory = (clone $query)
                ->where('category', $category)
                ->where('is_default', false)
                ->first();
        }

        if ($byCategory) {
            return $this->mappingCache[$cacheKey] = $byCategory;
        }

        // Fall back to default row.
        $default = (clone $query)
            ->where('category', InventoryAccountMapping::DEFAULT_CATEGORY_TOKEN)
            ->where('is_default', true)
            ->first();

        return $this->mappingCache[$cacheKey] = $default;
    }

    protected function categoryFor(RawMaterial $material): ?string
    {
        $cat = $material->category;
        if ($cat === null || $cat === '') {
            return null;
        }
        return $cat;
    }

    // ============================================================
    // COA code fallback
    // ============================================================

    protected function resolveCode(string $code): int
    {
        if (isset($this->codeCache[$code])) {
            return $this->codeCache[$code];
        }

        try {
            $account = $this->financeResolver->byCode($code);
        } catch (\Throwable $e) {
            throw new InventoryAccountResolutionException(
                "InventoryAccountResolver: COA fallback code '{$code}' is missing or inactive. "
                . 'Run Phase 4 COA extension migration and/or configure inventory_account_mappings.',
                previous: $e,
            );
        }

        return $this->codeCache[$code] = (int) $account->id;
    }

    /**
     * Read fallback code from config, falling back to the hardcoded
     * default if the config path is unset.
     *
     * Config path: finance.inventory.fallback_account_codes.{kind}.{role}
     */
    protected function fallbackCode(string $kind, string $role, string $default): string
    {
        $val = config("finance.inventory.fallback_account_codes.{$kind}.{$role}");
        return is_string($val) && $val !== '' ? $val : $default;
    }

    /**
     * Reset caches — used in tests and by long-running queue workers
     * between jobs.
     */
    public function flushCaches(): void
    {
        $this->mappingCache = [];
        $this->codeCache    = [];
    }
}
