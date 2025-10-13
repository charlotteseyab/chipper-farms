<x-app-layout>
    <main class="flex-1 p-8">
        <div class="max-w-6xl mx-auto">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <!-- <h2 class="text-3xl font-bold text-gray-900">🐷 All Stock Management</h2>
                    <p class="text-gray-600 mt-1">Manage and monitor all pigs in your farm</p> -->
                </div>
                <div class="flex gap-3">
                    <a href="{{ route('all-stocks.create') }}"
                       class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg font-semibold hover:bg-green-700 transition shadow-sm">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        Add New Stock
                    </a>
                </div>
            </div>

            <!-- Weight Alerts Summary -->
            <x-weight-alerts-summary />

            <div class="bg-white shadow-xl rounded-lg">
                <div class="flex items-center justify-end p-4 ">
                    <!-- <div class="flex items-center">
                        <input id="quickSearch" type="text" placeholder="Quick search..." class="w-64 border-gray-300 rounded-lg shadow-sm focus:ring-[#1C6C51] focus:border-[#1C6C51]">
                    </div> -->
                </div>
                <!-- Filters and Export Buttons integrated with table -->
                <div class="p-6 border-b border-gray-200">
                    <form method="GET" action="{{ route('all-stocks.index') }}" id="filterForm" class="flex flex-wrap items-end gap-4 mb-4">
                        <div class="flex-1 min-w-[150px]">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Stock Type</label>
                            <select name="stock_type" id="stockTypeFilter" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-[#1C6C51] focus:border-[#1C6C51]">
                                <option value="" disabled selected>All Types</option>
                                <option value="breeding" {{ request('stock_type') == 'breeding' ? 'selected' : '' }}>Breeding Stock</option>
                                <option value="standard" {{ request('stock_type') == 'standard' ? 'selected' : '' }}>Standard Stock</option>
                            </select>
                        </div>
                        <div class="flex-1 min-w-[200px]">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Breed</label>
                            <select name="breed" id="breedFilter" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-[#1C6C51] focus:border-[#1C6C51]">
                                <option value="" disabled selected>All Breeds</option>
                                <option value="LW" {{ request('breed') == 'LW' ? 'selected' : '' }}>Large White</option>
                                <option value="DR" {{ request('breed') == 'DR' ? 'selected' : '' }}>Duroc</option>
                                <option value="LWDR" {{ request('breed') == 'LWDR' ? 'selected' : '' }}>Large White Duroc</option>
                            </select>
                        </div>
                        <div class="flex-1 min-w-[150px]">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Gender</label>
                            <select name="gender" id="genderFilter" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-[#1C6C51] focus:border-[#1C6C51]">
                                <option value="" disabled selected>All Genders</option>
                                <option value="M" {{ request('gender') == 'M' ? 'selected' : '' }}>Male</option>
                                <option value="F" {{ request('gender') == 'F' ? 'selected' : '' }}>Female</option>
                            </select>
                        </div>
                        <!-- <div class="flex-1 min-w-[150px]">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Min Age (days)</label>
                            <input type="number" name="age" id="ageFilter" value="{{ request('age') }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-[#1C6C51] focus:border-[#1C6C51]" placeholder="e.g. 365">
                        </div> -->
                        <div class="flex gap-2">
                            <button type="submit" class="px-4 py-2 bg-[#1C6C51] text-white rounded-lg hover:bg-green-700 font-medium">Filter</button>
                            <a href="{{ route('all-stocks.index') }}" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 font-medium">Clear</a>
                        </div>
                    </form>
                    
                    <!-- Export Buttons + Quick Search -->
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex gap-2">
                            <a href="{{ route('all-stocks.export.csv', request()->query()) }}" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center gap-2 font-medium">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                Export CSV
                            </a>
                            <a href="{{ route('all-stocks.export.pdf', request()->query()) }}" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 flex items-center gap-2 font-medium">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                Export PDF
                            </a>
                        </div>
                        <div class="flex items-center">
                            <input id="quickSearch" type="text" placeholder="Quick search..." class="w-64 border-gray-300 rounded-lg shadow-sm focus:ring-[#1C6C51] focus:border-[#1C6C51]">
                        </div>
                    </div>
                </div>
                
                <!-- Results Summary -->
                <div class="px-6 py-3 bg-gray-50 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-600">
                            @if($pigmanagement->total() > 0)
                                Total: {{ $pigmanagement->total() }} pig{{ $pigmanagement->total() !== 1 ? 's' : '' }}
                                @if(request()->hasAny(['breed', 'gender', 'age', 'stock_type', 'has_weights', 'created_from', 'created_to', 'has_recent_weigh_date', 'recent_weigh_days', 'has_recent_weight', 'recent_weight_days']))
                                    (filtered)
                                @endif
                            @else
                                No pigs found
                            @endif
                        </div>
                        @if($pigmanagement->hasPages())
                        <!-- <div class="text-sm text-gray-600">
                            Page {{ $pigmanagement->currentPage() }} of {{ $pigmanagement->lastPage() }}
                        </div> -->
                        @endif
                    </div>
                </div>
                
                <div class="overflow-x-auto">
  <table id="allStockTable" class="min-w-full border border-gray-200 rounded-xl shadow-sm">
    <thead class="bg-green-50 sticky top-0 z-10">
      <tr>
        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Code</th>
        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Ear Tag</th>
        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Breed</th>
        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Type</th>
        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Gender</th>
        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">DOB</th>
        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Age</th>
        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Location</th>
        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Weight</th>
        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Parents</th>
        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Actions</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-200">
      @forelse($pigmanagement as $pig)
        <tr class="hover:bg-green-50 transition-colors duration-200 even:bg-gray-50 stock-row"
            data-code="{{ $pig->code }}"
            data-ear="{{ $pig->ear_tag_number }}"
            data-breed="{{ $pig->breed }}"
            data-type="{{ $pig->stock_type }}"
            data-gender="{{ $pig->gender }}"
            data-location="{{ $pig->room?->structure?->name ?? '' }} {{ $pig->room?->name ?? '' }}">
          <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $pig->code }}</td>
          <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ $pig->ear_tag_number }}</td>
          <td class="px-6 py-4 whitespace-nowrap">
            <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-700">
              {{ $pig->breed_display ?? $pig->breed }}
            </span>
          </td>
          <td class="px-6 py-4 whitespace-nowrap">
            @if($pig->stock_type == 'breeding')
              <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Breeding</span>
            @else
              <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">Standard</span>
            @endif
          </td>
          <td class="px-6 py-4 whitespace-nowrap">
            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $pig->gender == 'M' ? 'bg-blue-100 text-blue-700' : 'bg-pink-100 text-pink-700' }}">
              {{ $pig->gender == 'M' ? '♂ Male' : '♀ Female' }}
            </span>
          </td>
          <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $pig->date_of_birth->format('Y-m-d') }}</td>
          <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $pig->age_compact }}</td>
          <td class="px-6 py-4 whitespace-nowrap text-sm">
            @if($pig->room && $pig->room->structure)
                <div class="text-xs">
                    <div class="font-medium text-gray-900">{{ $pig->room->structure->name }}</div>
                    <div class="text-gray-500">{{ $pig->room->name }}</div>
                </div>
            @else
                <span class="text-gray-400">N/A</span>
            @endif
          </td>
          <td class="px-6 py-4 whitespace-nowrap">
                                @if($pig->recentWeightAlerts->count() > 0)
                                    @php
                                        $alert = $pig->recentWeightAlerts->first();
                                        $alertConfig = [
                                            'under_weight' => ['icon' => '⬇️', 'color' => 'text-blue-700', 'bg' => 'bg-blue-100', 'text' => 'Under', 'border' => 'border-blue-300'],
                                            'over_weight' => ['icon' => '⬆️', 'color' => 'text-orange-700', 'bg' => 'bg-orange-100', 'text' => 'Over', 'border' => 'border-orange-300'],
                                            'normal' => ['icon' => '✅', 'color' => 'text-green-700', 'bg' => 'bg-green-100', 'text' => 'Normal', 'border' => 'border-green-300'],
                                            'no_standard' => ['icon' => '', 'color' => 'text-gray-700', 'bg' => 'bg-gray-100', 'text' => 'No Std', 'border' => 'border-gray-300']
                                        ];
                                        $config = $alertConfig[$alert->alert_type] ?? $alertConfig['no_standard'];
                                        $severityBorder = $alert->severity == 'high' ? 'border-red-500 border-2' : ($alert->severity == 'medium' ? 'border-yellow-500' : $config['border']);
                                    @endphp
                                    <div class="flex items-center">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $config['bg'] }} {{ $config['color'] }} border {{ $severityBorder }}"
                                              title="{{ $alert->alert_message }} ({{ $alert->created_at->diffForHumans() }})">
                                            {{ $config['icon'] }} {{ $config['text'] }}
                                            @if($alert->severity == 'high')
                                                <span class="ml-1 text-red-600 font-bold">!</span>
                                            @endif
                                        </span>
                                    </div>
                                @else
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-50 text-gray-500 border border-gray-200">
                                        ➖ No Data
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @php
                                    $father = $pig->father_id ? $pig->father->ear_tag_number ?? 'N/A' : 'N/A';
                                    $mother = $pig->mother_id ? $pig->mother->ear_tag_number ?? 'N/A' : 'N/A';
                                @endphp
                                <div class="text-xs space-y-1">
                                    <div class="flex items-center">
                                        <span class="text-gray-500 w-4">♂</span>
                                        <span class="font-medium">{{ $father }}</span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="text-gray-500 w-4">♀</span>
                                        <span class="font-medium">{{ $mother }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="relative inline-block text-left" x-data="{ open: false, place: 'down', toggle(){ this.open = !this.open; if(!this.open){ return; } this.$nextTick(() => { const menu = this.$refs.menu; const trigger = this.$refs.trigger; if(!menu || !trigger) return; const mr = menu.getBoundingClientRect(); const tr = trigger.getBoundingClientRect(); const below = window.innerHeight - tr.bottom; const above = tr.top; this.place = (below < mr.height + 16 && above > mr.height) ? 'up' : 'down'; }); } }">
                                    <button x-ref="trigger" @click="toggle()"
                                            class="inline-flex items-center px-2 py-1 border border-gray-300 shadow-sm text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-green-500 transition">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/>
                                        </svg>
                                    </button>

                                    <div x-ref="menu" x-show="open"
                                         @click.away="open = false"
                                         x-transition:enter="transition ease-out duration-100"
                                         x-transition:enter-start="transform opacity-0 scale-95"
                                         x-transition:enter-end="transform opacity-100 scale-100"
                                         x-transition:leave="transition ease-in duration-75"
                                         x-transition:leave-start="transform opacity-100 scale-100"
                                         x-transition:leave-end="transform opacity-0 scale-95"
                                         :class="place === 'down' ? 'origin-top-right mt-2 top-full' : 'origin-bottom-right mb-2 bottom-full'"
                                         class="absolute right-0 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
                                        <div class="py-1">
                                            <a href="{{ route('pigs.show', $pig->id) }}"
                                               class="group flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-green-50 hover:text-green-900 transition">
                                                <svg class="mr-3 h-4 w-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                                View Details
                                            </a>

                                            <a href="{{ route('weights.create') }}?pig_id={{ $pig->id }}"
                                               class="group flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-900 transition">
                                                <svg class="mr-3 h-4 w-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                                                </svg>
                                                Record Weight
                                            </a>

                                            <a href="{{ route('breeding-stocks.edit', $pig) }}"
                                               class="group flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-yellow-50 hover:text-yellow-900 transition">
                                                <svg class="mr-3 h-4 w-4 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                                Edit Pig
                                            </a>

                                            @if($pig->stock_type === 'breeding')
                                                <a href="{{ route('mating.manage') }}"
                                                   class="group flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-pink-50 hover:text-pink-900 transition">
                                                    <svg class="mr-3 h-4 w-4 text-pink-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                                    </svg>
                                                    Manage Mating
                                                </a>
                                            @endif

                                            <!-- <a href="{{ route('weights.index') }}"
                                               class="group flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900 transition">
                                                <svg class="mr-3 h-4 w-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 00-2 2z"/>
                                                </svg>
                                                Weight Records
                                            </a> -->

                                            <div class="border-t border-gray-100"></div>

                                            <form action="{{ route('breeding-stocks.destroy', $pig) }}" method="POST" class="inline w-full">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        onclick="return confirm('Are you sure you want to delete {{ $pig->ear_tag_number }}? This action cannot be undone.')"
                                                        class="group flex items-center w-full px-4 py-2 text-sm text-red-700 hover:bg-red-50 hover:text-red-900 transition">
                                                    <svg class="mr-3 h-4 w-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                    Delete Pig
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
        </tr>
      @empty
        <tr>
          <td colspan="11" class="px-6 py-12 text-center text-sm text-gray-500">
            No stock found
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>


                
                <!-- Pagination (embedded, simple style) -->
                @if($pigmanagement->hasPages())
                    <div class="px-6 py-4 border-t border-gray-200 bg-white">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-700">
                                Showing {{ $pigmanagement->firstItem() }} to {{ $pigmanagement->lastItem() }} of {{ $pigmanagement->total() }} results
                            </div>
                            <div class="flex items-center space-x-2">
                                <x-simple-pagination :paginator="$pigmanagement" />
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </main>
    <x-copyright-minimal />


    <script>
        // Client-side quick search filtering (vanilla JS)
        (function() {
            function debounce(fn, wait) {
                let t; return function() { const ctx = this, args = arguments; clearTimeout(t); t = setTimeout(function(){ fn.apply(ctx, args); }, wait); };
            }
            document.addEventListener('DOMContentLoaded', function() {
                var rows = Array.prototype.slice.call(document.querySelectorAll('#allStockTable tbody tr.stock-row'));
                var noMatch = document.getElementById('clientNoMatchRow');
                var search = document.getElementById('quickSearch');
                if (!search) return;
                var handler = debounce(function() {
                    var q = (search.value || '').toLowerCase().trim();
                    var tokens = q.split(/\s+/).filter(Boolean);
                    if (tokens.length === 0) {
                        rows.forEach(function(r){ r.style.display = ''; });
                        if (noMatch) noMatch.classList.add('hidden');
                        return;
                    }
                    var visible = 0;
                    rows.forEach(function(r){
                        var fields = (
                            (r.dataset.code || '') + ' ' +
                            (r.dataset.ear || '') + ' ' +
                            (r.dataset.breed || '') + ' ' +
                            (r.dataset.type || '') + ' ' +
                            (r.dataset.gender || '') + ' ' +
                            (r.dataset.location || '')
                        ).toLowerCase();
                        var match = tokens.every(function(tok){ return fields.indexOf(tok) !== -1; });
                        r.style.display = match ? '' : 'none';
                        if (match) visible++;
                    });
                    if (noMatch) noMatch.classList.toggle('hidden', visible !== 0);
                }, 150);
                search.addEventListener('input', handler);
            });
        })();
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('filterForm');
            const stockTypeFilter = document.getElementById('stockTypeFilter');
            const breedFilter = document.getElementById('breedFilter');
            const genderFilter = document.getElementById('genderFilter');
            const ageFilter = document.getElementById('ageFilter');
            
            // Add event listeners for live search
            stockTypeFilter.addEventListener('change', function() {
                form.submit();
            });
            
            breedFilter.addEventListener('change', function() {
                form.submit();
            });
            
            genderFilter.addEventListener('change', function() {
                form.submit();
            });
            
            // For age input, use a debounced approach to avoid too many requests
            let ageTimeout;
            ageFilter.addEventListener('input', function() {
                clearTimeout(ageTimeout);
                ageTimeout = setTimeout(function() {
                    form.submit();
                }, 500); // Wait 500ms after user stops typing
            });
            
            // Also submit on Enter key for age input
            ageFilter.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    clearTimeout(ageTimeout);
                    form.submit();
                }
            });
        });
    </script>
</x-app-layout>
