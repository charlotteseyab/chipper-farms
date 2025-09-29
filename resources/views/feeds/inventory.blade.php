<x-app-layout>
<div class="max-w-7xl mx-auto py-2 px-4 sm:px-6 lg:px-8">

    <!-- <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 leading-tight">
            Feed Inventory Dashboard 📦
        </h1>
        <p class="text-sm text-gray-500 mt-1">
            Overview of manufactured feed stock and raw feed item (ingredient) inventory.
        </p>
    </div> -->

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
            <!-- <a href="{{ route('feed-inventory.analytics') }}" class="inline-flex items-center bg-purple-600 text-white px-3 py-1.5 rounded-full text-sm shadow hover:bg-purple-700 font-semibold transition">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"></path></svg>
                Analytics
            </a> -->
            <a id="btn-add-ingredient" href="{{ route('ingredients.create') }}" class="inline-flex items-center bg-green-600 text-white px-3 py-1.5 rounded-full text-sm shadow hover:bg-green-700 font-semibold transition" style="display:none;">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                Add Item
            </a>
        </div>
    </div>

    <div id="panel-feed">
        <div class="bg-white rounded-lg shadow-xl p-4 border border-gray-100">
            <h2 class="text-lg font-semibold text-gray-800 mb-3">
                Current Feed Stock (Manufactured)
            </h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Feed Type</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Protein %</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Energy (kcal/kg)</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">On-hand Stock</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Updated</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($inventories as $inv)
                        @php($ft = App\Models\FeedType::with('recipe.items.ingredient')->find($inv->feed_type_id))
                        <tr class="hover:bg-indigo-50 transition duration-150 ease-in-out">
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">{{ $ft->name ?? $inv->feed_type_id }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">{{ number_format($ft->computed_protein_percent ?? 0, 2) }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">{{ number_format($ft->computed_energy_kcal_per_kg ?? 0, 0) }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-base font-bold 
                            @if($inv->quantity <= 0) 
                                text-red-600
                            @else 
                                text-blue-600
                            @endif
                            ">
                            {{ number_format($inv->quantity, 2) }} {{ $inv->unit }}
                        </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{{ $inv->last_updated ? $inv->last_updated->format('Y-m-d') : '-' }}</td>
                        </tr>
                        @endforeach
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
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Feed Item</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit Cost (₵)</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Protein %</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Energy (kcal/kg)</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bag Weight (kg)</th> <!-- ⬅️ NEW COLUMN -->
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">On-hand Qty</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Stock Update</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($ingredients as $ing)
                        @php($inv = $ingredientInventoryMap[$ing->id] ?? null)
                        <tr class="hover:bg-indigo-50 transition duration-150 ease-in-out">
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-bold text-gray-900">{{ $ing->name }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">₵{{ number_format($ing->cost_per_unit, 2) }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">{{ $ing->protein_percentage ?? 'N/A' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">{{ $ing->energy_kcal_per_kg ?? 'N/A' }}</td>
                            
                            {{-- BAG WEIGHT DISPLAY (NEW) --}}
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                @if($ing->unit === 'bag' || $ing->unit === 'bags')
                                    <span class="text-indigo-600 font-semibold">{{ number_format($ing->bag_weight_kg, 2) }} kg</span>
                                @else
                                    <span class="text-gray-400">N/A</span>
                                @endif
                            </td>
                            {{-- END BAG WEIGHT DISPLAY --}}

                            @php($qty = $inv ? (float)$inv->quantity : 0)
                            <td class="px-4 py-3 whitespace-nowrap text-base font-bold {{ $qty <= 0 ? 'text-red-600' : 'text-green-600' }}">{{ number_format($qty, 2) }}</td>
                            
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">{{ $ing->unit }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{{ $inv && $inv->last_updated ? $inv->last_updated->format('Y-m-d') : '-' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium flex items-center gap-2">
                                <a href="{{ route('ingredients.edit', $ing) }}" class="text-indigo-600 hover:text-indigo-900 p-1 rounded hover:bg-indigo-100 transition">Edit</a>
                                <form method="POST" action="{{ route('ingredients.destroy', $ing) }}" class="inline" onsubmit="return confirm('Are you sure you want to delete the feed item: {{ $ing->name }}?')">
                                    @csrf @method('DELETE')
                                    <button class="text-red-600 hover:text-red-900 p-1 rounded hover:bg-red-100 transition">Delete</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

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