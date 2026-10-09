<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-10">
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Health</h1>
                <p class="mt-1 text-sm text-gray-500">What needs attention today, then every animal with a health record.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('health.worklist') }}" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Treatment worklist</a>
                @can('create', \App\Models\MedicationDosingEvent::class)<a href="{{ route('health.create-medication') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700">Record a dose</a>@endcan
                @can('create', \App\Models\TreatmentCourse::class)<a href="{{ route('treatment-courses.create') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700">Prescribe a course</a>@endcan
                <a href="{{ route('health.reports') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700">Reports</a>
            </div>
        </div>
        @php
            $cards = [
                ['Open cases', $counts['open_cases'], route('health.reports', ['tab' => 'cases', 'status' => 'active'])],
                ['Doses due today or overdue', $counts['doses_due'], route('health.worklist')],
                ['Follow-ups due', $counts['follow_ups_due'], route('health.worklist').'#follow-ups'],
                ['Animals under withdrawal', $counts['under_withdrawal'], route('health.reports', ['tab' => 'stock']).'#withdrawal'],
            ];
        @endphp
        <div class="mb-8 grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach($cards as [$label, $count, $url])
                <a href="{{ $url }}" class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm transition hover:border-emerald-200 hover:shadow">
                    <p class="text-3xl font-bold tabular-nums {{ $count > 0 ? 'text-gray-900' : 'text-gray-400' }}">{{ $count }}</p>
                    <p class="mt-1 text-sm text-gray-600">{{ $label }}</p>
                </a>
            @endforeach
        </div>
        @if(request('attention') === 'open_critical')
            <div class="mb-5 flex flex-col gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 sm:flex-row sm:items-center sm:justify-between">
                <div><strong>Showing critical cases requiring action.</strong> These animals have an unresolved high or severe health record.</div>
                <a href="{{ route('health.index') }}" class="font-bold underline">Clear filter</a>
            </div>
        @endif
        <!-- Filter and Action Card -->
        <div class="bg-white p-6 md:p-8 shadow-xl rounded-xl mb-8 border border-gray-100">
            <form method="get">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-6 md:gap-4">
                    <!-- Search Query -->
                    <div class="col-span-2">
                        <label for="q" class="block text-sm font-medium text-gray-700">Search Pig ID/Code/Breed</label>
                        <input id="q" type="text" name="q" value="{{ request('q') }}" placeholder="Search ear tag, code, breed..." class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" />
                    </div>

                    <!-- Condition -->
                    <div class="col-span-1">
                        <label for="condition" class="block text-sm font-medium text-gray-700">Condition</label>
                        <input id="condition" type="text" name="condition" value="{{ request('condition') }}" placeholder="e.g., Pneumonia" class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" />
                    </div>

                    <!-- Severity -->
                    <div class="col-span-1">
                        <label for="severity" class="block text-sm font-medium text-gray-700">Severity</label>
                        <select name="severity" id="severity" class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                            <option value="">Any Severity</option>
                            @foreach(\App\Models\HealthRecord::SEVERITIES as $value => $label)
                                <option value="{{ $value }}" @selected(request('severity')===$value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <label class="mt-2 flex items-center gap-2 text-sm text-gray-600">
                            <input type="checkbox" name="include_departed" value="1" @checked(request()->boolean('include_departed')) class="rounded border-gray-300">
                            Include sold and dead pigs
                        </label>
                    </div>

                    <!-- Date Range -->
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Health Record Date Range</label>
                        <div class="flex gap-3">
                            <input type="date" name="from" value="{{ request('from') }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" />
                            <input type="date" name="to" value="{{ request('to') }}" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" />
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="mt-6 flex flex-col sm:flex-row justify-between gap-3 pt-4 border-t border-gray-100">
                    <div class="flex flex-col sm:flex-row gap-3">
                        <a href="{{ route('health.create') }}" class="w-full sm:w-auto px-4 py-2 text-sm font-medium rounded-lg bg-green-600 text-white hover:bg-green-700 transition duration-150 shadow-md text-center">
                            <svg class="inline-block w-4 h-4 mr-1 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                            Add Health Record
                        </a>
                        <!-- <a href="{{ route('health.create-medication') }}" class="w-full sm:w-auto px-4 py-2 text-sm font-medium rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition duration-150 shadow-md text-center">
                            <svg class="inline-block w-4 h-4 mr-1 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                            Add Medication
                        </a> -->
                    </div>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <a href="{{ route('health.index') }}" class="w-full sm:w-auto px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition duration-150 text-center">
                            Reset Filters
                        </a>
                        <button type="submit" class="w-full sm:w-auto px-4 py-2 text-sm font-medium rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 transition duration-150 shadow-md">
                            <svg class="inline-block w-4 h-4 mr-1 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707v7l-4 4v-7a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                            Apply Filter
                        </button>
                        @if(auth()->user()->hasPermission('export_health_reports'))
                        <a href="{{ route('health.export', request()->query()) }}" class="w-full sm:w-auto px-4 py-2 text-sm font-medium rounded-lg bg-gray-800 text-white hover:bg-gray-700 transition duration-150 shadow-md text-center">
                            Export CSV
                        </a>
                        @endif
                    </div>
                </div>
            </form>
        </div>

        <!-- Pig Health Table Card -->
        <div class="bg-white shadow-xl rounded-xl overflow-hidden border border-gray-100">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Pig</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Breed / Stock</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Age</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Last Health Record</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Last Medication</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        @forelse($pigs as $pig)
                        <tr class="hover:bg-indigo-50/50 transition duration-100">
                            @php
                                $hr = $pig->healthRecords->first();
                                $med = $pig->medicationAdministrations->first();

                                $severity = strtolower((string) ($hr->severity ?? ''));
                                $isSevere = in_array($severity, ['severe', 'high'], true);
                                $isModerate = in_array($severity, ['moderate', 'medium'], true);
                                $hasTreatmentPlan = $hr && filled($hr->treatment_plan);
                                $hasMedication = !is_null($med);
                                $hasMedicationAfterRecord = $hr && $med
                                    ? $med->administration_date->gte($hr->record_date)
                                    : false;
                                $isRecovered = $hr && (
                                    $hr->outcome === 'recovered'
                                    || ($hr->treatment_status === 'completed' && !empty($hr->resolved_at))
                                );

                                if (!$hr) {
                                    $healthStatusText = 'Healthy / Stable';
                                    $healthStatusClass = 'text-green-600';
                                } elseif ($isRecovered) {
                                    $healthStatusText = 'Recovered / Resolved';
                                    $healthStatusClass = 'text-emerald-700 font-semibold';
                                } elseif ($hr->outcome === 'worsened') {
                                    $healthStatusText = 'Worsening / High Risk';
                                    $healthStatusClass = 'text-red-600 font-semibold';
                                } elseif ($hr->treatment_status === 'completed') {
                                    $healthStatusText = 'Treatment Completed';
                                    $healthStatusClass = 'text-emerald-700 font-semibold';
                                } elseif ($hr->treatment_status === 'stopped') {
                                    $healthStatusText = 'Treatment Stopped';
                                    $healthStatusClass = 'text-orange-700 font-semibold';
                                } elseif ($hr->outcome === 'improved') {
                                    $healthStatusText = 'Improving';
                                    $healthStatusClass = 'text-blue-700 font-semibold';
                                } elseif ($hr->treatment_status === 'ongoing') {
                                    $healthStatusText = 'Under Treatment';
                                    $healthStatusClass = 'text-yellow-600 font-semibold';
                                } elseif ($hr->treatment_status === 'planned') {
                                    $healthStatusText = 'Treatment Planned';
                                    $healthStatusClass = 'text-amber-600 font-semibold';
                                } elseif ($isSevere) {
                                    $healthStatusText = 'Active High Risk';
                                    $healthStatusClass = 'text-red-600 font-semibold';
                                } elseif (!$hasTreatmentPlan && !$hasMedication) {
                                    $healthStatusText = 'Needs Treatment Plan';
                                    $healthStatusClass = 'text-amber-600 font-semibold';
                                } elseif ($isModerate || $hasTreatmentPlan || $hasMedicationAfterRecord) {
                                    $healthStatusText = 'Under Treatment';
                                    $healthStatusClass = 'text-yellow-600 font-semibold';
                                } else {
                                    $healthStatusText = 'Stable';
                                    $healthStatusClass = 'text-green-600 font-semibold';
                                }
                            @endphp
                            <td class="px-6 py-4 whitespace-nowrap">
                                <a href="{{ route('health.show', $pig) }}" class="text-lg font-semibold text-indigo-600 hover:text-indigo-800 transition duration-150">
                                    {{ $pig->code ?? $pig->ear_tag_number }}
                                </a>
                                <div class="text-sm text-gray-500">{{ $pig->gender === 'M' ? 'Male' : 'Female' }}</div>
                                @if($pig->trashed())<span class="mt-1 inline-block rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">Left the farm</span>@endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                <div class="font-medium">{{ $pig->breed }}</div>
                                <div class="text-gray-500 text-xs">{{ $pig->stock_type }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ $pig->age_display }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                @if($hr)
                                    <div class="font-medium text-gray-900">{{ $hr->record_date->format('M d, Y') }}</div>
                                    <div class="text-sm text-gray-600">{{ $hr->condition }}@if($hr->severity) ({{ ucfirst($hr->severity) }})@endif</div>
                                    <div class="text-xs {{ $healthStatusClass }}">{{ $healthStatusText }}</div>
                                    <div class="mt-1 text-[11px] text-gray-500">
                                        Treatment: {{ ucfirst($hr->treatment_status ?: 'Not set') }}
                                        <span class="mx-1">·</span>
                                        Outcome: {{ ucfirst($hr->outcome ?: 'Not assessed') }}
                                    </div>
                                @else
                                    <span class="text-gray-400 italic">No recent record</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                @if($med)
                                    <div class="font-medium text-gray-900">{{ $med->administration_date->format('M d, Y') }}</div>
                                    <div class="text-gray-600 text-sm">{{ $med->medication_name }}</div>
                                @else
                                    <span class="text-gray-400 italic">None</span>
                                @endif
                            </td>
                            
                            {{-- ACTIONS DROPDOWN IMPLEMENTATION --}}
                            <td class="px-6 py-4 whitespace-nowrap text-right text-xs font-medium">
                                <div class="relative inline-block text-left" x-data="{ open: false }" @click.away="open = false">
                                    <!-- Dropdown Toggle Button (Kebab Icon) -->
                                    <button type="button" @click="open = !open" class="text-gray-500 hover:text-gray-700 p-1 rounded-full focus:outline-none focus:ring-2 focus:ring-indigo-500 transition duration-150">
                                        {{-- Kebab Icon (Three vertical dots) --}}
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg>
                                    </button>

                                    <!-- Dropdown Menu -->
                                    <div x-show="open" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="transform opacity-0 scale-95" x-transition:enter-end="transform opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="transform opacity-100 scale-100" x-transition:leave-end="transform opacity-0 scale-95"
                                        class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-10"
                                        role="menu" aria-orientation="vertical" aria-labelledby="options-menu">
                                        <div class="py-1" role="none">
                                            <a href="{{ route('health.show', $pig) }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50 hover:text-indigo-600 w-full text-left" role="menuitem">
                                                View Pig Health
                                            </a>
                                            @if($hr && auth()->user()?->can('update', $hr))
                                                <a href="{{ route('health.records.edit', $hr) }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50 hover:text-indigo-600 w-full text-left" role="menuitem">
                                                    Edit Latest Record
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-lg text-gray-500 bg-gray-50">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                <p class="mt-1">No health records found.</p>
                                <p class="text-sm text-gray-400">Select an animal and add its first health record to show it here.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <!-- Pagination (embedded, simple style) -->
            @if($pigs->hasPages())
                    <div class="px-6 py-4 border-t border-gray-200 bg-white">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-700">
                                Showing {{ $pigs->firstItem() }} to {{ $pigs->lastItem() }} of {{ $pigs->total() }} results
                            </div>
                            <div class="flex items-center space-x-2">
                                <x-simple-pagination :paginator="$pigs" />
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
