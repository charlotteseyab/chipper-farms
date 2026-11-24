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

    @if($hasLeft)
        <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700" role="status">
            This pig has left the farm (sold, died or removed). Its health history is kept here for traceability.
        </div>
    @endif
    @if($withdrawal['restricted'])
        <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
            <p class="font-semibold">Under medicine withdrawal until {{ $withdrawal['until']->format('d M Y') }}</p>
            <p class="mt-1">It cannot be sold before {{ $withdrawal['until']->addDay()->format('d M Y') }}.
                @foreach($withdrawal['doses'] as $dose){{ $loop->first ? 'Because of ' : ', ' }}{{ $dose->medication_name }} given {{ $dose->administration_date->format('d M Y') }} ({{ $dose->withdrawal_period_days }} days){{ $loop->last ? '.' : '' }}@endforeach
            </p>
        </div>
    @elseif($withdrawal['cautions']->isNotEmpty())
        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <p class="font-semibold">Withdrawal not recorded</p>
            <p class="mt-1">@foreach($withdrawal['cautions']->take(3) as $dose){{ $loop->first ? '' : ', ' }}{{ $dose->medication_name }} given {{ $dose->administration_date->format('d M Y') }}@endforeach has no withdrawal period recorded. Check the label before selling.</p>
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
            @if(auth()->user()->hasPermission('export_health_reports'))
            <a href="{{ route('health.export-pdf', $pig) }}" class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-lg bg-gradient-to-r from-emerald-600 to-teal-600 text-white hover:from-emerald-700 hover:to-teal-700 transition shadow-md">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 5H7a2 2 0 01-2-2V6a2 2 0 012-2h5l5 5v7a2 2 0 01-2 2z"></path></svg>
                Export Health PDF
            </a>
            @endif
            @if($lastRecord && auth()->user()?->can('update', $lastRecord))
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
        
        <!-- At a glance -->
        <x-health.glance :cards="$glance" class="md:col-span-2 lg:col-span-3 content-start lg:grid-cols-2" />
    </div>

    <section class="mb-8 rounded-xl border border-gray-100 bg-white p-6 shadow-xl" aria-labelledby="health-alerts-heading">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <h2 id="health-alerts-heading" class="text-xl font-semibold text-gray-800">Health alerts</h2>
            <a href="{{ route('health.alerts') }}" class="text-sm font-semibold text-emerald-700">All farm alerts →</a>
        </div>
        <x-health.alert-list :alerts="$healthAlerts" :show-state="true"
            :empty="'No vaccination, deworming, treatment or follow-up due in the next '.\App\Services\Health\HealthAlertService::defaultDays().' days.'" />
    </section>


    <!-- Health timeline: everything that happened to this animal, newest first -->
    @php
        $kindStyle = ['examination' => 'bg-violet-500', 'case' => 'bg-red-500', 'follow_up' => 'bg-amber-500', 'course' => 'bg-sky-500',
            'dose' => 'bg-blue-600', 'withdrawal' => 'bg-emerald-500', 'exit' => 'bg-slate-500'];
    @endphp
    <section class="mb-8 rounded-xl border border-gray-100 bg-white p-6 shadow-xl" x-data="{ all: false }">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold text-gray-800">Health timeline</h2>
            <div class="flex flex-wrap gap-2 text-sm">
                @if(! $pig->trashed())
                    @can('create', \App\Models\TreatmentCourse::class)
                        <a href="{{ route('treatment-courses.create', ['pig' => $pig->id]) }}" class="rounded-lg border border-sky-200 px-3 py-1.5 font-semibold text-sky-800 hover:bg-sky-50">Prescribe a course</a>
                    @endcan
                @endif
                <a href="{{ route('vet-visits.index', ['animal' => $pig->code]) }}" class="rounded-lg border px-3 py-1.5 font-semibold text-slate-700 hover:bg-slate-50">Vet visits</a>
            </div>
        </div>
        <p class="mt-1 text-sm text-gray-500">Permanent record for {{ $pig->code }}. Each entry shows the tag worn that day.</p>
        <ol class="mt-4 space-y-3">
            @forelse($timeline as $i => $event)
                <li class="flex gap-3" @if($i >= 12) x-show="all" x-cloak @endif>
                    <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full {{ $kindStyle[$event['kind']] ?? 'bg-slate-400' }}" aria-hidden="true"></span>
                    <div class="min-w-0">
                        <p class="text-sm {{ $event['struck'] ? 'text-gray-400 line-through' : 'text-gray-900' }}">
                            <span class="font-mono text-xs text-gray-500">{{ $event['at']->format('j M Y') }}</span>
                            @if($event['url'])<a href="{{ $event['url'] }}" class="font-semibold hover:underline">{{ $event['title'] }}</a>@else<span class="font-semibold">{{ $event['title'] }}</span>@endif
                        </p>
                        <p class="text-xs text-gray-500">{{ $event['tag'] ? 'Tag '.$event['tag'].' · ' : '' }}{{ $event['detail'] }}</p>
                    </div>
                </li>
            @empty
                <li class="text-sm text-gray-500">Nothing recorded yet.</li>
            @endforelse
        </ol>
        @if($timeline->count() > 12)<button type="button" @click="all = !all" class="mt-3 text-sm font-semibold text-indigo-700" x-text="all ? 'Show less' : 'Show all {{ $timeline->count() }} entries'"></button>@endif
    </section>

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
                    <div class="border-l-4 {{ $hr->isVoided() ? 'border-gray-300 bg-gray-50 opacity-75' : $borderClass }} rounded-r-lg p-4 shadow-sm transition hover:shadow-md">
                        <div class="flex items-start justify-between mb-2">
                            <div class="font-semibold text-gray-900 flex items-center">
                                {{ $hr->record_date->format('M d, Y') }}<div class="text-xs text-gray-500">{{ $pig->identityAt($hr->record_date) }}</div>
                            </div>
                            @if($hr->isVoided())
                                <span class="px-3 py-0.5 text-xs rounded-full font-bold uppercase bg-gray-200 text-gray-700">Voided</span>
                            @else
                                <span class="px-3 py-0.5 text-xs rounded-full font-bold uppercase">{{ ucfirst($hr->severity ?? '') }}</span>
                            @endif
                        </div>
                        @if($hr->isVoided())
                            <p class="mb-2 text-xs text-gray-600">Voided {{ $hr->voided_at->format('M d, Y') }} by {{ $hr->voidedBy?->name ?? '—' }}: {{ $hr->void_reason }}</p>
                        @endif
                        @if($hr->legacy_severity)
                            <p class="mb-1 text-xs text-gray-500">Originally entered severity: “{{ $hr->legacy_severity }}”</p>
                        @endif
                        
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

                        @if($hr->scope === 'group')<p class="mb-1 text-xs text-gray-500">Group case{{ $hr->group_label_snapshot ? ': '.$hr->group_label_snapshot : '' }}</p>@endif
                        @if(! $hr->isVoided())
                            <div class="mt-3 pt-2 border-t border-gray-200 flex flex-wrap items-start justify-end gap-3 text-xs">
                                <a href="{{ route('health-cases.show', $hr) }}" class="text-gray-700 hover:text-gray-900 font-medium">Open case</a>
                                @can('update', $hr)
                                    <a href="{{ route('health.records.edit', $hr) }}" class="text-indigo-600 hover:text-indigo-800 font-medium">Edit</a>
                                @endcan
                                @can('void', $hr)
                                    <details class="text-right">
                                        <summary class="cursor-pointer font-medium text-red-600 hover:text-red-800">Void</summary>
                                        <form method="POST" action="{{ route('health.records.void', $hr) }}" class="mt-2 flex gap-2">
                                            @csrf
                                            <input name="void_reason" required minlength="5" maxlength="2000" placeholder="Why is this record wrong?" class="w-56 rounded border-gray-300 text-xs">
                                            <button class="rounded bg-red-600 px-2 py-1 font-semibold text-white">Void</button>
                                        </form>
                                    </details>
                                @endcan
                            </div>
                        @endif
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
                    <x-health.pagination :paginator="$healthRecords" label="records" />
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
                    <div class="border-l-4 {{ $med->isVoided() ? 'border-gray-300 bg-gray-50 opacity-75' : 'border-blue-500' }} rounded-r-lg p-4 shadow-sm transition hover:shadow-md">
                        <div class="flex items-start justify-between mb-2">
                            <div class="font-semibold text-gray-900 flex items-center">
                                {{ $med->administration_date->format('M d, Y') }}<div class="text-xs text-gray-500">{{ $pig->identityAt($med->administration_date) }}</div>
                            </div>
                            @if($med->isVoided())
                                <span class="px-3 py-0.5 text-xs rounded-full font-bold uppercase bg-gray-200 text-gray-700">{{ $med->correctedBy ? 'Corrected' : 'Voided' }}</span>
                            @else
                                <div class="text-sm text-gray-600    px-2 py-0.5 rounded font-medium">{{ $med->route ?? 'N/A' }}</div>
                            @endif
                        </div>
                        @if($med->isVoided())
                            <p class="mb-2 text-xs text-gray-600">{{ $med->voided_at->format('M d, Y') }} by {{ $med->voidedBy?->name ?? '—' }}: {{ $med->void_reason }}</p>
                        @endif

                        <div class="space-y-1 text-sm">
                            <div class="text-gray-800">
                                <span class="font-bold">Medication:</span> {{ $med->medication_name }}
                            </div>
                            <div class="text-gray-800">
                                <span class="font-bold">Dosage:</span> {{ $med->dosage }}
                            </div>
                            <div class="{{ $med->withdrawal_period_days === null ? 'text-amber-700' : 'text-gray-700' }}">
                                <span class="font-bold">Withdrawal:</span>
                                {{ $med->withdrawal_period_days === null ? 'Not recorded' : $med->withdrawal_period_days.' days (until '.$med->withdrawal_ends_on?->format('M d, Y').')' }}
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

                        <div class="mt-3 pt-2 border-t border-gray-200 flex justify-end gap-3 text-xs">
                            <a href="{{ route('medications.show', $med) }}" class="text-gray-600 hover:text-gray-800 font-medium">View</a>
                            @can('correct', $med)
                                <a href="{{ route('medications.edit', $med) }}" class="text-indigo-600 hover:text-indigo-800 font-medium">Correct</a>
                            @endcan
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
                    <x-health.pagination :paginator="$medicationRecords" label="doses" />
                </div>
            </div>
        </div>
    </div>
</div>

@if($lastRecord && auth()->user()?->can('update', $lastRecord))
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

            <form method="POST" action="{{ route('health.records.status', $lastRecord) }}" class="p-6">
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
                            @foreach(\App\Models\HealthRecord::SEVERITIES as $value => $label)
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
