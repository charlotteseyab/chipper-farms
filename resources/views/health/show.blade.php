<x-app-layout>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-10">

    <!-- 1. Header and Primary Actions -->
    <div class="mb-6 flex justify-between items-center pt-4">
        <div>
            <h1 class="text-3xl font-extrabold text-gray-900 flex items-center">
                <i class="fas fa-pig text-indigo-600 mr-3"></i>
                Pig ID: <span class="ml-2 text-indigo-700">{{ $pig->code ?? $pig->ear_tag_number }}</span>
            </h1>
            <p class="mt-1 text-base text-gray-500">{{ $pig->breed }} ({{ $pig->stock_type }})</p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('health.index') }}" class="px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition duration-150 shadow-sm">
                <svg class="inline-block w-4 h-4 mr-1 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Back to List
            </a>
        </div>
    </div>

    <!-- 2. Key Metrics and Status Bar -->
    <div class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-5 gap-6 mb-8">
        <!-- Main Info Card -->
        <div class="md:col-span-2 lg:col-span-2 bg-white shadow-lg rounded-xl p-6 border border-gray-100">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Current Status</h2>
            <dl class="space-y-3">
                <div class="flex items-center">
                    <dt class="text-sm font-medium text-gray-500 w-24">Gender:</dt>
                    <dd class="text-sm font-semibold text-gray-700">{{ $pig->gender === 'M' ? 'Male' : 'Female' }}</dd>
                </div>
                <div class="flex items-center">
                    <dt class="text-sm font-medium text-gray-500 w-24">Age:</dt>
                    <dd class="text-sm font-semibold text-gray-700">{{ $pig->age_display ?? 'N/A' }}</dd>
                </div>
                @php
                    $lastRecord = $pig->healthRecords->sortByDesc('record_date')->first();
                    $statusColor = $lastRecord ? 
                        ($lastRecord->severity === 'severe' ? 'text-red-600' : 
                         ($lastRecord->severity === 'moderate' ? 'text-yellow-600' : 'text-green-600')) : 
                        'text-green-600';
                    $statusText = $lastRecord ? 
                        ($lastRecord->severity === 'severe' ? 'Active High Risk' : 
                         ($lastRecord->severity === 'moderate' ? 'Under Treatment' : 'Stable')) : 
                        'Healthy / Stable';
                @endphp
                <div class="flex items-center pt-3 border-t border-gray-100">
                    <dt class="text-sm font-medium text-gray-500 w-24">Health:</dt>
                    <dd class="text-sm font-bold {{ $statusColor }}">{{ $statusText }}</dd>
                </div>
            </dl>
        </div>
        
        <!-- Metrics Cards -->
        <div class="md:col-span-2 lg:col-span-3 grid grid-cols-1 sm:grid-cols-3 gap-6">
            <div class="bg-white shadow-lg rounded-xl p-5 border border-gray-100">
                <p class="text-sm font-medium text-indigo-500">Total Records</p>
                <p class="text-3xl font-bold text-gray-900 mt-1">{{ $pig->healthRecords->count() }}</p>
            </div>
            <div class="bg-white shadow-lg rounded-xl p-5 border border-gray-100">
                <p class="text-sm font-medium text-red-500">Last Record</p>
                <p class="text-lg font-bold text-gray-900 mt-1">
                    {{ $lastRecord ? $lastRecord->record_date->format('M d, Y') : 'N/A' }}
                </p>
                <p class="text-xs text-gray-500 truncate">{{ $lastRecord->condition ?? 'No data' }}</p>
            </div>
            <div class="bg-white shadow-lg rounded-xl p-5 border border-gray-100">
                <p class="text-sm font-medium text-blue-500">Last Medication</p>
                <p class="text-lg font-bold text-gray-900 mt-1">
                    @php $lastMed = $pig->medicationAdministrations->sortByDesc('administration_date')->first(); @endphp
                    {{ $lastMed ? $lastMed->administration_date->format('M d, Y') : 'N/A' }}
                </p>
                <p class="text-xs text-gray-500 truncate">{{ $lastMed->medication_name ?? 'No data' }}</p>
            </div>
        </div>
    </div>


    <!-- 3. Health Timeline and Medication Timeline -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        
        <!-- Health Records Timeline -->
        <div class="bg-white shadow-xl rounded-xl overflow-hidden border border-gray-100">
            <div class="bg-red-50 p-4 border-b border-red-200 flex justify-between items-center">
                <h2 class="text-xl font-semibold text-red-800 flex items-center">
                    <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Health Records
                </h2>
                <a href="{{ route('health.createForPig', $pig) }}" class="text-sm font-medium text-red-600 hover:text-red-700 transition duration-150 flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                    Add Record
                </a>
            </div>
            
            <div class="p-6">
                <div class="space-y-4 max-h-[500px] overflow-y-auto pr-2">
                    @forelse($pig->healthRecords->sortByDesc('record_date') as $hr)
                    @php
                        $borderClass = $hr->severity === 'severe' ? 'border-red-500' : ($hr->severity === 'moderate' ? 'border-yellow-500' : 'border-red-500');
                       
                        $pillClass = $hr->severity === 'severe' ? 'bg-red-200 text-red-800' : ($hr->severity === 'moderate' ? 'bg-yellow-200 text-yellow-800' : 'bg-red-200 text-red-800');
                    @endphp
                    <div class="border-l-4 {{ $borderClass }} rounded-r-lg p-4 shadow-sm transition hover:shadow-md">
                        <div class="flex items-start justify-between mb-2">
                            <div class="font-semibold text-gray-900 flex items-center">
                                {{ $hr->record_date->format('M d, Y') }}
                            </div>
                            <span class="px-3 py-0.5 text-xs rounded-full font-bold uppercase">{{ ucfirst($hr->severity) }}</span>
                        </div>
                        
                        <div class="space-y-1">
                            <div class="text-gray-800 text-sm">
                                <span class="font-bold">Condition:</span> {{ $hr->condition }}
                            </div>
                            @if($hr->notes)
                            <div class="text-gray-600 text-sm italic border-t border-gray-200 pt-2 mt-2">
                                {{ Str::limit($hr->notes, 80) }}
                            </div>
                            @endif
                        </div>

                        {{-- Record Actions (assuming individual routes exist for the HealthRecord model) --}}
                        <!-- <div class="mt-3 pt-2 border-t border-gray-200 flex justify-end gap-3 text-xs">
                            <a href="{{ route('health.edit', $hr) }}" class="text-indigo-600 hover:text-indigo-800 font-medium">Edit</a>
                        </div> -->
                    </div>
                    @empty
                    <div class="text-center py-8 text-gray-500">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <p class="mt-2 text-lg">No health records yet</p>
                        <p class="text-sm text-gray-400">Add the first health record for this pig.</p>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Medications Timeline -->
        <div class="bg-white shadow-xl rounded-xl overflow-hidden border border-gray-100">
            <div class="bg-blue-50 p-4 border-b border-blue-200 flex justify-between items-center">
                <h2 class="text-xl font-semibold text-blue-800 flex items-center">
                    <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11V7a5 5 0 0110 0v4m-2 0a2 2 0 012 2v2a2 2 0 01-2 2H9a2 2 0 01-2-2v-2a2 2 0 012-2h6z"></path></svg>
                    Medication Administrations
                </h2>
                <a href="{{ route('medications.createForPig', $pig) }}" class="text-sm font-medium text-blue-600 hover:text-blue-700 transition duration-150 flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                    Add Medication
                </a>
            </div>
            
            <div class="p-6">
                <div class="space-y-4 max-h-[500px] overflow-y-auto pr-2">
                    @forelse($pig->medicationAdministrations->sortByDesc('administration_date') as $med)
                    <div class="border-l-4 border-blue-500 rounded-r-lg p-4 shadow-sm transition hover:shadow-md">
                        <div class="flex items-start justify-between mb-2">
                            <div class="font-semibold text-gray-900 flex items-center">
                                {{ $med->administration_date->format('M d, Y') }}
                            </div>
                            <div class="text-sm text-gray-600    px-2 py-0.5 rounded font-medium">{{ $med->route ?? 'N/A' }}</div>
                        </div>

                        <div class="space-y-1 text-sm">
                            <div class="text-gray-800">
                                <span class="font-bold">Medication:</span> {{ $med->medication_name }}
                            </div>
                            <div class="text-gray-800">
                                <span class="font-bold">Dosage:</span> {{ $med->dosage }}
                            </div>
                            @if($med->purpose)
                            <div class="text-gray-600">
                                <span class="font-bold">Purpose:</span> {{ $med->purpose }}
                            </div>
                            @endif
                            @if($med->notes)
                            <div class="text-gray-600 text-sm italic border-t border-gray-200 pt-2 mt-2">
                                {{ Str::limit($med->notes, 80) }}
                            </div>
                            @endif
                        </div>

                        {{-- Medication Actions (assuming individual routes exist for the MedicationAdministration model) --}}
                        <div class="mt-3 pt-2 border-t border-gray-200 flex justify-end gap-3 text-xs">
                            <!-- <a href="{{ route('medications.edit', $med) }}" class="text-indigo-600 hover:text-indigo-800 font-medium">Edit</a> -->
                        </div>
                    </div>
                    @empty
                    <div class="text-center py-8 text-gray-500">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11V7a5 5 0 0110 0v4m-2 0a2 2 0 012 2v2a2 2 0 01-2 2H9a2 2 0 01-2-2v-2a2 2 0 012-2h6z"></path>
                        </svg>
                        <p class="mt-2 text-lg">No medications recorded yet</p>
                        <p class="text-sm text-gray-400">Add the first medication record for this pig.</p>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
<x-copyright-minimal />
</x-app-layout>
