<x-app-layout>
    <x-feed-hub hub="store" />
    @php
        // Only people who manage raw materials see the forms; everyone else sees the stock.
        $canManage = auth()->user()->hasPermission('manage_raw_materials');
        $canRequest = auth()->user()->can('create', \App\Models\PurchaseRequest::class);
        $statusClasses = [
            'in_stock' => 'bg-green-100 text-green-800 border border-green-200',
            'low_stock' => 'bg-amber-100 text-amber-800 border border-amber-200',
            'out_of_stock' => 'bg-red-100 text-red-800 border border-red-200',
        ];
    @endphp

    <div class="max-w-7xl mx-auto py-6 px-4" x-data="{ openModal: {{ $errors->any() ? "'add'" : 'null' }}, selectedMaterial: null, openingQuantity: {{ (float) old('quantity_in_stock', 0) }} }">
        <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Stock</h1>
                <p class="text-sm text-gray-500">Feed that is mixed and ready, and the ingredients in the store.</p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('purchase-requests.index') }}"
                   class="inline-flex items-center justify-center px-4 py-2.5 border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-semibold rounded-xl transition">
                    Purchase requests
                </a>
                @if(auth()->user()->hasAnyPermission(['manage_raw_materials', 'manage_feed_production']))
                    <a href="{{ route('stock-counts.create') }}"
                       class="inline-flex items-center justify-center px-4 py-2.5 border border-green-600 text-green-700 hover:bg-green-50 text-sm font-semibold rounded-xl transition">
                        Count stock
                    </a>
                @endif
                @if($canManage)
                <button type="button"
                        @click="openModal = 'add'"
                        class="inline-flex items-center justify-center px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-xl shadow-sm transition">
                    + Add Raw Material
                </button>
                @endif
            </div>
        </div>

        {{-- Feed ready to use --}}
        <section class="mb-8" aria-labelledby="ready-feed">
            <h2 id="ready-feed" class="text-lg font-bold text-gray-800">Feed ready to use</h2>
            <p class="text-sm text-gray-500">Mixed and waiting to be fed. Mix more on <a href="{{ route('feed.mix-buy') }}" class="font-semibold text-emerald-700 underline">Mix &amp; buy</a>.</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @forelse($readyFeed as $feed)
                    @php $days = $feed['days_cover']; $tone = $days === null ? 'text-gray-500' : ($days < 2 ? 'text-red-700' : ($days < 7 ? 'text-amber-700' : 'text-emerald-700')); @endphp
                    <div class="rounded-2xl border bg-white p-4 shadow-sm {{ $days !== null && $days < 2 ? 'border-red-200' : 'border-gray-100' }}">
                        <p class="text-sm font-semibold text-gray-700">{{ $feed['name'] }}</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900">{{ \App\Support\Qty::kg($feed['available_kg'], 0) }} <span class="text-sm font-semibold text-gray-500">kg</span></p>
                        <p class="text-xs font-semibold {{ $tone }}">{{ $days === null ? 'Not eaten at the moment' : 'Lasts about '.(rtrim(rtrim(number_format($days, 1), '0'), '.') ?: '0').' days' }}</p>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No feed types yet. Add them in <a href="{{ route('feed-setup.index') }}" class="font-semibold text-emerald-700 underline">Setup</a>.</p>
                @endforelse
            </div>
        </section>

        <h2 class="mb-3 text-lg font-bold text-gray-800">Ingredients in the store</h2>

        {{-- The cards are the filters: one list, with what needs attention first. --}}
        <x-glance class="mb-6" :cards="[
            ['label' => 'All materials', 'value' => $stats['total_materials'] ?? 0, 'hint' => 'in the store', 'url' => route('raw-materials.index', array_filter(['search' => $search])), 'current' => $status === ''],
            ['label' => 'Low stock', 'value' => $stats['low_stock'] ?? 0, 'hint' => 'at or below reorder level', 'url' => route('raw-materials.index', array_filter(['search' => $search, 'status' => 'low_stock'])), 'tone' => ($stats['low_stock'] ?? 0) ? 'warn' : 'good', 'current' => $status === 'low_stock'],
            ['label' => 'Out of stock', 'value' => $stats['out_of_stock'] ?? 0, 'hint' => 'nothing left', 'url' => route('raw-materials.index', array_filter(['search' => $search, 'status' => 'out_of_stock'])), 'tone' => ($stats['out_of_stock'] ?? 0) ? 'alert' : 'good', 'current' => $status === 'out_of_stock'],
            ['label' => 'Stock value', 'value' => number_format($stats['stock_value'] ?? 0, 2), 'hint' => 'at average cost'],
        ]" />

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

        @php $needing = $rawMaterials->filter(fn ($m) => $m->stock_status !== 'in_stock')->pluck('id')->values(); @endphp
        <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm"
             x-data="{ picked: [], needing: @js($needing) }">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 px-6 py-4">
                <div>
                    <h2 class="text-base font-semibold text-gray-800">Raw Materials Inventory</h2>
                    <p class="text-sm text-gray-500 mt-1">Monitor available stock, unit cost, reorder level, and stock actions.@if($canRequest) Tick several ingredients to put them all on one purchase request.@endif</p>
                </div>
                @if($canRequest)
                    {{-- One purchase request for every ingredient ticked. --}}
                    <form id="bulk-request" method="GET" action="{{ route('purchase-requests.create') }}" class="flex flex-wrap items-center gap-2">
                        <template x-for="id in picked" :key="id"><input type="hidden" name="restock_materials[]" :value="id"></template>
                        @if($needing->isNotEmpty())
                            <button type="button" @click="picked = [...needing]"
                                    class="rounded-lg border border-emerald-200 bg-white px-3 py-1.5 text-sm font-semibold text-emerald-800 hover:bg-emerald-50">
                                Select all low / out of stock ({{ $needing->count() }})
                            </button>
                        @endif
                        <button type="submit" :disabled="picked.length === 0"
                                class="rounded-lg bg-emerald-700 px-3 py-1.5 text-sm font-semibold text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-40">
                            Request purchase for selected (<span x-text="picked.length">0</span>)
                        </button>
                    </form>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            @if($canRequest)<th class="w-10"><span class="sr-only">Select</span></th>@endif
                            <th>Material</th>
                            <th>Category</th>
                            <th>Unit</th>
                            <th>Quantity</th>
                            <th>Avg Cost</th>
                            <th>Reorder Level</th>
                            <th>Stock Value</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($rawMaterials as $material)
                            @php
                                $materialStatus = $material->stock_status;
                            @endphp
                            <tr>
                                @if($canRequest)
                                    <td><input type="checkbox" value="{{ $material->id }}" x-model.number="picked" aria-label="Select {{ $material->name }}" class="rounded border-gray-300 text-emerald-700 focus:ring-emerald-500"></td>
                                @endif
                                <td>
                                    <div class="font-semibold text-gray-800">{{ $material->name }}</div>
                                    @if($material->notes)
                                        <div class="text-xs text-gray-400 mt-1 line-clamp-1">{{ $material->notes }}</div>
                                    @endif
                                </td>

                                <td class="text-sm text-gray-700">
                                    {{ $material->category ?: '—' }}
                                </td>

                                <td class="text-sm text-gray-700">
                                    {{ $material->unit }}
                                </td>

                                <td class="text-sm font-semibold text-gray-800">
                                    {{ number_format($material->quantity_in_stock, 2) }}
                                </td>

                                <td class="text-sm text-gray-700">
                                    {{ number_format($material->current_wac, 2) }}
                                </td>

                                <td class="text-sm text-gray-700">
                                    {{ number_format($material->reorder_level, 2) }}
                                </td>

                                <td class="text-sm font-semibold text-gray-800">
                                    {{ number_format($material->stock_value, 2) }}
                                </td>

                                <td>
                                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $statusClasses[$materialStatus] ?? 'bg-gray-100 text-gray-700 border border-gray-200' }}">
                                        {{ ucfirst(str_replace('_', ' ', $materialStatus)) }}
                                    </span>
                                </td>

                                <td>
                                    <div class="flex flex-wrap gap-2">
                                        @if($canRequest && $materialStatus !== 'in_stock')
                                            <a href="{{ route('purchase-requests.create', ['restock_material' => $material->id]) }}"
                                               class="inline-flex items-center rounded-lg bg-emerald-700 px-3 py-1.5 text-sm font-semibold text-white hover:bg-emerald-800 transition">
                                                Request purchase
                                            </a>
                                        @endif
                                        @if($canManage)
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
                                        @endif
                                        @if(! $canManage && ! ($canRequest && $materialStatus !== 'in_stock'))
                                            <span class="text-xs text-gray-400">View only</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canRequest ? 10 : 9 }}" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <div class="mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V7a2 2 0 00-2-2h-3V3H9v2H6a2 2 0 00-2 2v6m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0H4" />
                                            </svg>
                                        </div>
                                        <h3 class="text-base font-semibold text-gray-700">No raw materials found</h3>
                                        <p class="mt-1 text-sm text-gray-500">Add your first raw material to begin inventory tracking.</p>
                                        @if($canManage)
                                        <button type="button"
                                                @click="openModal = 'add'"
                                                class="mt-4 inline-flex items-center rounded-xl bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700 transition">
                                            + Add Raw Material
                                        </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4"><x-pager :paginator="$rawMaterials" label="materials" /></div>

        @if(($recentStockActivities ?? collect())->count())
            <div class="mt-6 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h2 class="text-base font-semibold text-gray-800">Recent Stock Activity</h2>
                    <p class="text-sm text-gray-500 mt-1">Latest receiving and adjustment actions on raw materials.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Material</th>
                                <th>Activity Type</th>
                                <th>Quantity</th>
                                <th>Unit Cost</th>
                                <th>Reference</th>
                                <th>Performed By</th>
                                <th>Date</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach($recentStockActivities as $activity)
                                @php
                                    $typeClasses = match($activity->type) {
                                        'receive' => 'bg-green-100 text-green-800 border border-green-200',
                                        'adjustment' => 'bg-amber-100 text-amber-800 border border-amber-200',
                                        'usage' => 'bg-blue-100 text-blue-800 border border-blue-200',
                                        default => 'bg-gray-100 text-gray-700 border border-gray-200',
                                    };
                                @endphp

                                <tr>
                                    <td>
                                        <div class="font-semibold text-gray-800">{{ $activity->rawMaterial->name ?? '—' }}</div>
                                    </td>

                                    <td>
                                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $typeClasses }}">
                                            {{ ucfirst($activity->type) }}
                                        </span>
                                    </td>

                                    <td class="text-sm font-semibold text-gray-800">
                                        {{ number_format($activity->quantity, 2) }}
                                    </td>

                                    <td class="text-sm text-gray-700">
                                        {{ $activity->unit_cost !== null ? number_format($activity->unit_cost, 2) : '—' }}
                                    </td>

                                    <td class="text-sm text-gray-700">
                                        {{ $activity->reference ?: '—' }}
                                    </td>

                                    <td class="text-sm text-gray-700">
                                        {{ trim(($activity->performer->first_name ?? '') . ' ' . ($activity->performer->last_name ?? '')) ?: ($activity->performer->name ?? 'System') }}
                                    </td>

                                    <td class="text-sm text-gray-600">
                                        {{ optional($activity->created_at)->format('M d, Y h:i A') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="data-table-footer"><x-pager :paginator="$recentStockActivities" label="stock changes" /></div>
            </div>
        @endif

        @if($canManage)
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
        @endif
    </div>

    <style>
        [x-cloak] { display: none !important; }
    </style>
</x-app-layout>
