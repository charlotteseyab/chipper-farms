<x-app-layout>
    <x-feed-hub hub="store" />
    @php
        $statusClasses = [
            'in_stock' => 'bg-green-100 text-green-800 border border-green-200',
            'low_stock' => 'bg-amber-100 text-amber-800 border border-amber-200',
            'out_of_stock' => 'bg-red-100 text-red-800 border border-red-200',
        ];
    @endphp

    <div class="max-w-7xl mx-auto py-6 px-4" x-data="{ openModal: {{ $errors->any() ? "'add'" : 'null' }}, selectedMaterial: null, openingQuantity: {{ (float) old('quantity_in_stock', 0) }} }">
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Raw Materials & Stock</h1>
                <p class="text-sm text-gray-500">Manage raw materials, stock levels, receiving, and adjustments.</p>
            </div>

            <div class="flex flex-wrap gap-2">
                @if(auth()->user()->hasAnyPermission(['manage_raw_materials', 'manage_feed_production']))
                    <a href="{{ route('stock-counts.create') }}"
                       class="inline-flex items-center justify-center px-4 py-2.5 border border-green-600 text-green-700 hover:bg-green-50 text-sm font-semibold rounded-xl transition">
                        Count stock
                    </a>
                @endif
                <button type="button"
                        @click="openModal = 'add'"
                        class="inline-flex items-center justify-center px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-xl shadow-sm transition">
                    + Add Raw Material
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
            <div class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5">
                <p class="text-sm font-medium text-gray-500">Total Materials</p>
                <h3 class="mt-2 text-3xl font-bold text-slate-800">{{ $stats['total_materials'] ?? 0 }}</h3>
                <p class="mt-1 text-xs text-gray-400">All raw materials in store</p>
            </div>

            <div class="rounded-2xl border border-amber-100 bg-white shadow-sm p-5">
                <p class="text-sm font-medium text-gray-500">Low Stock</p>
                <h3 class="mt-2 text-3xl font-bold text-amber-700">{{ $stats['low_stock'] ?? 0 }}</h3>
                <p class="mt-1 text-xs text-gray-400">Materials at or below reorder level</p>
            </div>

            <div class="rounded-2xl border border-red-100 bg-white shadow-sm p-5">
                <p class="text-sm font-medium text-gray-500">Out of Stock</p>
                <h3 class="mt-2 text-3xl font-bold text-red-700">{{ $stats['out_of_stock'] ?? 0 }}</h3>
                <p class="mt-1 text-xs text-gray-400">Materials with zero balance</p>
            </div>

            <div class="rounded-2xl border border-emerald-100 bg-white shadow-sm p-5">
                <p class="text-sm font-medium text-gray-500">Total Stock Value</p>
                <h3 class="mt-2 text-3xl font-bold text-emerald-700">{{ number_format($stats['stock_value'] ?? 0, 2) }}</h3>
                <p class="mt-1 text-xs text-gray-400">Estimated inventory value</p>
            </div>
        </div>

        @if(($lowStockAlerts ?? collect())->count())
            <div class="mb-6 overflow-hidden rounded-2xl border border-amber-200 bg-amber-50 shadow-sm">
                <div class="border-b border-amber-200 px-6 py-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-base font-semibold text-amber-900">Low Stock Alerts</h2>
                            <p class="mt-1 text-sm text-amber-700">
                                These materials need attention because stock is low or exhausted.
                            </p>
                        </div>

                        <span class="inline-flex items-center rounded-full bg-white px-3 py-1 text-xs font-semibold text-amber-800 border border-amber-200">
                            {{ $lowStockAlerts->count() }} item{{ $lowStockAlerts->count() > 1 ? 's' : '' }}
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 p-6">
                    @foreach($lowStockAlerts as $alert)
                        @php
                            $isOut = (float) $alert->quantity_in_stock <= 0;
                        @endphp

                        <div class="rounded-xl border {{ $isOut ? 'border-red-200 bg-red-50' : 'border-amber-200 bg-white' }} p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-900">{{ $alert->name }}</h3>
                                    <p class="mt-1 text-xs text-gray-500">
                                        {{ $alert->category ?: 'No category' }} • Unit: {{ $alert->unit }}
                                    </p>
                                </div>

                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $isOut ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700' }}">
                                    {{ $isOut ? 'Out of Stock' : 'Low Stock' }}
                                </span>
                            </div>

                            <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                <div class="rounded-lg bg-white/80 px-3 py-2 border border-gray-100">
                                    <p class="text-gray-500">Available</p>
                                    <p class="font-semibold text-gray-800">
                                        {{ number_format($alert->quantity_in_stock, 2) }} {{ $alert->unit }}
                                    </p>
                                </div>

                                <div class="rounded-lg bg-white/80 px-3 py-2 border border-gray-100">
                                    <p class="text-gray-500">Reorder Level</p>
                                    <p class="font-semibold text-gray-800">
                                        {{ number_format($alert->reorder_level, 2) }} {{ $alert->unit }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <button type="button"
                                        @click='selectedMaterial = @json($alert); openModal = "receive"'
                                        class="inline-flex items-center rounded-lg bg-green-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-green-700 transition">
                                    Receive Stock
                                </button>

                                <button type="button"
                                        @click='selectedMaterial = @json($alert); openModal = "adjust"'
                                        class="inline-flex items-center rounded-lg bg-white px-3 py-1.5 text-sm font-semibold text-amber-700 border border-amber-200 hover:bg-amber-100 transition">
                                    Adjust Stock
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <form method="GET" action="{{ route('raw-materials.index') }}" class="mb-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                    <input type="text"
                           name="search"
                           value="{{ $search }}"
                           placeholder="Search by name, category or unit"
                           class="w-full rounded-xl border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Stock Status</label>
                    <select name="status"
                            class="w-full rounded-xl border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                        <option value="">All Statuses</option>
                        <option value="in_stock" {{ $status === 'in_stock' ? 'selected' : '' }}>In Stock</option>
                        <option value="low_stock" {{ $status === 'low_stock' ? 'selected' : '' }}>Low Stock</option>
                        <option value="out_of_stock" {{ $status === 'out_of_stock' ? 'selected' : '' }}>Out of Stock</option>
                    </select>
                </div>

                <div class="flex items-end gap-3">
                    <button type="submit"
                            class="inline-flex items-center justify-center px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-xl shadow-sm transition">
                        Filter
                    </button>

                    <a href="{{ route('raw-materials.index') }}"
                       class="inline-flex items-center justify-center px-4 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm font-semibold rounded-xl transition">
                        Reset
                    </a>
                </div>
            </div>
        </form>

        <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-6 py-4">
                <h2 class="text-base font-semibold text-gray-800">Raw Materials Inventory</h2>
                <p class="text-sm text-gray-500 mt-1">Monitor available stock, unit cost, reorder level, and stock actions.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Material</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Category</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Unit</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Quantity</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Avg Cost</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Reorder Level</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Stock Value</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Actions</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse($rawMaterials as $material)
                            @php
                                $materialStatus = $material->stock_status;
                            @endphp
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-gray-800">{{ $material->name }}</div>
                                    @if($material->notes)
                                        <div class="text-xs text-gray-400 mt-1 line-clamp-1">{{ $material->notes }}</div>
                                    @endif
                                </td>

                                <td class="px-6 py-4 text-sm text-gray-700">
                                    {{ $material->category ?: '—' }}
                                </td>

                                <td class="px-6 py-4 text-sm text-gray-700">
                                    {{ $material->unit }}
                                </td>

                                <td class="px-6 py-4 text-sm font-semibold text-gray-800">
                                    {{ number_format($material->quantity_in_stock, 2) }}
                                </td>

                                <td class="px-6 py-4 text-sm text-gray-700">
                                    {{ number_format($material->current_wac, 2) }}
                                </td>

                                <td class="px-6 py-4 text-sm text-gray-700">
                                    {{ number_format($material->reorder_level, 2) }}
                                </td>

                                <td class="px-6 py-4 text-sm font-semibold text-gray-800">
                                    {{ number_format($material->stock_value, 2) }}
                                </td>

                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $statusClasses[$materialStatus] ?? 'bg-gray-100 text-gray-700 border border-gray-200' }}">
                                        {{ ucfirst(str_replace('_', ' ', $materialStatus)) }}
                                    </span>
                                </td>

                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button"
                                                @click='selectedMaterial = @json($material); openModal = "edit"'
                                                class="inline-flex items-center rounded-lg bg-blue-50 px-3 py-1.5 text-sm font-semibold text-blue-700 hover:bg-blue-100 transition">
                                            Edit
                                        </button>

                                        <button type="button"
                                                @click='selectedMaterial = @json($material); openModal = "receive"'
                                                class="inline-flex items-center rounded-lg bg-green-50 px-3 py-1.5 text-sm font-semibold text-green-700 hover:bg-green-100 transition">
                                            Receive
                                        </button>

                                        <button type="button"
                                                @click='selectedMaterial = @json($material); openModal = "adjust"'
                                                class="inline-flex items-center rounded-lg bg-amber-50 px-3 py-1.5 text-sm font-semibold text-amber-700 hover:bg-amber-100 transition">
                                            Adjust
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <div class="mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V7a2 2 0 00-2-2h-3V3H9v2H6a2 2 0 00-2 2v6m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0H4" />
                                            </svg>
                                        </div>
                                        <h3 class="text-base font-semibold text-gray-700">No raw materials found</h3>
                                        <p class="mt-1 text-sm text-gray-500">Add your first raw material to begin inventory tracking.</p>
                                        <button type="button"
                                                @click="openModal = 'add'"
                                                class="mt-4 inline-flex items-center rounded-xl bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700 transition">
                                            + Add Raw Material
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-5">
            {{ $rawMaterials->links() }}
        </div>

        @if(($recentStockActivities ?? collect())->count())
            <div class="mt-6 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h2 class="text-base font-semibold text-gray-800">Recent Stock Activity</h2>
                    <p class="text-sm text-gray-500 mt-1">Latest receiving and adjustment actions on raw materials.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Material</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Activity Type</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Quantity</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Unit Cost</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Reference</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Performed By</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Date</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-100 bg-white">
                            @foreach($recentStockActivities as $activity)
                                @php
                                    $typeClasses = match($activity->type) {
                                        'receive' => 'bg-green-100 text-green-800 border border-green-200',
                                        'adjustment' => 'bg-amber-100 text-amber-800 border border-amber-200',
                                        'usage' => 'bg-blue-100 text-blue-800 border border-blue-200',
                                        default => 'bg-gray-100 text-gray-700 border border-gray-200',
                                    };
                                @endphp

                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4">
                                        <div class="font-semibold text-gray-800">{{ $activity->rawMaterial->name ?? '—' }}</div>
                                    </td>

                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $typeClasses }}">
                                            {{ ucfirst($activity->type) }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-4 text-sm font-semibold text-gray-800">
                                        {{ number_format($activity->quantity, 2) }}
                                    </td>

                                    <td class="px-6 py-4 text-sm text-gray-700">
                                        {{ $activity->unit_cost !== null ? number_format($activity->unit_cost, 2) : '—' }}
                                    </td>

                                    <td class="px-6 py-4 text-sm text-gray-700">
                                        {{ $activity->reference ?: '—' }}
                                    </td>

                                    <td class="px-6 py-4 text-sm text-gray-700">
                                        {{ trim(($activity->performer->first_name ?? '') . ' ' . ($activity->performer->last_name ?? '')) ?: ($activity->performer->name ?? 'System') }}
                                    </td>

                                    <td class="px-6 py-4 text-sm text-gray-600">
                                        {{ optional($activity->created_at)->format('M d, Y h:i A') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-4 border-t border-gray-100">
                    {{ $recentStockActivities->links() }}
                </div>
            </div>
        @endif

        <!-- Add Modal -->
        <div x-show="openModal === 'add'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <div @click.away="openModal = null" class="w-full max-w-2xl rounded-2xl bg-white shadow-2xl overflow-hidden">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-800">Add Raw Material</h3>
                    <button type="button" @click="openModal = null" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
                </div>

                <form action="{{ route('raw-materials.store') }}" method="POST" class="p-6 space-y-5">
                    @csrf

                    @if($errors->any())
                        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            <p class="font-bold">Please correct the highlighted information.</p>
                            <ul class="mt-1 list-disc pl-5">
                                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Material Name</label>
                            <input type="text" name="name" value="{{ old('name') }}"
                                   class="w-full rounded-xl border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500" required>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                            <input type="text" name="category" value="{{ old('category') }}"
                                   class="w-full rounded-xl border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Unit</label>
                            <input type="text" name="unit" value="{{ old('unit', 'kg') }}"
                                   class="w-full rounded-xl border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500" required>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Kg per unit</label>
                            <input type="number" step="0.0001" min="0.0001" name="kg_per_unit" value="{{ old('kg_per_unit') }}" placeholder="e.g. 50 for a 50 kg bag"
                                   class="w-full rounded-xl border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                            <p class="mt-1 text-xs text-gray-500">Only needed when the unit is not kg. Recipes use it to convert.</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Opening Stock</label>
                            <input type="number" step="0.01" min="0" name="quantity_in_stock" value="{{ old('quantity_in_stock', 0) }}" x-model.number="openingQuantity"
                                   class="w-full rounded-xl border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Unit Cost</label>
                            <input type="number" step="0.01" min="0" name="unit_cost" value="{{ old('unit_cost', 0) }}"
                                   class="w-full rounded-xl border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Reorder Level</label>
                            <input type="number" step="0.01" min="0" name="reorder_level" value="{{ old('reorder_level', 0) }}"
                                   class="w-full rounded-xl border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                        </div>

                        <div x-show="openingQuantity > 0" x-cloak class="md:col-span-2 rounded-xl border border-emerald-100 bg-emerald-50/60 p-4">
                            <label class="block text-sm font-semibold text-gray-800 mb-2">Opening Stock Supplier</label>
                            @if($suppliers->isNotEmpty())
                                <select name="opening_supplier_id" :required="openingQuantity > 0"
                                        class="w-full rounded-xl border-gray-300 bg-white shadow-sm focus:border-green-500 focus:ring-green-500">
                                    <option value="">Select supplier</option>
                                    @foreach($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}" @selected((string) old('opening_supplier_id') === (string) $supplier->id)>{{ $supplier->name }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-2 text-xs text-gray-600">This attributes the opening receipt correctly in inventory and finance records.</p>
                            @else
                                <p class="text-sm font-medium text-amber-800">No supplier exists for this farm yet. Add the material with opening stock set to zero, or create a supplier first.</p>
                                @if(Route::has('finance.suppliers.create'))
                                    <a href="{{ route('finance.suppliers.create') }}" class="mt-2 inline-flex text-sm font-bold text-emerald-700 hover:text-emerald-800">Create supplier →</a>
                                @endif
                            @endif
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                            <textarea name="notes" rows="3"
                                      class="w-full rounded-xl border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">{{ old('notes') }}</textarea>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="openModal = null"
                                class="px-4 py-2.5 rounded-xl bg-gray-200 hover:bg-gray-300 text-sm font-semibold text-gray-800 transition">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2.5 rounded-xl bg-green-600 hover:bg-green-700 text-sm font-semibold text-white transition">
                            Save Material
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Modal -->
        <div x-show="openModal === 'edit' && selectedMaterial" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <div @click.away="openModal = null" class="w-full max-w-2xl rounded-2xl bg-white shadow-2xl overflow-hidden">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-800">Edit Raw Material</h3>
                    <button type="button" @click="openModal = null" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
                </div>

                <form :action="`/raw-materials-stock/${selectedMaterial.id}`" method="POST" class="p-6 space-y-5">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Material Name</label>
                            <input type="text" name="name" :value="selectedMaterial.name"
                                   class="w-full rounded-xl border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500" required>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                            <input type="text" name="category" :value="selectedMaterial.category"
                                   class="w-full rounded-xl border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Unit</label>
                            <input type="text" name="unit" :value="selectedMaterial.unit"
                                   class="w-full rounded-xl border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500" required>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Kg per unit</label>
                            <input type="number" step="0.0001" min="0.0001" name="kg_per_unit" :value="selectedMaterial.kg_per_unit" placeholder="e.g. 50 for a 50 kg bag"
                                   class="w-full rounded-xl border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                            <p class="mt-1 text-xs text-gray-500">Only needed when the unit is not kg. Recipes use it to convert.</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Unit Cost</label>
                            <input type="number" step="0.01" min="0" name="unit_cost" :value="selectedMaterial.unit_cost"
                                   class="w-full rounded-xl border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Reorder Level</label>
                            <input type="number" step="0.01" min="0" name="reorder_level" :value="selectedMaterial.reorder_level"
                                   class="w-full rounded-xl border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                            <textarea name="notes" rows="3"
                                      class="w-full rounded-xl border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                                      x-text="selectedMaterial.notes"></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="openModal = null"
                                class="px-4 py-2.5 rounded-xl bg-gray-200 hover:bg-gray-300 text-sm font-semibold text-gray-800 transition">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-sm font-semibold text-white transition">
                            Update Material
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Receive Modal -->
        <div x-show="openModal === 'receive' && selectedMaterial" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <div @click.away="openModal = null" class="w-full max-w-xl rounded-2xl bg-white shadow-2xl overflow-hidden">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">Receive Stock</h3>
                        <p class="text-sm text-gray-500" x-text="selectedMaterial ? selectedMaterial.name : ''"></p>
                    </div>
                    <button type="button" @click="openModal = null" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
                </div>

                <form :action="`/raw-materials-stock/${selectedMaterial.id}/receive`" method="POST" class="p-6 space-y-5">
                    @csrf

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Quantity Received</label>
                            <input type="number" step="0.01" min="0.01" name="quantity"
                                   class="w-full rounded-xl border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500" required>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Unit Cost</label>
                            <input type="number" step="0.01" min="0" name="unit_cost" :value="selectedMaterial.unit_cost"
                                   class="w-full rounded-xl border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Reference</label>
                            <input type="text" name="reference" placeholder="e.g. Invoice No / GRN"
                                   class="w-full rounded-xl border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Supplier</label>
                            <select name="supplier_id" class="w-full rounded-xl border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500" required>
                                <option value="">Select supplier</option>
                                @foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Reason for direct receipt</label>
                            <select name="receipt_reason" class="w-full rounded-xl border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500" required>
                                <option value="">Select reason</option>
                                <option value="direct_purchase">Direct purchase</option>
                                <option value="donation">Donation</option>
                                <option value="sample">Supplier sample</option>
                                <option value="returns">Stock returned</option>
                                <option value="correction">Stock correction</option>
                            </select>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                            <textarea name="notes" rows="3"
                                      class="w-full rounded-xl border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="openModal = null"
                                class="px-4 py-2.5 rounded-xl bg-gray-200 hover:bg-gray-300 text-sm font-semibold text-gray-800 transition">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2.5 rounded-xl bg-green-600 hover:bg-green-700 text-sm font-semibold text-white transition">
                            Receive Stock
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Adjust Modal -->
        <div x-show="openModal === 'adjust' && selectedMaterial" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <div @click.away="openModal = null" class="w-full max-w-xl rounded-2xl bg-white shadow-2xl overflow-hidden">
                <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">Adjust Stock</h3>
                        <p class="text-sm text-gray-500" x-text="selectedMaterial ? selectedMaterial.name : ''"></p>
                    </div>
                    <button type="button" @click="openModal = null" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
                </div>

                <form :action="`/raw-materials-stock/${selectedMaterial.id}/adjust`" method="POST" class="p-6 space-y-5">
                    @csrf

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Adjustment Type</label>
                            <select name="adjustment_type"
                                    class="w-full rounded-xl border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500" required>
                                <option value="add">Add to Stock</option>
                                <option value="deduct">Deduct from Stock</option>
                                <option value="set">Set Exact Stock</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Quantity</label>
                            <input type="number" step="0.01" min="0.01" name="quantity"
                                   class="w-full rounded-xl border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500" required>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Reference</label>
                            <input type="text" name="reference" placeholder="e.g. Stock Count / Correction"
                                   class="w-full rounded-xl border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                            <textarea name="notes" rows="3" required minlength="5" maxlength="2000" placeholder="Reason for this stock adjustment"
                                      class="w-full rounded-xl border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500"></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="openModal = null"
                                class="px-4 py-2.5 rounded-xl bg-gray-200 hover:bg-gray-300 text-sm font-semibold text-gray-800 transition">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-sm font-semibold text-white transition">
                            Save Adjustment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
        [x-cloak] { display: none !important; }
    </style>
</x-app-layout>
