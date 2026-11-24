<?php

namespace App\Services\Feed;

use App\Models\FeedRecipeVersion;
use App\Models\FeedType;
use App\Models\Organization;
use App\Models\RawMaterial;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FeedRecipeService
{
    public function validateIngredients(int $organizationId, array $items): array
    {
        $total = '0';
        $ingredients = [];
        foreach ($items as $item) {
            $material = RawMaterial::where('organization_id', $organizationId)->find($item['raw_material_id'] ?? 0);
            $quantity = $item['quantity_per_100kg'] ?? null;
            if ($material && $material->is_active && $material->kgPerUnit() === null) {
                throw ValidationException::withMessages(['items' => "{$material->name} is stocked in {$material->unit}. Set its kg per {$material->unit} on Raw Materials before using it in a recipe."]);
            }
            if (! $material || ! $material->is_active
                || ! is_numeric($quantity) || ! preg_match('/^\d+(?:\.\d{1,4})?$/', (string) $quantity) || $quantity <= 0 || $quantity > 100 || isset($ingredients[$material->id])) {
                throw ValidationException::withMessages(['items' => 'Use distinct active raw materials, each with a positive kg per 100 kg.']);
            }
            $total = bcadd($total, (string) $quantity, 4);
            $ingredients[$material->id] = ['raw_material_id' => $material->id, 'name' => $material->name,
                'unit' => 'kg', 'quantity_per_100kg' => (string) $quantity];
        }
        if ($ingredients === [] || bccomp($total, '100', 4) !== 0) {
            throw ValidationException::withMessages(['items' => 'Recipe ingredients must total exactly 100 kg per 100 kg (100%).']);
        }

        return array_values($ingredients);
    }

    /**
     * Turn "kg of each ingredient in a batch" into kg per 100 kg.
     *
     * The batch is whatever the quantities add up to, so a mixing sheet for a
     * 1,000 kg batch, a 250 kg batch or a single bag can be typed as it is.
     * Shares are rounded to 4 decimals and any rounding remainder goes to the
     * largest ingredient, so the recipe totals exactly 100 kg.
     *
     * @param  list<array{raw_material_id: mixed, quantity_kg: mixed}>  $items
     * @return list<array{raw_material_id: mixed, quantity_per_100kg: string}>
     */
    public function percentagesFromBatch(array $items): array
    {
        $items = array_values($items);
        $total = '0';
        foreach ($items as $item) {
            $kg = $item['quantity_kg'] ?? null;
            if (! is_numeric($kg) || ! preg_match('/^\d+(?:\.\d{1,4})?$/', (string) $kg) || bccomp((string) $kg, '0', 4) <= 0) {
                throw ValidationException::withMessages(['items' => 'Enter a positive kg for each ingredient in the batch.']);
            }
            $total = bcadd($total, (string) $kg, 4);
        }
        if ($items === [] || bccomp($total, '0', 4) <= 0) {
            throw ValidationException::withMessages(['items' => 'Add the ingredients in the batch.']);
        }

        $out = [];
        $sum = '0';
        $largest = 0;
        foreach ($items as $i => $item) {
            // Round half up at 4 decimals: compute at 6, then round.
            $share = bcdiv(bcmul((string) $item['quantity_kg'], '100', 6), $total, 6);
            $share = bcadd($share, '0.00005', 6);
            $share = bcadd($share, '0', 4);
            $out[$i] = ['raw_material_id' => $item['raw_material_id'] ?? null, 'quantity_per_100kg' => $share];
            $sum = bcadd($sum, $share, 4);
            if (bccomp((string) $item['quantity_kg'], (string) $items[$largest]['quantity_kg'], 4) > 0) {
                $largest = $i;
            }
        }
        $out[$largest]['quantity_per_100kg'] = bcadd($out[$largest]['quantity_per_100kg'], bcsub('100', $sum, 4), 4);

        return array_values($out);
    }

    public function resolve(FeedType $feedType, string $date): array
    {
        if (! $feedType->is_active) {
            throw ValidationException::withMessages(['feed_type_id' => 'This feed type is inactive.']);
        }
        // The newest saved revision wins within an effective date. Existing
        // production plans and batches retain their original snapshots.
        $version = FeedRecipeVersion::where('organization_id', $feedType->organization_id)->where('feed_type_id', $feedType->id)
            ->whereDate('effective_from', '<=', $date)->latest('effective_from')->latest('id')->first();
        if ($version) {
            if (! $version->is_active) {
                throw ValidationException::withMessages(['feed_type_id' => 'No active recipe applies on this production date.']);
            }

            return ['version_id' => $version->id, 'effective_from' => $version->effective_from->toDateString(),
                'ingredients' => $this->validateIngredients((int) $feedType->organization_id, $version->ingredients)];
        }
        if ($feedType->recipes()->doesntExist()) {
            throw ValidationException::withMessages(['feed_type_id' => 'This feed type does not have a recipe yet.']);
        }

        return ['version_id' => null, 'effective_from' => null, 'legacy' => true,
            'ingredients' => $this->validateIngredients((int) $feedType->organization_id, $feedType->recipes()->get()->toArray())];
    }

    public function save(User $actor, array $data): FeedRecipeVersion
    {
        abort_unless($actor->hasPermission('manage_feed_production'), 403);
        $date = $data['effective_from'] ?? now()->toDateString();
        Validator::make(['effective_from' => $date], ['effective_from' => ['required', 'date_format:Y-m-d', 'after_or_equal:today']])->validate();

        return DB::transaction(function () use ($actor, $data, $date) {
            Organization::whereKey($actor->organization_id)->lockForUpdate()->firstOrFail();
            $type = FeedType::where('organization_id', $actor->organization_id)->where('is_active', true)->findOrFail($data['feed_type_id']);
            $ingredients = $this->validateIngredients((int) $actor->organization_id, $data['items']);
            $version = FeedRecipeVersion::create(['organization_id' => $actor->organization_id, 'feed_type_id' => $type->id,
                'effective_from' => $date, 'is_active' => $data['is_active'] ?? true, 'ingredients' => $ingredients, 'created_by' => $actor->id]);
            // Preserve legacy recipe rows. All new calculations resolve versions;
            // old batches continue to use their already-recorded batch items.
            app(FeedConfigurationService::class)->audit($actor, 'feed_recipe_version_created', $version, [], $version->toArray(), 'New recipe version; earlier recipes preserved.');

            return $version;
        }, 3);
    }
}
