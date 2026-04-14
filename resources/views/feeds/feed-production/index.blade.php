<x-app-layout>
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
            </div>

            <button type="button"
                    @click="openModal = 'feed-type'"
                    class="inline-flex items-center justify-center px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-xl shadow-sm transition">
                + Add Feed Type
            </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
                <p class="text-sm font-medium text-gray-500">Feed Types</p>
                <h3 class="mt-2 text-3xl font-bold text-slate-800">{{ $stats['feed_types'] ?? 0 }}</h3>
                <p class="mt-1 text-xs text-gray-400">Available feed products</p>
            </div>

            <div class="rounded-2xl border border-blue-100 bg-white shadow-sm p-5">
                <p class="text-sm font-medium text-gray-500">Recipe Lines</p>
                <h3 class="mt-2 text-3xl font-bold text-blue-700">{{ $stats['recipes'] ?? 0 }}</h3>
                <p class="mt-1 text-xs text-gray-400">Configured recipe ingredients</p>
            </div>

            <div class="rounded-2xl border border-amber-100 bg-white shadow-sm p-5">
                <p class="text-sm font-medium text-gray-500">Batches Mixed</p>
                <h3 class="mt-2 text-3xl font-bold text-amber-700">{{ $stats['batches'] ?? 0 }}</h3>
                <p class="mt-1 text-xs text-gray-400">Total recorded feed batches</p>
            </div>

            <div class="rounded-2xl border border-emerald-100 bg-white shadow-sm p-5">
                <p class="text-sm font-medium text-gray-500">Total Feed Produced</p>
                <h3 class="mt-2 text-3xl font-bold text-emerald-700">{{ number_format($stats['total_feed_produced'] ?? 0, 2) }} kg</h3>
                <p class="mt-1 text-xs text-gray-400">Cumulative production output</p>
            </div>
        </div>

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

                                <button type="button"
                                        @click="
                                            selectedFeedTypeId = '{{ $feedType->id }}';
                                            recipeItems = [
                                                @foreach($feedType->recipes as $recipe)
                                                    {
                                                        raw_material_id: '{{ $recipe->raw_material_id }}',
                                                        quantity_per_100kg: '{{ (float) $recipe->quantity_per_100kg }}'
                                                    },
                                                @endforeach
                                                @if($feedType->recipes->isEmpty())
                                                    { raw_material_id: '', quantity_per_100kg: '' }
                                                @endif
                                            ];
                                            openModal = 'recipe';
                                        "
                                        class="inline-flex items-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 transition">
                                    Set Recipe
                                </button>
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
                                        @forelse($feedType->recipes as $recipe)
                                            <tr>
                                                <td class="px-4 py-3 text-sm font-medium text-gray-800">
                                                    {{ $recipe->rawMaterial->name ?? '—' }}
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-700">
                                                    {{ number_format($recipe->quantity_per_100kg, 2) }} kg
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="2" class="px-4 py-5 text-center text-sm text-gray-500">
                                                    No recipe has been configured yet.
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
                            <button type="button"
                                    @click="openModal = 'feed-type'"
                                    class="mt-4 inline-flex items-center rounded-xl bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700 transition">
                                + Add Feed Type
                            </button>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h2 class="text-base font-semibold text-gray-800">Mix Feed</h2>
                    <p class="text-sm text-gray-500 mt-1">Create a production batch from an existing recipe.</p>
                </div>

                <form action="{{ route('feed-production.mix') }}" method="POST" class="p-6 space-y-5">
                    @csrf

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Feed Type</label>
                        <select name="feed_type_id"
                                class="w-full rounded-xl border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                                required>
                            <option value="">Select feed type</option>
                            @foreach($feedTypes as $feedType)
                                <option value="{{ $feedType->id }}">{{ $feedType->name }}</option>
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
                        Mix Feed Batch
                    </button>
                </form>
            </div>
        </div>

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
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse($feedBatches as $batch)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    {{ optional($batch->batch_date)->format('M d, Y') }}
                                </td>

                                <td class="px-6 py-4">
                                    <div class="font-semibold text-gray-800">{{ $batch->feedType->name ?? '—' }}</div>
                                    @if($batch->notes)
                                        <div class="text-xs text-gray-400 mt-1 line-clamp-1">{{ $batch->notes }}</div>
                                    @endif
                                </td>

                                <td class="px-6 py-4 text-sm font-semibold text-gray-800">
                                    {{ number_format($batch->batch_size_kg, 2) }} kg
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
                                                — {{ number_format($item->quantity_used, 2) }} kg
                                            </div>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
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
        <div x-show="openModal === 'recipe'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <div @click.away="openModal = null" class="w-full max-w-3xl rounded-2xl bg-white shadow-2xl overflow-hidden">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-800">Set Feed Recipe</h3>
                    <button type="button" @click="openModal = null" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
                </div>

                <form action="{{ route('feed-production.recipes.store') }}" method="POST" class="p-6 space-y-5">
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
</x-app-layout>