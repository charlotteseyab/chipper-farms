<?php

namespace App\Http\Controllers;

use App\Models\FeedBatch;
use App\Models\FeedType;
use App\Models\RawMaterial;
use App\Services\Feed\ProduceFeedBatchService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Feed production — feed types, recipes, and batch mixing.
 *
 * Phase E2 rewrite. mixFeed delegates to ProduceFeedBatchService,
 * fixing closure scope, missing imports, and missing event dispatch.
 */
class FeedProductionController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $feedTypes = FeedType::with(['recipes.rawMaterial'])
            ->latest()
            ->get();

        $currentRecipes = [];
        foreach ($feedTypes as $type) {
            try {
                $currentRecipes[$type->id] = app(\App\Services\Feed\FeedRecipeService::class)->resolve($type, now()->toDateString());
            } catch (\Illuminate\Validation\ValidationException $exception) {
                $currentRecipes[$type->id] = ['ingredients' => [], 'error' => $exception->validator->errors()->first()];
            }
        }
        $recipeVersions = \App\Models\FeedRecipeVersion::with('feedType')->orderByDesc('effective_from')->orderByDesc('id')->get();
        $rawMaterials = RawMaterial::orderBy('name')->get();

        $feedBatches = FeedBatch::with(['feedType', 'items.rawMaterial', 'mixer', 'reverser'])
            ->latest('batch_date')
            ->latest('id')
            ->paginate(10)->withQueryString();

        $stats = [
            'feed_types'          => FeedType::count(),
            'recipes'             => \App\Models\FeedTypeRecipe::count(),
            'batches'             => FeedBatch::whereNull('reversed_at')->count(),
            'total_feed_produced' => FeedBatch::whereNull('reversed_at')->sum('batch_size_kg'),
        ];

        $waitingPlans = \App\Models\FeedProductionPlan::with('feedType')->where('status', 'planned')
            ->oldest('production_date')->oldest('id')->paginate(10, ['*'], 'plans_page')->withQueryString();
        $earlierPlans = \App\Models\FeedProductionPlan::with('feedType')->where('status', '!=', 'planned')
            ->latest()->latest('id')->paginate(10, ['*'], 'earlier_plans_page')->withQueryString();
        $glance = app(\App\Services\Feed\FeedGlanceService::class)->production(app(\App\Support\CurrentOrganization::class)->requireId());

        return view('feeds.feed-production.index', compact('glance',
            'waitingPlans', 'earlierPlans', 'currentRecipes', 'recipeVersions',
            'feedTypes',
            'rawMaterials',
            'feedBatches',
            'stats'
        ));
    }

    public function storeFeedType(Request $request)
    {
        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:255', $this->uniqueFeedTypeName($request)],
            'notes' => ['nullable', 'string'],
            'wastage_percent' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:50'],
        ]);

        FeedType::create($validated + ['is_active' => true]);

        return back()->with('success', 'Feed type created successfully.');
    }

    public function updateFeedType(Request $request, FeedType $feedType)
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255', $this->uniqueFeedTypeName($request, $feedType)],
            'notes'     => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
            'wastage_percent' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:50'],
        ]);
        $validated['wastage_percent'] ??= null;

        $before = $feedType->only(['name', 'notes', 'is_active', 'wastage_percent']);
        DB::transaction(function () use ($request, $feedType, $validated, $before) {
            $feedType->update($validated);
            app(\App\Services\Feed\FeedConfigurationService::class)->audit($request->user(), 'feed_type_updated', $feedType,
                $before, $feedType->only(['name', 'notes', 'is_active', 'wastage_percent']), 'Feed type edited.');
        });

        return back()->with('success', $feedType->is_active ? 'Feed type saved.' : 'Feed type switched off. It can no longer be mixed or fed.');
    }

    public function reverseBatch(Request $request, FeedBatch $batch, \App\Services\Feed\FeedCorrectionService $corrections)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']]);

        return $this->correct(fn () => $corrections->reverseBatch($request->user(), $batch, $data['reason']),
            'Batch reversed. Its ingredients are back in stock and its finished feed removed.');
    }

    public function cancelPlan(Request $request, \App\Models\FeedProductionPlan $plan, \App\Services\Feed\FeedCorrectionService $corrections)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']]);

        return $this->correct(fn () => $corrections->cancelPlan($request->user(), $plan, $data['reason']), 'Production plan cancelled.');
    }

    private function correct(\Closure $action, string $success)
    {
        try {
            $action();
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\App\Finance\Exceptions\PostingException $e) {
            return back()->with('error', 'The accounts could not record this correction: '.$e->getMessage());
        }

        return back()->with('success', $success);
    }

    /** Feed type names are unique per farm, ignoring case and surrounding spaces. */
    private function uniqueFeedTypeName(Request $request, ?FeedType $ignore = null): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($request, $ignore) {
            $exists = FeedType::where('organization_id', $request->user()->organization_id)
                ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim((string) $value))])
                ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))
                ->exists();
            if ($exists) {
                $fail('A feed type with this name already exists.');
            }
        };
    }

    public function storeRecipe(Request $request)
    {
        // Ingredients are entered either as kg per 100 kg (a percentage) or as
        // kg in a batch, which is converted. Both are stored per 100 kg.
        $byBatch = $request->input('entry_mode') === 'batch';
        $validated = $request->validate([
            'feed_type_id'              => ['required', 'exists:feed_types,id'],
            'effective_from' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'is_active' => ['sometimes', 'boolean'],
            'entry_mode'                => ['nullable', 'in:percent,batch'],
            'items'                     => ['required', 'array', 'min:1'],
            'items.*.raw_material_id'   => ['required', 'exists:raw_materials,id'],
            'items.*.quantity_per_100kg'=> [$byBatch ? 'nullable' : 'required', 'numeric', 'decimal:0,4', 'min:0.0001'],
            'items.*.quantity_kg'       => [$byBatch ? 'required' : 'nullable', 'numeric', 'decimal:0,4', 'min:0.0001'],
        ]);

        $recipes = app(\App\Services\Feed\FeedRecipeService::class);
        if ($byBatch) {
            $validated['items'] = $recipes->percentagesFromBatch($validated['items']);
        }
        $recipes->save($request->user(), $validated);

        return back()->with('success', 'Recipe saved successfully.');
    }

    /**
     * Mix a feed batch from a recipe — produces a FeedBatch and updates
     * raw material stock + valuation in one transaction.
     */
    public function mixFeed(Request $request, ProduceFeedBatchService $service)
    {
        $validated = $request->validate([
            'submission_key' => ['nullable', 'uuid'],
            'feed_type_id'  => ['required', 'exists:feed_types,id'],
            'batch_date'    => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'batch_size_kg' => ['required', 'numeric', 'decimal:0,4', 'min:0.0001'],
            'notes'         => ['nullable', 'string'],
            'return'        => ['nullable', 'in:mix-buy'],
        ]);
        // Mixing from Mix & buy comes back to it, on the same period; the older mix form keeps its own page.
        $back = ($validated['return'] ?? null) === 'mix-buy'
            ? redirect()->route('feed.mix-buy', array_filter($request->only(['period', 'from', 'to', 'weeks']), fn ($v) => is_scalar($v) && (string) $v !== ''))
            : redirect()->route('feed-production.index');

        $key = $validated['submission_key'] ?? $request->session()->get('feed_mix_key', (string) \Illuminate\Support\Str::uuid());
        $request->session()->put('feed_mix_key', $key);
        try {
            $service->execute($request->user(), [
                'submission_key' => $key,
                'feed_type_id'  => (int) $validated['feed_type_id'],
                'batch_date'    => $validated['batch_date'],
                'batch_size_kg' => (float) $validated['batch_size_kg'],
                'notes'         => $validated['notes'] ?? null,
            ]);
        } catch (HttpExceptionInterface $exception) {
            if ($exception->getStatusCode() !== 422) {
                throw $exception;
            }

            return $back->withInput()->with('mix_error', $exception->getMessage());
        } catch (\App\Services\Finance\Inventory\InsufficientInventoryException $exception) {
            $name = \App\Models\RawMaterial::find($exception->itemId)?->name ?? 'an ingredient';

            return $back->withInput()->with('mix_error', sprintf('Not enough %s in the store: this mix needs %s kg, there is %s kg. Buy it first, or mix less.',
                $name, number_format($exception->requested, 2), number_format($exception->available, 2)));
        }

        $type = FeedType::find($validated['feed_type_id']);

        return $back->with('success', sprintf('Mixed %s kg of %s. The feed is in stock and the ingredients have come out of the store.',
            rtrim(rtrim(number_format((float) $validated['batch_size_kg'], 2), '0'), '.'), $type?->name ?? 'feed'));
    }
    public function plan(Request $request, \App\Services\Feed\FeedProductionPlanService $service)
    {
        $data = $request->validate([
            'feed_type_id' => ['required', 'integer'], 'production_date' => ['required', 'date_format:Y-m-d'],
            'planned_quantity_kg' => ['required', 'numeric', 'decimal:0,4', 'gt:0', 'max:1000000'], 'submission_key' => ['required', 'uuid'],
        ]);
        $service->create($request->user(), $data);
        return redirect()->route('feed-production.index')->with('success', 'Production planned. Stock is unchanged until confirmation.');
    }

    /**
     * Plan a mix for every feed the forecast says to produce.
     *
     * The forecast is worked out again here rather than trusting quantities
     * from the page. Each feed gets a key derived from the form's key, so a
     * double-submitted form plans nothing twice; a feed that already has a
     * plan waiting to be confirmed is left alone rather than planned again.
     */
    public function planAll(Request $request, \App\Services\Feed\FeedProductionPlanService $service)
    {
        $input = $request->validate([
            'planning_from' => ['required', 'date_format:Y-m-d'], 'planning_to' => ['required', 'date_format:Y-m-d'],
            'submission_key' => ['required', 'uuid'],
            'stage' => ['nullable', 'string'], 'group_id' => ['nullable', 'integer'], 'room_id' => ['nullable', 'integer'],
            'structure_id' => ['nullable', 'integer'], 'feed_type_id' => ['nullable', 'integer'],
        ]);
        $orgId = (int) $request->user()->organization_id;
        return DB::transaction(function () use ($orgId, $input, $service, $request) {
            \App\Models\Organization::whereKey($orgId)->lockForUpdate()->firstOrFail();
            // A bulk submission is atomic. Replay it before consulting today's
            // stock, rations or waiting plans, which may have changed since then.
            $keys = \App\Models\FeedType::where('organization_id', $orgId)->pluck('id')
                ->map(fn ($id) => \Ramsey\Uuid\Uuid::uuid5($input['submission_key'], 'feed:'.$id)->toString());
            $saved = \App\Models\FeedProductionPlan::where('organization_id', $orgId)
                ->whereIn('submission_key', $keys)->with('feedType')->get();
            if ($saved->isNotEmpty()) {
                return redirect()->route('feed-production.index')->with('success', 'Production submission already saved: '
                    .$saved->map(fn ($plan) => $plan->feedType->name.' '.\App\Support\Qty::kg($plan->planned_quantity_kg).' kg')->implode(', ').'.');
            }

            $forecast = app(\App\Services\Feed\FeedPlanningService::class)->calculate($orgId, $input['planning_from'], $input['planning_to'], $input);
            if (! $forecast['demand_complete']) {
                return back()->with('error', 'Resolve the feeding setup notices before planning mixes from this forecast.');
            }
            $waiting = \App\Models\FeedProductionPlan::where('organization_id', $orgId)->where('status', 'planned')->pluck('feed_type_id')->map(fn ($id) => (int) $id)->all();
            $planned = [];
            $skipped = [];
            foreach ($forecast['feeds'] as $feed) {
                if ($feed['production_kg'] <= 0 || ! isset($feed['recipe_snapshot'])) {
                    continue;
                }
                if (in_array((int) $feed['feed_type_id'], $waiting, true)) {
                    $skipped[] = $feed['name'];

                    continue;
                }
                $key = \Ramsey\Uuid\Uuid::uuid5($input['submission_key'], 'feed:'.$feed['feed_type_id'])->toString();
                $plan = $service->create($request->user(), [
                    'feed_type_id' => $feed['feed_type_id'], 'production_date' => $forecast['from'],
                    'planned_quantity_kg' => $feed['production_kg'], 'submission_key' => $key,
                ]);
                $planned[] = $feed['name'].' '.\App\Support\Qty::kg($plan->planned_quantity_kg).' kg';
            }
            if ($planned === []) {
                return back()->with('error', $skipped === []
                    ? 'Nothing needs producing for this period.'
                    : 'Every feed that needs producing already has a plan waiting to be confirmed: '.implode(', ', $skipped).'.');
            }

            return redirect()->route('feed-production.index')->with('success', 'Production planned: '.implode(', ', $planned).'.'
                .($skipped === [] ? '' : ' Already planned, left as they are: '.implode(', ', $skipped).'.').' Stock is unchanged until each mix is confirmed.');
        }, 3);
    }

    public function completePlan(Request $request, \App\Models\FeedProductionPlan $plan, \App\Services\Feed\FeedProductionPlanService $service)
    {
        $data = $request->validate([
            'batch_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'finished_quantity_kg' => ['required', 'numeric', 'decimal:0,4', 'gt:0', 'max:1000000'],
            'actual_quantities' => ['required', 'array'], 'actual_quantities.*' => ['required', 'numeric', 'decimal:0,4', 'gt:0', 'max:1000000'],
        ]);
        try {
            $service->complete($request->user(), $plan, $data);
        } catch (HttpExceptionInterface $exception) {
            if ($exception->getStatusCode() !== 422) { throw $exception; }
            return back()->with('mix_error', $exception->getMessage())->withInput();
        }
        return back()->with('success', 'Production confirmed; inventory posted once.');
    }

}
