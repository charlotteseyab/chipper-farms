<x-app-layout>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 mb-10">
        <div class="mt-4 bg-white p-6 rounded-xl shadow">
            <h2 class="text-2xl font-semibold">Edit Health Record</h2>
            <p class="mt-1 text-sm text-gray-500">
                {{ $pig?->code }} · {{ $pig?->identityAt($record->record_date) }} · recorded {{ $record->record_date->format('d M Y') }}
            </p>
            <p class="mt-1 text-xs text-gray-500">Changes are saved to the audit trail with their earlier values.</p>

            @if(session('error'))<p role="alert" class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ session('error') }}</p>@endif
            @if($record->legacy_severity)
                <p class="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">Originally entered severity: “{{ $record->legacy_severity }}”.</p>
            @endif

            <form action="{{ route('health.records.update', $record) }}" method="POST" class="mt-6 space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label for="condition" class="block text-sm font-medium text-gray-700">Condition</label>
                    <input type="text" name="condition" id="condition" required maxlength="255" class="mt-1 block w-full rounded-md border-gray-300" value="{{ old('condition', $record->condition) }}">
                    @error('condition')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="severity" class="block text-sm font-medium text-gray-700">Severity</label>
                    <select name="severity" id="severity" class="mt-1 block w-full rounded-md border-gray-300">
                        <option value="">Not specified</option>
                        @foreach(\App\Models\HealthRecord::SEVERITIES as $value => $label)
                            <option value="{{ $value }}" @selected(old('severity', $record->severity) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('severity')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="symptoms" class="block text-sm font-medium text-gray-700">Symptoms</label>
                    <textarea name="symptoms" id="symptoms" rows="3" class="mt-1 block w-full rounded-md border-gray-300">{{ old('symptoms', $record->symptoms) }}</textarea>
                </div>
                <div>
                    <label for="diagnosis" class="block text-sm font-medium text-gray-700">Diagnosis</label>
                    <textarea name="diagnosis" id="diagnosis" rows="2" class="mt-1 block w-full rounded-md border-gray-300">{{ old('diagnosis', $record->diagnosis) }}</textarea>
                </div>
                <div>
                    <label for="treatment_plan" class="block text-sm font-medium text-gray-700">Treatment Plan</label>
                    <textarea name="treatment_plan" id="treatment_plan" rows="3" class="mt-1 block w-full rounded-md border-gray-300">{{ old('treatment_plan', $record->treatment_plan) }}</textarea>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="treatment_status" class="block text-sm font-medium text-gray-700">Treatment Status</label>
                        <select name="treatment_status" id="treatment_status" class="mt-1 block w-full rounded-md border-gray-300">
                            <option value="">Select status</option>
                            @foreach(\App\Models\HealthRecord::TREATMENT_STATUSES as $value => $label)
                                <option value="{{ $value }}" @selected(old('treatment_status', $record->treatment_status) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="outcome" class="block text-sm font-medium text-gray-700">Outcome</label>
                        <select name="outcome" id="outcome" class="mt-1 block w-full rounded-md border-gray-300">
                            <option value="">Select outcome</option>
                            @foreach(\App\Models\HealthRecord::OUTCOMES as $value => $label)
                                <option value="{{ $value }}" @selected(old('outcome', $record->outcome) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label for="resolved_at" class="block text-sm font-medium text-gray-700">Resolved Date</label>
                    <input type="date" name="resolved_at" id="resolved_at" min="{{ $record->record_date->toDateString() }}" max="{{ now()->toDateString() }}" class="mt-1 block w-full rounded-md border-gray-300" value="{{ old('resolved_at', optional($record->resolved_at)->toDateString()) }}">
                    @error('resolved_at')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="resolution_notes" class="block text-sm font-medium text-gray-700">Resolution Notes</label>
                    <textarea name="resolution_notes" id="resolution_notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300">{{ old('resolution_notes', $record->resolution_notes) }}</textarea>
                </div>
                <div class="flex items-center gap-4">
                    <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 font-semibold text-white hover:bg-indigo-700">Save changes</button>
                    <a href="{{ route('health.show', $record->pig_id) }}" class="text-gray-600 hover:underline">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
