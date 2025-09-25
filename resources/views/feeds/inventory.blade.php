<x-app-layout>
    <div class="max-w-7xl mx-auto py-8 px-4">
        <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white rounded-xl shadow p-4 border">
                <div class="text-xs text-gray-500">Total Feed Items</div>
                <div class="text-2xl font-bold text-gray-900">{{ isset($ingredients) ? $ingredients->count() : 0 }}</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 border">
                <div class="text-xs text-gray-500">Tracked Feed Types</div>
                <div class="text-2xl font-bold text-gray-900">{{ isset($inventories) ? $inventories->count() : 0 }}</div>
            </div>
            <div class="bg-white rounded-xl shadow p-4 border">
                <div class="text-xs text-gray-500">Low/No Stock Feed Items</div>
                @php($lowCount = isset($ingredientInventoryMap) ? $ingredientInventoryMap->filter(fn($i) => (float)($i->quantity ?? 0) <= 0)->count() : 0)
                <div class="text-2xl font-bold {{ $lowCount > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ $lowCount }}</div>
            </div>
        </div>

        <div class="flex items-center gap-3 mb-4">
            <button id="tab-feed" class="px-4 py-2 rounded-lg text-sm font-semibold bg-blue-600 text-white">Feed Types Stock</button>
            <button id="tab-ingredients" class="px-4 py-2 rounded-lg text-sm font-semibold bg-gray-100 text-gray-800">Feed Items  On-hand</button>
            <div class="ml-auto flex gap-2">
                <a id="btn-add-stock" href="{{ route('feed-inventory.create') }}" class="inline-block bg-blue-600 text-white px-4 py-2 rounded-lg shadow hover:bg-blue-700 font-semibold transition">Add Feed Stock</a>
                <a id="btn-add-ingredient" href="{{ route('ingredients.create') }}" class="inline-block bg-green-600 text-white px-4 py-2 rounded-lg shadow hover:bg-green-700 font-semibold transition" style="display:none;">Add Feed Item</a>
                <a href="{{ route('feed-inventory.analytics') }}" class="inline-block bg-purple-600 text-white px-4 py-2 rounded-lg shadow hover:bg-purple-700 font-semibold transition">Analytics</a>
            </div>
        </div>

        <div id="panel-feed">
            <div class="grid grid-cols-1 gap-8 mb-10">
                <div class="bg-white rounded-xl shadow-lg p-6 border">
                    <h2 class="text-xl font-semibold mb-4 flex items-center">
                        <svg class="w-6 h-6 text-blue-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V7"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 3v4M8 3v4m-5 4h18"/></svg>
                        Current Feed Stock (by Feed Type)
                    </h2>
                    <table class="min-w-full bg-white border rounded shadow text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left">Feed Type</th>
                                <th class="px-4 py-2 text-left">Protein %</th>
                                <th class="px-4 py-2 text-left">Energy (kcal/kg)</th>
                                <!-- <th class="px-4 py-2 text-left">Cost/kg (GHS)</th> -->
                                <th class="px-4 py-2 text-left">On-hand</th>
                                <th class="px-4 py-2 text-left">Last Updated</th>
                                <th class="px-4 py-2 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($inventories as $inv)
                            @php($ft = App\Models\FeedType::with('recipe.items.ingredient')->find($inv->feed_type_id))
                            <tr>
                                <td class="px-4 py-2">{{ $ft->name ?? $inv->feed_type_id }}</td>
                                <td class="px-4 py-2">{{ number_format($ft->computed_protein_percent ?? 0, 2) }}</td>
                                <td class="px-4 py-2">{{ number_format($ft->computed_energy_kcal_per_kg ?? 0, 0) }}</td>
                                <!-- <td class="px-4 py-2">₵{{ number_format($ft->computed_cost_per_kg ?? 0, 2) }}</td> -->
                                <td class="px-4 py-2 font-semibold">{{ number_format($inv->quantity, 2) }} {{ $inv->unit }}</td>
                                <td class="px-4 py-2">{{ $inv->last_updated ? $inv->last_updated->format('Y-m-d') : '-' }}</td>
                                <!-- <td class="px-4 py-2 space-x-3">
                                    <a href="{{ route('feed-inventory.edit', $inv) }}" class="text-blue-600 hover:underline">Edit</a>
                                    <form method="POST" action="{{ route('feed-inventory.destroy', $inv) }}" class="inline" onsubmit="return confirm('Delete this feed stock?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-red-600 hover:underline">Delete</button>
                                    </form>
                                </td> -->
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="panel-ingredients" style="display:none;">
            <div class="bg-white rounded-xl shadow-lg p-6 border">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold">Feed Items Catalog + On-hand</h2>
                    <div class="flex items-center gap-2 text-sm text-gray-500">Edit ingredient master data or adjust stock</div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white border rounded shadow text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left">Feed Item</th>
                                <th class="px-4 py-2 text-left">Cost/Unit (GHS)</th>
                                <th class="px-4 py-2 text-left">Protein %</th>
                                <th class="px-4 py-2 text-left">Energy (kcal/kg)</th>
                                <th class="px-4 py-2 text-left">On-hand</th>
                                <th class="px-4 py-2 text-left">Unit</th>
                                <th class="px-4 py-2 text-left">Last Updated</th>
                                <th class="px-4 py-2 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($ingredients as $ing)
                            @php($inv = $ingredientInventoryMap[$ing->id] ?? null)
                            <tr>
                                <td class="px-4 py-2 font-medium">{{ $ing->name }}</td>
                                <td class="px-4 py-2">₵{{ number_format($ing->cost_per_unit, 2) }}</td>
                                <td class="px-4 py-2">{{ $ing->protein_percentage ?? '-' }}</td>
                                <td class="px-4 py-2">{{ $ing->energy_kcal_per_kg ?? '-' }}</td>
                                <td class="px-4 py-2">{{ $inv ? number_format($inv->quantity, 2) : '0.00' }}</td>
                                <td class="px-4 py-2">{{ $ing->unit }}</td>
                                <td class="px-4 py-2">{{ $inv && $inv->last_updated ? $inv->last_updated->format('Y-m-d') : '-' }}</td>
                                <td class="px-4 py-2 space-x-3">
                                    <a href="{{ route('ingredients.edit', $ing) }}" class="text-blue-600 hover:underline">Edit Ingredient</a>
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
        const tabFeed = document.getElementById('tab-feed');
        const tabIng = document.getElementById('tab-ingredients');
        const panelFeed = document.getElementById('panel-feed');
        const panelIng = document.getElementById('panel-ingredients');
        function activate(tab) {
            const active = tab === 'feed';
            panelFeed.style.display = active ? 'block' : 'none';
            panelIng.style.display = active ? 'none' : 'block';
            tabFeed.className = active ? 'px-4 py-2 rounded-lg text-sm font-semibold bg-blue-600 text-white' : 'px-4 py-2 rounded-lg text-sm font-semibold bg-gray-100 text-gray-800';
            tabIng.className = !active ? 'px-4 py-2 rounded-lg text-sm font-semibold bg-blue-600 text-white' : 'px-4 py-2 rounded-lg text-sm font-semibold bg-gray-100 text-gray-800';
            const btnStock = document.getElementById('btn-add-stock');
            const btnIngredient = document.getElementById('btn-add-ingredient');
            if (btnStock && btnIngredient) {
                btnStock.style.display = active ? 'inline-block' : 'none';
                btnIngredient.style.display = active ? 'none' : 'inline-block';
            }
        }
        tabFeed?.addEventListener('click', () => activate('feed'));
        tabIng?.addEventListener('click', () => activate('ingredients'));
        // Auto-activate tab from query param ?tab=ingredients
        (function(){
            const params = new URLSearchParams(window.location.search);
            const tab = params.get('tab');
            if (tab === 'ingredients') {
                activate('ingredients');
            } else {
                activate('feed');
            }
        })();
    </script>
</x-app-layout>
