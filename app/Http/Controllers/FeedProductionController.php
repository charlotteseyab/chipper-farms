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
        $recipeVersions = \App\Models\FeedRecipeVersion::with('feedType')->orderByDesc('effective_from')->get();
        $rawMaterials = RawMaterial::orderBy('name')->get();

        $feedBatches = FeedBatch::with(['feedType', 'items.rawMaterial', 'mixer', 'reverser'])
            ->latest('batch_date')
            ->latest('id')
            ->paginate(10);

        $stats = [
            'feed_types'          => FeedType::count(),
            'recipes'             => \App\Models\FeedTypeRecipe::count(),
            'batches'             => FeedBatch::whereNull('reversed_at')->count(),
            'total_feed_produced' => FeedBatch::whereNull('reversed_at')->sum('batch_size_kg'),
        ];

        $productionPlans = \App\Models\FeedProductionPlan::with('feedType')->latest()->limit(30)->get();
        return view('feeds.feed-production.index', compact(
            'productionPlans', 'currentRecipes', 'recipeVersions',
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
        ]);

        $before = $feedType->only(['name', 'notes', 'is_active']);
        DB::transaction(function () use ($request, $feedType, $validated, $before) {
            $feedType->update($validated);
            app(\App\Services\Feed\FeedConfigurationService::class)->audit($request->user(), 'feed_type_updated', $feedType,
                $before, $feedType->only(['name', 'notes', 'is_active']), 'Feed type edited.');
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
        $validated = $request->validate([
            'feed_type_id'              => ['required', 'exists:feed_types,id'],
            'effective_from' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'is_active' => ['sometimes', 'boolean'],
            'items'                     => ['required', 'array', 'min:1'],
            'items.*.raw_material_id'   => ['required', 'exists:raw_materials,id'],
            'items.*.quantity_per_100kg'=> ['required', 'numeric', 'decimal:0,4', 'min:0.0001'],
        ]);

        app(\App\Services\Feed\FeedRecipeService::class)->save($request->user(), $validated);

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
        ]);

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

            return redirect()
                ->route('feed-production.index')
                ->withInput()
                ->with('mix_error', $exception->getMessage());
        }

        return redirect()
            ->route('feed-production.index')
            ->with('success', 'Feed mixed successfully and raw material stock updated.');
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
