<x-app-layout>
<div class="max-w-7xl mx-auto py-2 px-4 sm:px-6 lg:px-8">

    <div class="mb-8 grid grid-cols-1 md:grid-cols-3 gap-4">
        
        <div class="bg-white rounded-lg shadow-md p-4 border border-gray-100 transition duration-300 hover:shadow-lg">
            <div class="text-xs font-medium text-gray-500 mb-1">Total Raw Feed Items</div>
            <div class="text-3xl font-extrabold text-gray-900 leading-snug">{{ isset($ingredients) ? $ingredients->count() : 0 }}</div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-4 border border-gray-100 transition duration-300 hover:shadow-lg">
            <div class="text-xs font-medium text-gray-500 mb-1">Tracked Feed Types (Stock)</div>
            <div class="text-3xl font-extrabold text-gray-900 leading-snug">{{ isset($inventories) ? $inventories->count() : 0 }}</div>
        </div>

        @php($lowCount = isset($ingredientInventoryMap) ? $ingredientInventoryMap->filter(fn($i) => (float)($i->quantity ?? 0) <= 0)->count() : 0)
        <div class="bg-white rounded-lg shadow-md p-4 border border-gray-100 transition duration-300 hover:shadow-lg">
            <div class="text-xs font-medium text-gray-500 mb-1">Low/No Stock Items</div>
            <div class="text-3xl font-extrabold leading-snug {{ $lowCount > 0 ? 'text-red-600' : 'text-green-600' }}">{{ $lowCount }}</div>
            <p class="text-sm {{ $lowCount > 0 ? 'text-red-400' : 'text-gray-400' }} mt-1">Urgent inventory review needed</p>
        </div>
    </div>
    
    <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-gray-200 pb-3 mb-4">
        <div class="flex space-x-2 mb-3 sm:mb-0">
            <button id="tab-feed" class="px-4 py-1.5 rounded-full text-sm font-bold transition duration-150 ease-in-out">Feed Types Stock</button>
            <button id="tab-ingredients" class="px-4 py-1.5 rounded-full text-sm font-bold transition duration-150 ease-in-out">Raw Feed Items</button>
        </div>

        <div class="flex gap-2">
            <a id="btn-add-ingredient" href="{{ route('ingredients.create') }}" class="inline-flex items-center bg-green-600 text-white px-3 py-1.5 rounded-full text-sm shadow hover:bg-green-700 font-semibold transition" style="display:none;">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                Add Item
            </a>
        </div>
    </div>

    <div id="panel-feed">
        <div class="bg-white rounded-lg shadow-lg p-4 border border-gray-50">
            <h2 class="text-lg font-semibold text-gray-800 mb-3">
                Current Feed Stock (Manufactured)
            </h2>
            <div class="overflow-x-auto">
                <table class="min-w-full table-auto border border-gray-200 rounded-lg overflow-hidden">
                    <thead class="bg-gray-50 sticky top-0 z-10">
                        <tr>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold text-gray-600 uppercase tracking-wider">Feed Type</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold text-gray-600 uppercase tracking-wider">Protein %</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold text-gray-600 uppercase tracking-wider">Energy (kcal/kg)</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold text-gray-600 uppercase tracking-wider">On-hand Stock</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold text-gray-600 uppercase tracking-wider">Last Updated</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        @forelse($inventories as $inv)
                        @php($ft = App\Models\FeedType::with('recipe.items.ingredient')->find($inv->feed_type_id))
                        <tr class="hover:bg-indigo-50/40 transition duration-150 ease-in-out">
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-gray-900">{{ $ft->name ?? $inv->feed_type_id }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">{{ number_format($ft->computed_protein_percent ?? 0, 2) }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">{{ number_format($ft->computed_energy_kcal_per_kg ?? 0, 0) }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @php($isLow = (float)$inv->quantity <= 0)
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold
                                    {{ $isLow ? 'bg-red-50 text-red-700 ring-1 ring-red-200' : 'bg-blue-50 text-blue-700 ring-1 ring-blue-200' }}">
                                    {{ number_format($inv->quantity, 2) }} {{ $inv->unit }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{{ $inv->last_updated ? $inv->last_updated->format('Y-m-d') : '-' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center bg-gray-50">
                                <div class="flex flex-col items-center">
                                    <svg class="w-12 h-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                                    <span class="text-gray-500 text-lg font-semibold">
                                        No Manufactured Feed Stock Tracked
                                    </span>
                                    <p class="text-sm text-gray-500 mt-1">
                                        To track stock, first ensure you have defined <a href="{{ route('feed-types.index') }}" class="text-indigo-600 hover:text-indigo-800 font-medium">Feed Types</a>.
                                    </p>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="panel-ingredients" style="display:none;">
        <div class="bg-white rounded-lg shadow-xl p-4 border border-gray-100">
            <h2 class="text-lg font-semibold text-gray-800 mb-3">
                Raw Feed Items Catalog + On-hand Inventory
            </h2>
            <div class="overflow-x-auto">
                <table class="min-w-full table-auto border border-gray-200 rounded-lg overflow-hidden">
                    <thead class="bg-gray-50 sticky top-0 z-10">
                        <tr>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold text-gray-600 uppercase tracking-wider">Feed Item</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold text-gray-600 uppercase tracking-wider">Unit Cost (₵)</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold text-gray-600 uppercase tracking-wider">Protein %</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold text-gray-600 uppercase tracking-wider">Energy (kcal/kg)</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold text-gray-600 uppercase tracking-wider">Bag Weight (kg)</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold text-gray-600 uppercase tracking-wider">On-hand Qty</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold text-gray-600 uppercase tracking-wider">Unit</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold text-gray-600 uppercase tracking-wider">Last Stock Update</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        @forelse($ingredients as $ing)
                        @php($inv = $ingredientInventoryMap[$ing->id] ?? null)
                        <tr class="hover:bg-indigo-50/40 transition duration-150 ease-in-out">
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-gray-900">{{ $ing->name }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">₵{{ number_format($ing->cost_per_unit, 2) }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">{{ $ing->protein_percentage ?? 'N/A' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">{{ $ing->energy_kcal_per_kg ?? 'N/A' }}</td>
                            
                            {{-- BAG WEIGHT DISPLAY (EDITS APPLIED HERE) --}}
                            <td class="px-3 py-3 text-sm align-top">
                                @if($ing->unit === 'bag' || $ing->unit === 'bags')
                                    @php($bagWeight = (float)($ing->bag_weight_kg ?? 0))
                                    @php($bags = $inv ? (float)$inv->quantity : 0)
                                    
                                    @if($bagWeight > 0 && $bags > 0)
                                        @php($totalKg = $bagWeight * $bags)
                                        
                                        <div class="flex flex-col text-xs font-medium text-indigo-700 bg-indigo-50/60 rounded-md p-2 ring-1 ring-indigo-100">
                                            <span class="text-gray-700 font-semibold text-[13px]">
                                                {{ number_format($bagWeight, 2) }} kg/bag
                                            </span>
                                            <span class="text-[13px] text-gray-700">
                                                &times; {{ number_format($bags, 2) }} bags
                                            </span>
                                            <span class="mt-1 pt-1 border-t border-indigo-200 text-sm font-bold text-indigo-700">
                                                {{ number_format($totalKg, 2) }} kg Total
                                            </span>
                                        </div>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded bg-gray-100 text-gray-700 text-xs font-medium">{{ number_format($bagWeight, 2) }} kg/bag</span>
                                    @endif
                                @else
                                    <span class="text-gray-400">N/A</span>
                                @endif
                            </td>
                            {{-- END BAG WEIGHT DISPLAY --}}

                            @php($qty = $inv ? (float)$inv->quantity : 0)
                            <td class="px-4 py-3 whitespace-nowrap">
                                @php($isLow = $qty <= 0)
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold {{ $isLow ? 'bg-red-50 text-red-700 ring-1 ring-red-200' : 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' }}">
                                    {{ number_format($qty, 2) }}
                                </span>
                            </td>
                            
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 text-xs font-medium">{{ $ing->unit }}</span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{{ $inv && $inv->last_updated ? $inv->last_updated->format('Y-m-d') : '-' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium flex items-center gap-2">
                                <a href="{{ route('ingredients.edit', $ing) }}" class="text-indigo-600 hover:text-indigo-900 p-1 rounded hover:bg-indigo-100 transition">Edit</a>
                                <form method="POST" action="{{ route('ingredients.destroy', $ing) }}" class="inline" onsubmit="return confirm('Are you sure you want to delete the feed item: {{ $ing->name }}?')">
                                    @csrf @method('DELETE')
                                    <button class="text-red-600 hover:text-red-900 p-1 rounded hover:bg-red-100 transition">Delete</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center bg-gray-50">
                                <div class="flex flex-col items-center">
                                    <svg class="w-12 h-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                    <span class="text-gray-500 text-lg font-semibold">
                                        No Raw Feed Items Defined
                                    </span>
                                    <p class="text-sm text-gray-500 mt-1">
                                        You need to define raw ingredients before you can track their inventory.
                                    </p>
                                    <a id="btn-add-ingredient-empty" href="{{ route('ingredients.create') }}" class="mt-4 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-full shadow-sm text-white bg-green-600 hover:bg-green-700 transition">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                        Add Raw Feed Item
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<x-copyright-minimal />
<script>

document.addEventListener('DOMContentLoaded', function () {
const tabFeed = document.getElementById('tab-feed');
const tabIng = document.getElementById('tab-ingredients');
const panelFeed = document.getElementById('panel-feed');
const panelIng = document.getElementById('panel-ingredients');
const btnIngredient = document.getElementById('btn-add-ingredient');

// Defines the styles for active and inactive tabs (adjusted for smaller size)
const activeClass = 'bg-indigo-600 text-white shadow-md';
const inactiveClass = 'bg-gray-100 text-gray-800 hover:bg-gray-200';

function activate(tab) {
    localStorage.setItem('activeTab', tab); // save chosen tab

    const activeIsFeed = tab === 'feed';
    panelFeed.style.display = activeIsFeed ? 'block' : 'none';
    panelIng.style.display = activeIsFeed ? 'none' : 'block';

    // Apply styles based on active tab
    tabFeed.className = `px-4 py-1.5 rounded-full text-sm font-bold transition duration-150 ease-in-out ${activeIsFeed ? activeClass : inactiveClass}`;
    tabIng.className = `px-4 py-1.5 rounded-full text-sm font-bold transition duration-150 ease-in-out ${!activeIsFeed ? activeClass : inactiveClass}`;

    // Toggle 'Add Feed Item' button
    if (btnIngredient) {
        btnIngredient.style.display = activeIsFeed ? 'none' : 'inline-flex';
    }
}

// Restore saved tab after reload
const savedTab = localStorage.getItem('activeTab') || 'feed';
activate(savedTab);

// Handle clicks
tabFeed.addEventListener('click', () => activate('feed'));
tabIng.addEventListener('click', () => activate('ingredients'));

});
</script>

</x-app-layout>