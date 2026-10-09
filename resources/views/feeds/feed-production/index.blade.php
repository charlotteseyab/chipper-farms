<x-app-layout>
    <x-feed-hub hub="mill" />
    <div class="max-w-7xl mx-auto py-6 px-4"
         x-data="{
            openModal: null,
            selectedFeedTypeId: '',
            recipeItems: [{ raw_material_id: '', quantity_per_100kg: '' }]
         }">

        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Feed Production</h1>
                <p class="text-sm text-gray-500">Manage feed types, recipes, production batches, and material usage.</p>
                <a href="{{ route('feed-setup.index') }}" class="mt-1 inline-block text-sm font-semibold text-emerald-700 underline">Feed setup checklist</a>
            </div>

            @if(auth()->user()->hasPermission('manage_feed_production'))
            <button type="button"
                    @click="openModal = 'feed-type'"
                    class="inline-flex items-center justify-center px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-xl shadow-sm transition">
                + Add Feed Type
            </button>
            @endif
        </div>

        @if(session('mix_error'))
            <div role="alert" class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-5 shadow-sm">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-700">
                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h2 class="font-bold text-red-900">Batch not created</h2>
                        <p class="mt-1 text-sm font-medium leading-6 text-red-800">{{ session('mix_error') }}</p>
                        <p class="mt-1 text-xs text-red-700">No raw-material quantities were changed. Receive or adjust the required stock, then try again.</p>
                    </div>
                </div>
            </div>
        @endif

        @if($errors->any())<ul class="mb-4 rounded bg-red-50 p-4 text-red-700">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
        @if(session('success'))<p class="mb-4 rounded bg-green-50 p-4 text-green-800">{{ session('success') }}</p>@endif
    <section class="space-y-4 mb-6"><h2 class="text-xl font-bold">Production plans</h2><p class="text-sm text-slate-500">Review the ingredients and finished quantity, then confirm once production is complete.</p>
        @forelse($productionPlans as $plan)<details class="rounded-2xl border bg-white p-4 space-y-3" @if($loop->first && $plan->status==='planned') open @endif><summary class="font-semibold cursor-pointer">Plan #{{ $plan->id }} · {{ $plan->feedType?->name }} · {{ $plan->planned_quantity_kg }} kg · {{ ['planned' => 'Awaiting production', 'completed' => 'Completed', 'cancelled' => 'Cancelled'][$plan->status] ?? $plan->status }}</summary><p>Recipe {{ $plan->recipe_snapshot['version_id'] ?? 'legacy snapshot' }} · Planned {{ $plan->production_date->toDateString() }}</p>
            @if($plan->status==='planned' && auth()->user()->hasPermission('manage_feed_production'))
            <form method="POST" action="{{ route('feed-production.plans.complete',$plan) }}" class="space-y-3">@csrf
                <div class="grid sm:grid-cols-3 gap-3"><label>Production date<input name="batch_date" type="date" max="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}" required class="block rounded border-slate-300"></label><label>Finished feed produced (kg)<input name="finished_quantity_kg" type="number" min="0.0001" step="0.0001" value="{{ $plan->planned_quantity_kg }}" required class="block rounded border-slate-300"></label></div>
                @foreach($plan->recipe_snapshot['ingredients'] as $ingredient)<label class="block">{{ $ingredient['name'] }} — expected {{ number_format($plan->planned_quantity_kg*$ingredient['quantity_per_100kg']/100,4) }} kg; actual used (kg)<input name="actual_quantities[{{ $ingredient['raw_material_id'] }}]" type="number" min="0.0001" step="0.0001" value="{{ $plan->planned_quantity_kg*$ingredient['quantity_per_100kg']/100 }}" required class="block rounded border-slate-300"></label>@endforeach
                <button class="rounded bg-emerald-700 px-4 py-2 text-white">Confirm production and post stock</button>
            </form>
            <details class="rounded-xl border border-red-100 p-3"><summary class="cursor-pointer text-sm font-semibold text-red-700">Not making this? Cancel the plan</summary>
                <form method="POST" action="{{ route('feed-production.plans.cancel', $plan) }}" class="mt-3 flex flex-wrap items-end gap-3">@csrf
                    <label class="flex-1 text-sm">Reason<input name="reason" required minlength="5" maxlength="2000" class="mt-1 block w-full rounded border-slate-300 text-sm"></label>
                    <button class="rounded bg-red-600 px-4 py-2 text-sm font-semibold text-white">Cancel plan</button>
                </form>
            </details>
            @elseif($plan->status === 'cancelled')<p class="text-sm text-slate-500">Cancelled {{ $plan->cancelled_at?->format('j M Y') }}: {{ $plan->cancellation_reason }}</p>
            @elseif($plan->feed_batch_id)<p>Completed as batch #{{ $plan->feed_batch_id }}. Inventory already posted.</p>@endif
        </details>@empty<p>No production plans. Create one from Feed Planning when finished feed is short.</p>@endforelse
    </section>
        <details class="mb-6 rounded-2xl border bg-white p-4" @if($errors->any() || session('mix_error')) open @endif><summary class="cursor-pointer font-semibold mb-4">Feed types, recipes and direct mixing</summary>
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
            <div class="xl:col-span-2 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h2 class="text-base font-semibold text-gray-800">Feed Types & Recipes</h2>
                    <p class="text-sm text-gray-500 mt-1">Define each feed type and configure its ingredient recipe per 100kg.</p>
                </div>

                <div class="p-6 space-y-6 max-h-[800px] overflow-y-auto">
                    @forelse($feedTypes as $feedType)
                        <div class="rounded-2xl border border-gray-100 bg-gray-50/50 p-5">
                            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-800">{{ $feedType->name }}</h3>
                                    <p class="mt-1 text-sm text-gray-500">{{ $feedType->notes ?: 'No notes added for this feed type.' }}</p>
                                </div>

                                @if(auth()->user()->hasPermission('manage_feed_production'))
                                <button type="button"
                                        @click="
                                            selectedFeedTypeId = '{{ $feedType->id }}';
                                            recipeItems = [
                                                @foreach($currentRecipes[$feedType->id]['ingredients'] as $recipe)
                                                    {
                                                        raw_material_id: '{{ $recipe['raw_material_id'] }}',
                                                        quantity_per_100kg: '{{ (float) $recipe['quantity_per_100kg'] }}'
                                                    },
                                                @endforeach
                                                @if(empty($currentRecipes[$feedType->id]['ingredients']))
                                                    { raw_material_id: '', quantity_per_100kg: '' }
                                                @endif
                                            ];
                                            openModal = 'recipe';
                                        "
                                        class="inline-flex items-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 transition">
                                    Set Recipe
                                </button>
                                @endif
                            </div>

                            <div class="mt-4 overflow-x-auto max-h-64 overflow-y-auto rounded-xl border border-gray-100">
                                <table class="min-w-full divide-y divide-gray-200 rounded-xl overflow-hidden">
                                    <thead class="bg-white sticky top-0 z-10">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Ingredient</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Qty per 100kg</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 bg-white">
                                        @forelse($currentRecipes[$feedType->id]['ingredients'] as $recipe)
                                            <tr>
                                                <td class="px-4 py-3 text-sm font-medium text-gray-800">
                                                    {{ $recipe['name'] ?? '—' }}
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-700">
                                                    {{ number_format($recipe['quantity_per_100kg'], 2) }} kg
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="2" class="px-4 py-5 text-center text-sm text-gray-500">
                                                    {{ $currentRecipes[$feedType->id]['error'] ?? 'No recipe has been configured yet.' }}
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-10 text-center">
                            <h3 class="text-base font-semibold text-gray-700">No feed types yet</h3>
                            <p class="mt-1 text-sm text-gray-500">Create your first feed type to begin recipe setup.</p>
                            @if(auth()->user()->hasPermission('manage_feed_production'))
                            <button type="button"
                                    @click="openModal = 'feed-type'"
                                    class="mt-4 inline-flex items-center rounded-xl bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700 transition">
                                + Add Feed Type
                            </button>
                            @endif
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h2 class="text-base font-semibold text-gray-800">Record a batch without a plan</h2>
                    <p class="text-sm text-gray-500 mt-1">For a batch without a plan. Submitting this form immediately deducts the recipe ingredients and adds finished feed.</p>
                </div>

                @if(auth()->user()->hasPermission('manage_feed_production'))
                <form action="{{ route('feed-production.mix') }}" method="POST" class="p-6 space-y-5">
                    <input type="hidden" name="submission_key" value="{{ old('submission_key', (string) \Illuminate\Support\Str::uuid()) }}">
                    @csrf

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Feed Type</label>
                        <select name="feed_type_id"
                                class="w-full rounded-xl border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                                required>
                            <option value="">Select feed type</option>
                            @foreach($feedTypes as $feedType)
                                <option value="{{ $feedType->id }}" @selected((string) old('feed_type_id') === (string) $feedType->id)>{{ $feedType->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Batch Date</label>
                        <input type="date"
                               name="batch_date"
                               value="{{ old('batch_date', now()->toDateString()) }}"
                               class="w-full rounded-xl border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                               required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Batch Size (kg)</label>
                        <input type="number"
                               step="0.01"
                               min="0.01"
                               name="batch_size_kg"
                               value="{{ old('batch_size_kg') }}"
                               placeholder="e.g. 100"
                               class="w-full rounded-xl border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                               required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                        <textarea name="notes"
                                  rows="4"
                                  class="w-full rounded-xl border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                                  placeholder="Optional notes for this batch">{{ old('notes') }}</textarea>
                    </div>

                    <button type="submit"
                            class="w-full inline-flex items-center justify-center rounded-xl bg-green-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-green-700 transition">
                        Confirm batch and update stock
                    </button>
                </form>
                @endif
            </div>
        </div>

        </details>
        <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-6 py-4">
                <h2 class="text-base font-semibold text-gray-800">Production Log</h2>
                <p class="text-sm text-gray-500 mt-1">Review mixed batches, feed type used, batch size, cost, and ingredients.</p>
            </div>

            <div class="overflow-x-auto overflow-y-auto max-h-[650px]">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50 sticky top-0 z-10">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Batch Date</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Feed Type</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Batch Size</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Total Cost</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Mixed By</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Ingredients Used</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse($feedBatches as $batch)
                            <tr class="hover:bg-gray-50 transition {{ $batch->isReversed() ? 'opacity-60' : '' }}">
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    {{ optional($batch->batch_date)->format('M d, Y') }}
                                    @if($batch->isReversed())<span class="mt-1 block w-fit rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">Reversed</span>@endif
                                </td>

                                <td class="px-6 py-4">
                                    <div class="font-semibold text-gray-800">{{ $batch->feedType->name ?? '—' }}</div>
                                    @if($batch->notes)
                                        <div class="text-xs text-gray-400 mt-1 line-clamp-1">{{ $batch->notes }}</div>
                                    @endif
                                </td>

                                <td class="px-6 py-4 text-sm font-semibold text-gray-800">
                                    {{ number_format($batch->batch_size_kg, 2) }} kg
                                    @if($batch->recipe_snapshot)<p class="text-xs text-slate-500">Planned {{ $batch->planned_quantity_kg }} kg · Recipe {{ $batch->recipe_snapshot['version_id'] ?? 'legacy snapshot' }}</p><details><summary>Recipe used / expected ingredients</summary>@foreach($batch->recipe_snapshot['ingredients'] as $ingredient)<p>{{ $ingredient['name'] }}: {{ $ingredient['quantity_per_100kg'] }}% · expected {{ number_format($batch->planned_quantity_kg * $ingredient['quantity_per_100kg']/100,4) }} kg</p>@endforeach</details>@else<p class="text-xs">Legacy batch — recorded actual ingredients retained.</p>@endif
                                </td>

                                <td class="px-6 py-4 text-sm font-semibold text-gray-800">
                                    {{ number_format($batch->total_cost, 2) }}
                                </td>

                                <td class="px-6 py-4 text-sm text-gray-700">
                                    {{ trim(($batch->mixer->first_name ?? '') . ' ' . ($batch->mixer->last_name ?? '')) ?: ($batch->mixer->name ?? 'System') }}
                                </td>

                                <td class="px-6 py-4">
                                    <div class="space-y-1">
                                        @foreach($batch->items as $item)
                                            <div class="text-xs text-gray-600">
                                                <span class="font-medium text-gray-800">{{ $item->rawMaterial->name ?? '—' }}</span>
                                                — {{ number_format($item->quantity_used, 2) }} {{ $item->rawMaterial->unit ?? 'kg' }}
                                            </div>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-6 py-4 align-top">
                                    @if($batch->isReversed())
                                        <p class="max-w-[14rem] text-xs text-slate-500">{{ $batch->reversed_at->format('j M Y') }} by {{ $batch->reverser?->name ?? '—' }}: {{ $batch->reversal_reason }}</p>
                                    @elseif(auth()->user()->hasPermission('manage_feed_production'))
                                        <details class="text-sm">
                                            <summary class="cursor-pointer font-semibold text-red-700">Reverse</summary>
                                            <form method="POST" action="{{ route('feed-production.batches.reverse', $batch) }}" class="mt-2 w-56 space-y-2">@csrf
                                                <p class="text-xs text-slate-500">Puts the ingredients back and removes the finished feed. Only possible before any of it is fed or sold.</p>
                                                <input name="reason" required minlength="5" maxlength="2000" placeholder="Reason" class="block w-full rounded border-slate-300 text-sm">
                                                <button class="rounded bg-red-600 px-3 py-1.5 text-xs font-semibold text-white">Reverse batch</button>
                                            </form>
                                        </details>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <div class="mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-6m3 6V7m3 10v-4m4 6H5a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v12a2 2 0 01-2 2z" />
                                            </svg>
                                        </div>
                                        <h3 class="text-base font-semibold text-gray-700">No production batches found</h3>
                                        <p class="mt-1 text-sm text-gray-500">Once you mix feed, batch history will appear here.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-5">
            {{ $feedBatches->links() }}
        </div>

        <!-- Add Feed Type Modal -->
        <div x-show="openModal === 'feed-type'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <div @click.away="openModal = null" class="w-full max-w-xl rounded-2xl bg-white shadow-2xl overflow-hidden">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-800">Add Feed Type</h3>
                    <button type="button" @click="openModal = null" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
                </div>

                <form action="{{ route('feed-production.feed-types.store') }}" method="POST" class="p-6 space-y-5">
                    @csrf

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Feed Type Name</label>
                        <input type="text"
                               name="name"
                               value="{{ old('name') }}"
                               class="w-full rounded-xl border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                               required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                        <textarea name="notes"
                                  rows="4"
                                  class="w-full rounded-xl border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                                  placeholder="Optional notes about this feed type">{{ old('notes') }}</textarea>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="openModal = null"
                                class="px-4 py-2.5 rounded-xl bg-gray-200 hover:bg-gray-300 text-sm font-semibold text-gray-800 transition">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2.5 rounded-xl bg-green-600 hover:bg-green-700 text-sm font-semibold text-white transition">
                            Save Feed Type
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Recipe Modal -->
        <div x-show="openModal === 'recipe'" x-cloak @click.self="openModal = null" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <div class="w-full max-w-3xl rounded-2xl bg-white shadow-2xl overflow-visible">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-800">Set Feed Recipe</h3>
                    <button type="button" @click="openModal = null" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
                </div>

                <form action="{{ route('feed-production.recipes.store') }}" method="POST" class="p-6 space-y-5">
                    <label class="block text-sm">Recipe effective from<input type="date" name="effective_from" min="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}" required class="block rounded border-slate-300"></label>
                    <label class="block text-sm">Status<select name="is_active" class="block rounded border-slate-300"><option value="1">Active</option><option value="0">Inactive</option></select></label><p class="text-sm text-slate-500">Save a new version. Ingredients must total 100 kg per 100 kg; previous production recipes remain unchanged.</p>
                    @csrf

                    <input type="hidden" name="feed_type_id" :value="selectedFeedTypeId">

                    <div class="space-y-4">
                        <template x-for="(item, index) in recipeItems" :key="index">
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end border border-gray-100 rounded-xl p-4 bg-gray-50/60">
                                <div class="md:col-span-7">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Raw Material</label>
                                    <select :name="`items[${index}][raw_material_id]`"
                                            x-model="item.raw_material_id"
                                            class="w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            required>
                                        <option value="">Select raw material</option>
                                        @foreach($rawMaterials as $material)
                                            <option value="{{ $material->id }}">{{ $material->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="md:col-span-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Qty per 100kg</label>
                                    <input type="number"
                                           step="0.01"
                                           min="0.01"
                                           :name="`items[${index}][quantity_per_100kg]`"
                                           x-model="item.quantity_per_100kg"
                                           class="w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                           required>
                                </div>

                                <div class="md:col-span-1">
                                    <button type="button"
                                            @click="recipeItems.length > 1 ? recipeItems.splice(index, 1) : null"
                                            class="w-full rounded-xl bg-red-100 px-3 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-200 transition">
                                        ×
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="flex items-center justify-between">
                        <button type="button"
                                @click="recipeItems.push({ raw_material_id: '', quantity_per_100kg: '' })"
                                class="inline-flex items-center rounded-xl bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-100 transition">
                            + Add Ingredient
                        </button>

                        <div class="flex gap-3">
                            <button type="button" @click="openModal = null"
                                    class="px-4 py-2.5 rounded-xl bg-gray-200 hover:bg-gray-300 text-sm font-semibold text-gray-800 transition">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-sm font-semibold text-white transition">
                                Save Recipe
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
        [x-cloak] { display: none !important; }
    </style>
    <section class="max-w-7xl mx-auto p-6 !pb-24"><details class="rounded-xl border bg-white p-4"><summary class="font-bold">Recipe version history</summary>
        @foreach($recipeVersions as $version)<article class="border-t py-3"><strong>{{ $version->feedType?->name }} · Version #{{ $version->id }}</strong><p>Effective {{ $version->effective_from->toDateString() }} · {{ $version->is_active ? 'Active' : 'Inactive' }}</p>@foreach($version->ingredients as $ingredient)<p>{{ $ingredient['name'] }}: {{ $ingredient['quantity_per_100kg'] }}%</p>@endforeach</article>@endforeach
    </details></section>
</x-app-layout>
