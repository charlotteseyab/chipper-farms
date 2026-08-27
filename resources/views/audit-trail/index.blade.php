<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">System Audit Trail</h1>
                <p class="text-sm text-gray-500 mt-1">Track important actions, record changes, approvals, stock movement, and archived records.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('deaths.index') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    Death Records
                </a>
                <a href="{{ route('sales.index') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    Sales
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-800">
                {{ session('success') }}
            </div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
            <div class="rounded-xl bg-white border border-gray-100 shadow-sm p-5">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Today's Events</div>
                <div class="mt-2 text-3xl font-bold text-gray-900">{{ number_format($summary['today']) }}</div>
            </div>
            <div class="rounded-xl bg-white border border-blue-100 shadow-sm p-5">
                <div class="text-xs font-semibold uppercase tracking-wide text-blue-600">Stock Changes</div>
                <div class="mt-2 text-3xl font-bold text-blue-900">{{ number_format($summary['stock']) }}</div>
            </div>
            <div class="rounded-xl bg-white border border-amber-100 shadow-sm p-5">
                <div class="text-xs font-semibold uppercase tracking-wide text-amber-600">Approvals</div>
                <div class="mt-2 text-3xl font-bold text-amber-900">{{ number_format($summary['approvals']) }}</div>
            </div>
            <div class="rounded-xl bg-white border border-emerald-100 shadow-sm p-5">
                <div class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Archived / Restored</div>
                <div class="mt-2 text-3xl font-bold text-emerald-900">{{ number_format($summary['archived_restored']) }}</div>
            </div>
        </div>

        <div class="mb-6 border-b border-gray-200">
            <nav class="-mb-px flex gap-6">
                <a href="{{ route('audit-trail.index', array_merge(request()->except(['tab', 'archive_page', 'piglet_death_page']), ['tab' => 'activity'])) }}"
                   class="pb-3 text-sm font-semibold border-b-2 {{ $activeTab === 'activity' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    All Activity
                </a>
                @if($canViewArchives)
                    <a href="{{ route('audit-trail.index', array_merge(request()->except(['tab', 'audit_page']), ['tab' => 'archived'])) }}"
                       class="pb-3 text-sm font-semibold border-b-2 {{ $activeTab === 'archived' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                        Archived Records
                    </a>
                @endif
            </nav>
        </div>

        @if($activeTab === 'activity')
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6">
                <form method="GET" class="p-4 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-4">
                    <input type="hidden" name="tab" value="activity">
                    <div class="xl:col-span-2">
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide">Search</label>
                        <input type="text" name="q" value="{{ request('q') }}" placeholder="User, ear tag, batch, description..." class="mt-1 w-full rounded-lg border-gray-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide">Module</label>
                        <select name="module" class="mt-1 w-full rounded-lg border-gray-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">All</option>
                            @foreach($modules as $value => $label)
                                <option value="{{ $value }}" @selected(request('module') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide">Action</label>
                        <select name="action" class="mt-1 w-full rounded-lg border-gray-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">All</option>
                            @foreach($actions as $value => $label)
                                <option value="{{ $value }}" @selected(request('action') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide">User</label>
                        <select name="user_id" class="mt-1 w-full rounded-lg border-gray-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">All</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" @selected((string) request('user_id') === (string) $user->id)>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2 xl:col-span-2">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide">From</label>
                            <input type="date" name="date_from" value="{{ request('date_from') }}" class="mt-1 w-full rounded-lg border-gray-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide">To</label>
                            <input type="date" name="date_to" value="{{ request('date_to') }}" class="mt-1 w-full rounded-lg border-gray-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="w-full px-4 py-2 bg-indigo-600 text-white rounded-lg font-semibold shadow hover:bg-indigo-700">Apply</button>
                        <a href="{{ route('audit-trail.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg font-semibold hover:bg-gray-200">Reset</a>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-xl shadow border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Date & Time</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">User</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Module</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Action</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Record</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Details</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @forelse($logs as $log)
                                @php
                                    $moduleLabel = $modules[$log->module] ?? \Illuminate\Support\Str::headline($log->module ?: 'System');
                                    $actionLabel = $actions[$log->action] ?? \Illuminate\Support\Str::headline($log->action);
                                    $actionClass = match($log->action) {
                                        'created' => 'bg-blue-100 text-blue-800',
                                        'updated' => 'bg-slate-100 text-slate-800',
                                        'approved', 'added_to_stock' => 'bg-amber-100 text-amber-800',
                                        'archived', 'deleted' => 'bg-red-100 text-red-800',
                                        'death_recorded' => 'bg-red-100 text-red-800',
                                        'restored' => 'bg-emerald-100 text-emerald-800',
                                        'sold' => 'bg-green-100 text-green-800',
                                        default => 'bg-gray-100 text-gray-800',
                                    };
                                @endphp
                                <tr class="hover:bg-gray-50 align-top">
                                    <td class="px-4 py-4 text-sm text-gray-700 whitespace-nowrap">
                                        <div class="font-semibold text-gray-900">{{ optional($log->created_at)->format('M d, Y') }}</div>
                                        <div class="text-xs text-gray-500">{{ optional($log->created_at)->format('h:i A') }}</div>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-700">
                                        <div class="font-semibold text-gray-900">{{ $log->user->name ?? $log->name ?? 'System' }}</div>
                                        <div class="text-xs text-gray-500">{{ $log->user->email ?? '' }}</div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700">{{ $moduleLabel }}</span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $actionClass }}">{{ $actionLabel }}</span>
                                    </td>
                                    <td class="px-4 py-4 text-sm">
                                        <div class="font-semibold text-gray-900">{{ $log->record_label ?: 'General activity' }}</div>
                                        @if($log->auditable_type)
                                            <div class="text-xs text-gray-500">{{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-600 min-w-[280px]">
                                        <div>{{ $log->description ?: 'No description recorded.' }}</div>
                                        @if(!empty($log->old_values) || !empty($log->new_values) || !empty($log->metadata))
                                            <details class="mt-2">
                                                <summary class="cursor-pointer text-indigo-600 font-semibold">View details</summary>
                                                @if(!empty($log->old_values) || !empty($log->new_values))
                                                    <div class="mt-3 overflow-x-auto">
                                                        <table class="min-w-full text-xs border border-gray-100 rounded-lg">
                                                            <thead class="bg-gray-50">
                                                                <tr>
                                                                    <th class="px-3 py-2 text-left">Field</th>
                                                                    <th class="px-3 py-2 text-left">Before</th>
                                                                    <th class="px-3 py-2 text-left">After</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach(array_unique(array_merge(array_keys($log->old_values ?? []), array_keys($log->new_values ?? []))) as $field)
                                                                    <tr class="border-t border-gray-100">
                                                                        <td class="px-3 py-2 font-semibold text-gray-700">{{ \Illuminate\Support\Str::headline($field) }}</td>
                                                                        <td class="px-3 py-2 text-gray-500">{{ is_scalar($log->old_values[$field] ?? null) ? ($log->old_values[$field] ?? '—') : json_encode($log->old_values[$field] ?? null) }}</td>
                                                                        <td class="px-3 py-2 text-gray-900">{{ is_scalar($log->new_values[$field] ?? null) ? ($log->new_values[$field] ?? '—') : json_encode($log->new_values[$field] ?? null) }}</td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                @endif
                                                @if(!empty($log->metadata))
                                                    <pre class="mt-3 rounded-lg bg-gray-950 text-gray-100 text-xs p-3 overflow-x-auto">{{ json_encode($log->metadata, JSON_PRETTY_PRINT) }}</pre>
                                                @endif
                                            </details>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-12 text-center text-gray-500">
                                        <p class="font-semibold">No audit events found</p>
                                        <p class="text-xs text-gray-400 mt-1">Try widening the filters or perform a tracked stock action.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($logs->hasPages())
                    <div class="px-6 py-4 border-t border-gray-100">
                        {{ $logs->links() }}
                    </div>
                @endif
            </div>
        @else
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6">
                <form method="GET" class="p-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <input type="hidden" name="tab" value="archived">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide">Search</label>
                        <input type="text" name="archive_search" value="{{ request('archive_search') }}" placeholder="Ear tag, code..." class="mt-1 w-full rounded-lg border-gray-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide">Category</label>
                        <select name="category" class="mt-1 w-full rounded-lg border-gray-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">All</option>
                            <option value="piglet" @selected(request('category') === 'piglet')>Piglets (&lt; {{ $weaningAge }} days)</option>
                            <option value="adult" @selected(request('category') === 'adult')>Adults (≥ {{ $weaningAge }} days)</option>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="w-full px-4 py-2 bg-indigo-600 text-white rounded-lg font-semibold shadow hover:bg-indigo-700">Apply Filters</button>
                        <a href="{{ route('audit-trail.index', ['tab' => 'archived']) }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg font-semibold hover:bg-gray-200">Reset</a>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-xl shadow border border-gray-100">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h2 class="text-lg font-bold text-gray-900">Archived Pigs</h2>
                    <p class="text-sm text-gray-500">Animals removed because of sales, deaths, or manual archive actions. Restore when needed.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Archived Date</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Record</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Type</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Reason</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Related Details</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @forelse($pigs as $pig)
                                @php
                                    $isPiglet = $pig->date_of_birth?->diffInDays(now()) < $weaningAge;
                                    $death = $pig->deaths->first();
                                    $sale = $pig->sales->first();
                                    $status = $death ? 'Deceased' : ($sale ? 'Sold' : 'Manual Archive');
                                    $statusClasses = $death
                                        ? 'bg-red-100 text-red-800'
                                        : ($sale ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800');
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-4 text-sm text-gray-600 whitespace-nowrap">
                                        <div class="font-semibold text-gray-900">{{ optional($pig->deleted_at)->format('M d, Y') }}</div>
                                        <div class="text-xs text-gray-400">{{ optional($pig->deleted_at)->format('h:i A') }} · {{ optional($pig->deleted_at)->diffForHumans() }}</div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="text-sm font-semibold text-gray-900">{{ $pig->ear_tag_number ?? 'No Tag' }}</div>
                                        <div class="text-xs text-gray-500">{{ $pig->code }} · {{ $pig->breed ?? 'Unknown breed' }}</div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $isPiglet ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800' }}">
                                            {{ $isPiglet ? 'Piglet (< ' . $weaningAge . 'd)' : 'Adult' }}
                                        </span>
                                        <div class="text-xs text-gray-500 mt-1">{{ ucfirst($pig->stock_type ?? 'stock') }} · {{ $pig->gender === 'M' ? 'Male' : 'Female' }}</div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusClasses }}">
                                            {{ $status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-600">
                                        @if($death)
                                            <div class="font-semibold text-gray-900">{{ optional($death->death_date)->format('M d, Y') }}</div>
                                            <div class="text-xs text-gray-500">{{ $death->death_cause ?? 'Death recorded' }}</div>
                                        @elseif($sale)
                                            <div class="font-semibold text-gray-900">{{ optional($sale->sale_date)->format('M d, Y') }}</div>
                                            <div class="text-xs text-gray-500">{{ $sale->buyer_name ?: 'Buyer not recorded' }}</div>
                                        @else
                                            <div class="font-semibold text-gray-900">No linked sale/death</div>
                                            <div class="text-xs text-gray-500">Likely manually archived</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex items-center gap-3">
                                            <a href="{{ route('pigs.show', $pig->id) }}" class="text-indigo-600 text-sm font-semibold hover:text-indigo-800">View</a>
                                            <form action="{{ route('pigs.restore', $pig->id) }}" method="POST" onsubmit="return confirm('Restore this record to active pigs?');">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="text-sm font-semibold text-green-600 hover:text-green-700">Restore</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-12 text-center text-gray-500">
                                        <p class="font-semibold">No archived pigs found</p>
                                        <p class="text-xs text-gray-400 mt-1">Records will appear here after sales, deaths, or manual archive actions.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($pigs->hasPages())
                    <div class="px-6 py-4 border-t border-gray-100">
                        {{ $pigs->links() }}
                    </div>
                @endif
            </div>

            <div class="bg-white rounded-xl shadow border border-gray-100 mt-8">
                <div class="px-6 py-4 border-b border-gray-100 bg-yellow-50">
                    <h2 class="text-lg text-yellow-900 font-bold">Archived Piglet Death Records</h2>
                    <p class="text-xs text-yellow-700">Piglet deaths that have been soft-deleted. Restore if mistakenly recorded.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Archived Date</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Record</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Type</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Death Date</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Details</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @forelse($pigletDeaths as $death)
                                <tr class="hover:bg-yellow-50">
                                    <td class="px-4 py-4 text-sm text-gray-600 whitespace-nowrap">
                                        <div class="font-semibold text-gray-900">{{ optional($death->deleted_at)->format('M d, Y') }}</div>
                                        <div class="text-xs text-gray-400">{{ optional($death->deleted_at)->format('h:i A') }} · {{ optional($death->deleted_at)->diffForHumans() }}</div>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-900">
                                        <div class="font-semibold">{{ $death->farrowing->sow->ear_tag_number ?? 'N/A' }}</div>
                                        <div class="text-xs text-gray-500">Sow {{ $death->farrowing->sow->code ?? 'N/A' }}</div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-900">Piglet Death</span>
                                        <div class="text-xs text-gray-500 mt-1">Litter-level record</div>
                                    </td>
                                    <td class="px-4 py-4 text-sm">{{ optional($death->death_date)->format('M d, Y') }}</td>
                                    <td class="px-4 py-4 text-sm text-gray-700">
                                        <div><span class="font-semibold text-red-700">{{ $death->count }}</span> piglet(s)</div>
                                        <div class="text-xs text-gray-500">{{ $death->cause ?? 'Cause not recorded' }}</div>
                                        @if($death->recordedBy)
                                            <div class="text-xs text-gray-400 mt-1">Recorded by {{ $death->recordedBy->name }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <form action="{{ route('pigs.piglet-death-restore', $death->id) }}" method="POST" onsubmit="return confirm('Restore this piglet death record?');">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded bg-yellow-200 text-yellow-900 hover:bg-yellow-300 transition">
                                                Restore
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-12 text-center text-gray-500">
                                        <p class="font-semibold">No archived piglet deaths</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($pigletDeaths->hasPages())
                    <div class="px-6 py-4 border-t border-gray-100">
                        {{ $pigletDeaths->links() }}
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-app-layout>
