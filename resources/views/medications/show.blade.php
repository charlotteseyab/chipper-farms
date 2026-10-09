<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-xl shadow-2xl overflow-hidden border border-gray-100">

            <!-- <div class="bg-indigo-700 p-6 sm:p-8">
                <h1 class="text-3xl font-bold text-white tracking-tight">Medication Record Details</h1>
                <p class="text-indigo-200 mt-1 text-sm">Comprehensive view of the administered treatment.</p>
            </div> -->

            <div class="p-6 sm:p-8">
                @if(session('success'))<p role="status" class="mb-6 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</p>@endif
                @if(session('error'))<p role="alert" class="mb-6 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ session('error') }}</p>@endif
                @if($medication->isVoided())
                    <div class="mb-6 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700">
                        <p class="font-semibold text-gray-900">{{ $medication->correctedBy ? 'Corrected' : 'Voided' }} {{ $medication->voided_at->format('d M Y, H:i') }} by {{ $medication->voidedBy?->name ?? '—' }}</p>
                        <p class="mt-1">{{ $medication->void_reason }}</p>
                        <p class="mt-1 text-xs text-gray-500">This record no longer counts towards withdrawal.</p>
                        @if($medication->correctedBy)
                            <a href="{{ route('medications.show', $medication->correctedBy) }}" class="mt-2 inline-block font-semibold text-indigo-700 underline">View the corrected record</a>
                        @endif
                    </div>
                @endif
                @if($medication->medication_dosing_event_id)
                    <div class="mb-6 rounded-lg border border-emerald-100 bg-emerald-50 p-4 text-sm text-emerald-900">
                        <p class="font-semibold">Part of dose record #{{ $medication->medication_dosing_event_id }}</p>
                        <p class="mt-1">Batch, stock taken and the other animals dosed at the same time are there. Corrections and voids are made on the dose record so the stock goes back too.</p>
                        <a href="{{ route('doses.show', $medication->medication_dosing_event_id) }}" class="mt-2 inline-block font-semibold underline">Open the dose record</a>
                    </div>
                @endif
                @if($medication->corrects)
                    <div class="mb-6 rounded-lg border border-indigo-100 bg-indigo-50 p-4 text-sm text-indigo-900">
                        <p class="font-semibold">This record corrects an earlier one</p>
                        <p class="mt-1">{{ $medication->correction_reason }}</p>
                        <a href="{{ route('medications.show', $medication->corrects) }}" class="mt-2 inline-block font-semibold underline">View the original</a>
                    </div>
                @endif

                <div class="mb-8 p-4 bg-yellow-50 border-l-4 border-yellow-500 rounded-lg">
                    <label class="block text-sm font-medium text-yellow-800 mb-1">Pig ID</label>
                    <div class="font-mono text-2xl font-bold text-yellow-900">
                        {{ $medication->pig?->identityAt($medication->administration_date) ?? '—' }}
                    </div>
                </div>

                <div class="mb-8">
                    <h2 class="text-xl font-semibold text-gray-800 border-b pb-2 mb-4">Medication Information</h2>

                    <div class="mb-4">
                        <label class="block text-gray-500 text-sm font-medium uppercase tracking-wider mb-1">Medication Name</label>
                        <div class="text-2xl font-bold text-indigo-800">{{ $medication->medication_name }}</div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-500 text-sm font-medium uppercase tracking-wider mb-1">Purpose</label>
                        <div class="text-base text-gray-700">{{ $medication->purpose ?? '—' }}</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 border-t pt-6 mb-8">
                    <div>
                        <label class="block text-gray-500 text-sm font-medium uppercase tracking-wider mb-1">Administration Date</label>
                        <div class="text-lg font-semibold text-gray-700">
                            {{ $medication->administration_date ? $medication->administration_date->format('Y-m-d') : '—' }}
                        </div>
                    </div>
                    <div>
                        <label class="block text-gray-500 text-sm font-medium uppercase tracking-wider mb-1">Dosage</label>
                        <div class="text-lg font-semibold text-gray-700">
                            {{ $medication->dosage ?? '—' }}
                        </div>
                    </div>
                    <div>
                        <label class="block text-gray-500 text-sm font-medium uppercase tracking-wider mb-1">Route</label>
                        <div class="text-lg font-semibold text-gray-700">
                            {{ $medication->route ?? '—' }}
                        </div>
                    </div>
                </div>

                <div class="mb-8 border-t pt-6">
                    <label class="block text-gray-500 text-sm font-medium uppercase tracking-wider mb-1">Withdrawal</label>
                    @if($medication->withdrawal_period_days === null)
                        <div class="text-lg font-semibold text-amber-700">Not recorded — check the label before selling</div>
                    @else
                        <div class="text-lg font-semibold text-gray-700">{{ $medication->withdrawal_period_days }} days · restricted through {{ $medication->withdrawal_ends_on?->format('d M Y') }}, can be sold from {{ $medication->withdrawal_ends_on?->addDay()->format('d M Y') }}</div>
                    @endif
                </div>

                <div class="mb-8 border-t pt-6">
                    <label class="block text-gray-500 text-sm font-medium uppercase tracking-wider mb-2">Notes</label>
                    <div class="whitespace-pre-line text-gray-700 bg-gray-50 p-3 rounded-md shadow-inner min-h-[5rem]">
                        {{ $medication->notes ?? 'No additional notes recorded.' }}
                    </div>
                </div>

                <div class="flex flex-wrap items-start justify-end gap-3 border-t pt-6">
                    <a href="{{ $medication->pig_id ? route('health.show', $medication->pig_id) : route('medications.index') }}" class="px-5 py-2 text-gray-600 rounded-lg transition hover:bg-gray-100 border border-gray-300">
                        Back to Pig Health
                    </a>
                    @can('correct', $medication)
                        <a href="{{ route('medications.edit', $medication) }}" class="px-5 py-2 bg-indigo-600 text-white font-semibold rounded-lg shadow-md transition hover:bg-indigo-700">
                            Correct Record
                        </a>
                    @endcan
                    @can('void', $medication)
                        <details class="w-full sm:w-auto">
                            <summary class="cursor-pointer rounded-lg border border-red-200 px-5 py-2 font-semibold text-red-700 hover:bg-red-50">Void Record</summary>
                            <form method="POST" action="{{ route('medications.void', $medication) }}" class="mt-3 space-y-2">
                                @csrf
                                <p class="text-xs text-gray-500">For a dose that was never given. The record stays, marked void, and its withdrawal stops applying.</p>
                                <input name="void_reason" required minlength="5" maxlength="2000" placeholder="Why is this record wrong?" class="block w-full rounded-lg border-gray-300 text-sm">
                                @error('void_reason')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                                <button class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white">Void record</button>
                            </form>
                        </details>
                    @endcan
                </div>
            </div>
        </div>
    </div>
</x-app-layout>