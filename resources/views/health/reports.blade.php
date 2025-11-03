<x-app-layout>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-10">
    
     <!-- Header and Date Filter -->
     <!-- <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-8 bg-white p-6 rounded-xl shadow-lg border-l-4 border-indigo-600">
         <div>
             <h1 class="text-3xl font-extrabold text-gray-900 flex items-center">
                 <svg class="w-8 h-8 mr-3 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                 </svg>
                 Health & Medication Reports
             </h1>
             <p class="text-base text-gray-500 mt-1 ml-11">Comprehensive health metrics and medication administration overview for the selected period.</p>
         </div>
         <form method="get" class="flex items-center gap-2 mt-4 sm:mt-0">
             <select name="days" class="rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                 <option value="7" @selected(($days ?? 30)==7)>Last 7 days</option>
                 <option value="30" @selected(($days ?? 30)==30)>Last 30 days</option>
                 <option value="90" @selected(($days ?? 30)==90)>Last 90 days</option>
             </select>
             <button class="px-4 py-2 rounded-md bg-indigo-600 text-white hover:bg-indigo-700 transition duration-150">Apply</button>
         </form>
     </div> -->

    <!-- Summary Statistics Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        
        <!-- Total Health Records Card -->
        <div class="bg-white shadow-xl rounded-xl p-6 border-t-4 border-red-500">
            <div class="text-sm font-semibold uppercase text-red-500">Total Health Records</div>
            <div class="mt-2 text-4xl font-extrabold text-gray-900">{{ $totalHealthRecords }}</div>
            <p class="text-sm text-gray-400 mt-2">New records in the last {{ $days ?? 30 }} days.</p>
        </div>

        <!-- Total Medications Card -->
        <div class="bg-white shadow-xl rounded-xl p-6 border-t-4 border-teal-500">
            <div class="text-sm font-semibold uppercase text-teal-500">Medications Administered</div>
            <div class="mt-2 text-4xl font-extrabold text-gray-900">{{ $totalMedications }}</div>
            <p class="text-sm text-gray-400 mt-2">Total doses given across the herd.</p>
        </div>

        <!-- Top Conditions Card -->
        <div class="bg-white shadow-xl rounded-xl p-6 border-t-4 border-yellow-500">
            <div class="text-sm font-semibold uppercase text-yellow-500 mb-4">Top Conditions ({{ $days ?? 30 }} days)</div>
            <div class="space-y-3">
                @forelse($topConditions as $c)
                    @php
                        // Simple calculation for visual representation
                        $maxCount = $topConditions->first()->count;
                        $width = $maxCount > 0 ? ($c->count / $maxCount) * 100 : 0;
                    @endphp
                    <div class="text-sm">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-gray-700 font-medium">{{ $c->condition }}</span>
                            <span class="text-gray-900 font-semibold">{{ $c->count }}</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-yellow-400 h-2 rounded-full" style="width: {{ $width }}%"></div>
                        </div>
                    </div>
                @empty
                    <div class="text-gray-400 text-center py-4">No significant condition data recorded.</div>
                @endforelse
            </div>
        </div>
    </div>

     <!-- Recent Health Records Table Card -->
     <div class="bg-white shadow-xl rounded-xl overflow-hidden border border-gray-100 mb-8">
         <div class="p-6">
             <h2 class="text-xl font-semibold text-gray-900">Recent Health Records</h2>
             <p class="text-sm text-gray-500">Latest health conditions and observations.</p>
         </div>

         <div class="overflow-x-auto">
             <table class="min-w-full divide-y divide-gray-200">
                 <thead class="bg-gray-50">
                     <tr>
                         <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Date</th>
                         <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Pig</th>
                         <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Condition</th>
                         <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Severity</th>
                         <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Notes</th>
                     </tr>
                 </thead>
                 <tbody class="bg-white divide-y divide-gray-100">
                     @forelse($recentHealthRecords ?? [] as $hr)
                     <tr class="hover:bg-indigo-50/50 transition duration-100">
                         <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ $hr->record_date->format('M d, Y') }}</td>
                         <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                             <a href="{{ route('health.show', $hr->pig) }}" class="text-indigo-600 hover:text-indigo-800">{{ $hr->pig->code ?? $hr->pig->ear_tag_number }}</a>
                         </td>
                         <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ $hr->condition }}</td>
                         <td class="px-6 py-4 whitespace-nowrap text-sm">
                             @if($hr->severity)
                                 <span class="px-2 py-1 text-xs rounded-full {{
                                     $hr->severity === 'severe' ? 'bg-red-100 text-red-800' : 
                                     ($hr->severity === 'moderate' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800')
                                 }}">{{ ucfirst($hr->severity) }}</span>
                             @else
                                 <span class="text-gray-400">N/A</span>
                             @endif
                         </td>
                         <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ Str::limit($hr->notes, 50) ?? 'N/A' }}</td>
                     </tr>
                     @empty
                     <tr>
                         <td colspan="5" class="px-6 py-10 text-center text-gray-500 bg-gray-50">
                             <p class="text-lg">No recent health records found.</p>
                             <p class="text-sm text-gray-400 mt-1">Check the selected time period in the filter above.</p>
                         </td>
                     </tr>
                     @endforelse
                 </tbody>
             </table>
         </div>
     </div>

     <!-- Recent Medications Table Card -->
     <div class="bg-white shadow-xl rounded-xl overflow-hidden border border-gray-100">
         <div class="p-6">
             <h2 class="text-xl font-semibold text-gray-900">Recent Medication Administrations</h2>
             <p class="text-sm text-gray-500">Detailed logs of recent treatments and medications.</p>
         </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Pig</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Medication</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Purpose</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Dosage</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Route</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @forelse($recentMedications as $m)
                    <tr class="hover:bg-indigo-50/50 transition duration-100">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ $m->administration_date->format('M d, Y') }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                            <a href="{{ route('health.show', $m->pig) }}" class="text-indigo-600 hover:text-indigo-800">{{ $m->pig->code ?? $m->pig->ear_tag_number }}</a>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ $m->medication_name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ $m->purpose ?? 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ $m->dosage }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ $m->route ?? 'N/A' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-10 text-center text-gray-500 bg-gray-50">
                            <p class="text-lg">No recent medications found.</p>
                            <p class="text-sm text-gray-400 mt-1">Check the selected time period in the filter above.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
<x-copyright-minimal />
</x-app-layout>
