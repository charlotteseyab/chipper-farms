<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-10">
        <div class="bg-white p-6 rounded shadow">
            <h2 class="text-2xl font-semibold mb-6">Edit Health Record for Pig: <span class="font-mono">{{ $pig->ear_tag_number ?? $pig->code }}</span></h2>
            @php $latestRecord = optional($pig->healthRecords->first()); @endphp

            <form action="{{ route('health.update', $pig) }}" method="POST" class="space-y-4">
                @csrf
                @method('PUT')
                <div class="mb-4">
                <label for="condition" class="block text-sm font-medium text-gray-700">Condition</label>
                <input type="text" name="condition" id="condition" class="mt-1 block w-full border border-gray-300 rounded-md p-2" value="{{ old('condition', $latestRecord->condition) }}">
            </div>
            <div class="mb-4">
                <label for="severity" class="block text-sm font-medium text-gray-700">Severity</label>
                <input type="text" name="severity" id="severity" class="mt-1 block w-full border border-gray-300 rounded-md p-2" value="{{ old('severity', $latestRecord->severity) }}">
            </div>
            <div class="mb-4">
                <label for="symptoms" class="block text-sm font-medium text-gray-700">Symptoms</label>
                <textarea name="symptoms" id="symptoms" class="mt-1 block w-full border border-gray-300 rounded-md p-2">{{ old('symptoms', $latestRecord->symptoms) }}</textarea>
            </div>
            <div class="mb-4">
                <label for="diagnosis" class="block text-sm font-medium text-gray-700">Diagnosis</label>
                <input type="text" name="diagnosis" id="diagnosis" class="mt-1 block w-full border border-gray-300 rounded-md p-2" value="{{ old('diagnosis', $latestRecord->diagnosis) }}">
            </div>
            <div class="mb-4">
                <label for="treatment_plan" class="block text-sm font-medium text-gray-700">Treatment Plan</label>
                <textarea name="treatment_plan" id="treatment_plan" class="mt-1 block w-full border border-gray-300 rounded-md p-2">{{ old('treatment_plan', $latestRecord->treatment_plan) }}</textarea>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="mb-4">
                    <label for="treatment_status" class="block text-sm font-medium text-gray-700">Treatment Status</label>
                    <select name="treatment_status" id="treatment_status" class="mt-1 block w-full border border-gray-300 rounded-md p-2">
                        <option value="">Select status</option>
                        <option value="planned" @selected(old('treatment_status', $latestRecord->treatment_status) === 'planned')>Planned</option>
                        <option value="ongoing" @selected(old('treatment_status', $latestRecord->treatment_status) === 'ongoing')>Ongoing</option>
                        <option value="completed" @selected(old('treatment_status', $latestRecord->treatment_status) === 'completed')>Completed</option>
                        <option value="stopped" @selected(old('treatment_status', $latestRecord->treatment_status) === 'stopped')>Stopped</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label for="outcome" class="block text-sm font-medium text-gray-700">Outcome</label>
                    <select name="outcome" id="outcome" class="mt-1 block w-full border border-gray-300 rounded-md p-2">
                        <option value="">Select outcome</option>
                        <option value="recovered" @selected(old('outcome', $latestRecord->outcome) === 'recovered')>Recovered</option>
                        <option value="improved" @selected(old('outcome', $latestRecord->outcome) === 'improved')>Improved</option>
                        <option value="unchanged" @selected(old('outcome', $latestRecord->outcome) === 'unchanged')>Unchanged</option>
                        <option value="worsened" @selected(old('outcome', $latestRecord->outcome) === 'worsened')>Worsened</option>
                    </select>
                </div>
            </div>
            <div class="mb-4">
                <label for="resolved_at" class="block text-sm font-medium text-gray-700">Resolved Date</label>
                <input type="date" name="resolved_at" id="resolved_at" class="mt-1 block w-full border border-gray-300 rounded-md p-2" value="{{ old('resolved_at', optional($latestRecord->resolved_at)->toDateString()) }}">
            </div>
            <div class="mb-4">
                <label for="resolution_notes" class="block text-sm font-medium text-gray-700">Resolution Notes</label>
                <textarea name="resolution_notes" id="resolution_notes" class="mt-1 block w-full border border-gray-300 rounded-md p-2">{{ old('resolution_notes', $latestRecord->resolution_notes) }}</textarea>
            </div>
            <div>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Update Health Record</button>
                <a href="{{ route('health.show', $pig) }}" class="ml-4 text-gray-600 hover:underline">Cancel</a>
            </div>
        </form>
    </div>
</div>
</x-app-layout>
