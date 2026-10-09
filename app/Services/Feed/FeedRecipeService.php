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

    public function resolve(FeedType $feedType, string $date): array
    {
        if (! $feedType->is_active) {
            throw ValidationException::withMessages(['feed_type_id' => 'This feed type is inactive.']);
        }
        $version = FeedRecipeVersion::where('organization_id', $feedType->organization_id)->where('feed_type_id', $feedType->id)
            ->whereDate('effective_from', '<=', $date)->latest('effective_from')->first();
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
            if (FeedRecipeVersion::where('feed_type_id', $type->id)->whereDate('effective_from', $date)->exists()) {
                throw ValidationException::withMessages(['effective_from' => 'This recipe already has a version on that date. Add a later version.']);
            }
            $version = FeedRecipeVersion::create(['organization_id' => $actor->organization_id, 'feed_type_id' => $type->id,
                'effective_from' => $date, 'is_active' => $data['is_active'] ?? true, 'ingredients' => $ingredients, 'created_by' => $actor->id]);
            // Preserve legacy recipe rows. All new calculations resolve versions;
            // old batches continue to use their already-recorded batch items.
            app(FeedConfigurationService::class)->audit($actor, 'feed_recipe_version_created', $version, [], $version->toArray(), 'New recipe version; earlier recipes preserved.');

            return $version;
        }, 3);
    }
}
