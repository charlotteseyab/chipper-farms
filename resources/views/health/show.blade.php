<x-app-layout>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-10">

    @if(session('success'))
        <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800" role="alert">
            {{ session('error') }}
        </div>
    @endif

    <!-- 1. Header and Primary Actions -->
    <div class="mb-6 flex justify-between items-center pt-4">
        <div>
            <h1 class="text-3xl font-extrabold text-gray-900 flex items-center">
                <i class="fas fa-pig text-indigo-600 mr-3"></i>
                Pig ID: <span class="ml-2 text-indigo-700">{{ $pig->code ?? $pig->ear_tag_number }}</span>
            </h1>
            <p class="mt-1 text-base text-gray-500">{{ $pig->breed }} ({{ $pig->stock_type }})</p>
        </div>
        <div class="flex flex-wrap justify-end gap-3">
            <a href="{{ route('health.export-pdf', $pig) }}" class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-lg bg-gradient-to-r from-emerald-600 to-teal-600 text-white hover:from-emerald-700 hover:to-teal-700 transition shadow-md">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 5H7a2 2 0 01-2-2V6a2 2 0 012-2h5l5 5v7a2 2 0 01-2 2z"></path></svg>
                Export Health PDF
            </a>
            @if(auth()->user()?->hasPermission('manage_health_records'))
                <button type="button" id="open-health-status-modal" @disabled(!$lastRecord)
                    class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 transition shadow-md disabled:cursor-not-allowed disabled:bg-gray-300 disabled:shadow-none"
                    title="{{ $lastRecord ? 'Update the latest health record status' : 'Add a health record before updating status' }}">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    Update Health Status
                </button>
            @endif
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
                    $hasTreatmentPlan = $lastRecord && filled($lastRecord->treatment_plan);
                    $hasMedication = !is_null($lastMedication);
                    $hasMedicationAfterLastRecord = $lastRecord && $lastMedication
                        ? $lastMedication->administration_date->gte($lastRecord->record_date)
                        : false;
                    $severity = strtolower((string) ($lastRecord->severity ?? ''));
                    $isSevere = in_array($severity, ['severe', 'high'], true);
                    $isModerate = in_array($severity, ['moderate', 'medium'], true);
                    $isRecovered = $lastRecord && (
                        $lastRecord->outcome === 'recovered'
                        || ($lastRecord->treatment_status === 'completed' && !empty($lastRecord->resolved_at))
                    );

                    if (!$lastRecord) {
                        $statusColor = 'text-green-600';
                        $statusText = 'Healthy / Stable';
                    } elseif ($isRecovered) {
                        $statusColor = 'text-emerald-700';
                        $statusText = 'Recovered / Resolved';
                    } elseif ($lastRecord->outcome === 'worsened') {
                        $statusColor = 'text-red-600';
                        $statusText = 'Worsening / High Risk';
                    } elseif ($lastRecord->treatment_status === 'completed') {
                        $statusColor = 'text-emerald-700';
                        $statusText = 'Treatment Completed';
                    } elseif ($lastRecord->treatment_status === 'stopped') {
                        $statusColor = 'text-orange-700';
                        $statusText = 'Treatment Stopped';
                    } elseif ($lastRecord->outcome === 'improved') {
                        $statusColor = 'text-blue-700';
                        $statusText = 'Improving';
                    } elseif ($lastRecord->treatment_status === 'ongoing') {
                        $statusColor = 'text-yellow-600';
                        $statusText = 'Under Treatment';
                    } elseif ($lastRecord->treatment_status === 'planned') {
                        $statusColor = 'text-amber-600';
                        $statusText = 'Treatment Planned';
                    } elseif ($isSevere) {
                        $statusColor = 'text-red-600';
                        $statusText = 'Active High Risk';
                    } elseif (!$hasTreatmentPlan && !$hasMedication) {
                        $statusColor = 'text-amber-600';
                        $statusText = 'Needs Treatment Plan';
                    } elseif ($isModerate || $hasTreatmentPlan || $hasMedicationAfterLastRecord) {
                        $statusColor = 'text-yellow-600';
                        $statusText = 'Under Treatment';
                    } else {
                        $statusColor = 'text-green-600';
                        $statusText = 'Stable';
                    }
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
                <p class="text-3xl font-bold text-gray-900 mt-1">{{ $healthRecords->total() }}</p>
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
                    {{ $lastMedication ? $lastMedication->administration_date->format('M d, Y') : 'N/A' }}
                </p>
                <p class="text-xs text-gray-500 truncate">{{ $lastMedication->medication_name ?? 'No data' }}</p>
            </div>
        </div>
    </div>


    <!-- 3. Health Timeline and Medication Timeline -->
    <div class="grid grid-cols-1 items-start gap-8 lg:grid-cols-2">
        
        <!-- Health Records Timeline -->
        <div id="medication-history" class="scroll-mt-28 flex flex-col bg-white shadow-xl rounded-xl overflow-hidden border border-gray-100">
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
            
            <div class="flex flex-1 flex-col p-6">
                <div class="flex-1 space-y-4">
                    @forelse($healthRecords as $hr)
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
                            @if($hr->treatment_status || $hr->outcome)
                            <div class="text-gray-700 text-xs">
                                @if($hr->treatment_status)
                                    <span class="font-semibold">Treatment:</span> {{ ucfirst($hr->treatment_status) }}
                                @endif
                                @if($hr->outcome)
                                    <span class="ml-2 font-semibold">Outcome:</span> {{ ucfirst($hr->outcome) }}
                                @endif
                                @if($hr->resolved_at)
                                    <span class="ml-2">Resolved: {{ $hr->resolved_at->format('M d, Y') }}</span>
                                @endif
                            </div>
                            @endif
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
                <div class="mt-6 border-t border-gray-100 pt-4">
                    <x-simple-pagination :paginator="$healthRecords" />
                </div>
            </div>
        </div>

        <!-- Medications Timeline -->
        <div class="flex flex-col bg-white shadow-xl rounded-xl overflow-hidden border border-gray-100">
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
            
            <div class="flex flex-1 flex-col p-6">
                <div class="flex-1 space-y-4">
                    @forelse($medicationRecords as $med)
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
                <div class="mt-6 border-t border-gray-100 pt-4">
                    <x-simple-pagination :paginator="$medicationRecords" />
                </div>
            </div>
        </div>
    </div>
</div>

@if($lastRecord && auth()->user()?->hasPermission('manage_health_records'))
<div id="health-status-modal" class="fixed inset-0 z-[100] hidden" role="dialog" aria-modal="true" aria-labelledby="health-status-title">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" data-close-health-status></div>
    <div class="relative flex min-h-full items-center justify-center p-4">
        <div class="relative max-h-[calc(100vh-2rem)] w-full max-w-xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
            <div class="bg-gradient-to-r from-indigo-700 to-blue-600 px-6 py-5 text-white">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-indigo-100">Latest health record</p>
                        <h2 id="health-status-title" class="mt-1 text-2xl font-bold">Update Health Status</h2>
                        <p class="mt-1 text-sm text-indigo-100">{{ $lastRecord->condition }} · {{ $lastRecord->record_date->format('M d, Y') }}</p>
                    </div>
                    <button type="button" data-close-health-status class="rounded-lg p-2 text-white/80 hover:bg-white/10 hover:text-white" aria-label="Close modal">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
            </div>

            <form method="POST" action="{{ route('health.status.update', $pig) }}" class="p-6">
                @csrf
                @method('PATCH')

                @if($errors->any())
                    <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        <ul class="list-disc space-y-1 pl-5">
                            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                        </ul>
                    </div>
                @endif

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <label for="status-severity" class="mb-1 block text-sm font-semibold text-gray-700">Severity</label>
                        <select id="status-severity" name="severity" class="w-full rounded-lg border-gray-300">
                            <option value="">Not specified</option>
                            @foreach(['mild' => 'Mild', 'moderate' => 'Moderate', 'severe' => 'Severe', 'low' => 'Low', 'medium' => 'Medium', 'high' => 'High'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('severity', $lastRecord->severity) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="status-treatment" class="mb-1 block text-sm font-semibold text-gray-700">Treatment Status <span class="text-red-500">*</span></label>
                        <select id="status-treatment" name="treatment_status" required class="w-full rounded-lg border-gray-300">
                            @foreach(['planned' => 'Planned', 'ongoing' => 'Ongoing', 'completed' => 'Completed', 'stopped' => 'Stopped'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('treatment_status', $lastRecord->treatment_status ?: 'planned') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="status-outcome" class="mb-1 block text-sm font-semibold text-gray-700">Current Outcome</label>
                        <select id="status-outcome" name="outcome" class="w-full rounded-lg border-gray-300">
                            <option value="">Not assessed</option>
                            @foreach(['recovered' => 'Recovered', 'improved' => 'Improved', 'unchanged' => 'Unchanged', 'worsened' => 'Worsened'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('outcome', $lastRecord->outcome) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="status-resolved-at" class="mb-1 block text-sm font-semibold text-gray-700">Resolved Date</label>
                        <input id="status-resolved-at" type="date" name="resolved_at" min="{{ $lastRecord->record_date->toDateString() }}" max="{{ now()->toDateString() }}" value="{{ old('resolved_at', $lastRecord->resolved_at?->toDateString()) }}" class="w-full rounded-lg border-gray-300">
                    </div>
                </div>

                <div class="mt-5">
                    <label for="status-notes" class="mb-1 block text-sm font-semibold text-gray-700">Status Notes</label>
                    <textarea id="status-notes" name="resolution_notes" rows="4" maxlength="2000" class="w-full rounded-lg border-gray-300" placeholder="Add a concise update on progress, response to treatment, or resolution...">{{ old('resolution_notes', $lastRecord->resolution_notes) }}</textarea>
                </div>

                <div class="mt-6 flex justify-end gap-3 border-t border-gray-100 pt-5">
                    <button type="button" data-close-health-status class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">Save Health Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('health-status-modal');
        const opener = document.getElementById('open-health-status-modal');
        if (!modal || !opener) return;

        const openModal = () => {
            modal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            window.setTimeout(() => {
                const statusSelect = document.getElementById('status-treatment');
                const visibleControl = statusSelect?.nextElementSibling?.querySelector('.searchable-select__trigger');
                (visibleControl || statusSelect)?.focus();
            }, 50);
        };
        const closeModal = () => {
            modal.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
            opener.focus();
        };

        opener.addEventListener('click', openModal);
        modal.querySelectorAll('[data-close-health-status]').forEach(button => button.addEventListener('click', closeModal));
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
        });

        @if($errors->any()) openModal(); @endif
    });
</script>
@endif
</x-app-layout>
