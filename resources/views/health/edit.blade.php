<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-10">
        <div class="bg-white p-6 rounded shadow">
            <h2 class="text-2xl font-semibold mb-6">Edit Health Record for Pig: <span class="font-mono">{{ $pig->ear_tag_number ?? $pig->code }}</span></h2>

            <form action="{{ route('health.update', $pig) }}" method="POST" class="space-y-4">
                @csrf
                @method('PUT')
                <div class="mb-4">
                <label for="condition" class="block text-sm font-medium text-gray-700">Condition</label>
                <input type="text" name="condition" id="condition" class="mt-1 block w-full border border-gray-300 rounded-md p-2" value="{{ old('condition', optional($pig->healthRecords->first())->condition) }}">
            </div>
            <div class="mb-4">
                <label for="severity" class="block text-sm font-medium text-gray-700">Severity</label>
                <input type="text" name="severity" id="severity" class="mt-1 block w-full border border-gray-300 rounded-md p-2" value="{{ old('severity', optional($pig->healthRecords->first())->severity) }}">
            </div>
            <div class="mb-4">
                <label for="symptoms" class="block text-sm font-medium text-gray-700">Symptoms</label>
                <textarea name="symptoms" id="symptoms" class="mt-1 block w-full border border-gray-300 rounded-md p-2">{{ old('symptoms', optional($pig->healthRecords->first())->symptoms) }}</textarea>
            </div>
            <div class="mb-4">
                <label for="diagnosis" class="block text-sm font-medium text-gray-700">Diagnosis</label>
                <input type="text" name="diagnosis" id="diagnosis" class="mt-1 block w-full border border-gray-300 rounded-md p-2" value="{{ old('diagnosis', optional($pig->healthRecords->first())->diagnosis) }}">
            </div>
            <div class="mb-4">
                <label for="treatment_plan" class="block text-sm font-medium text-gray-700">Treatment Plan</label>
                <textarea name="treatment_plan" id="treatment_plan" class="mt-1 block w-full border border-gray-300 rounded-md p-2">{{ old('treatment_plan', optional($pig->healthRecords->first())->treatment_plan) }}</textarea>
            </div>
            <div>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Update Health Record</button>
                <a href="{{ route('health.show', $pig) }}" class="ml-4 text-gray-600 hover:underline">Cancel</a>
            </div>
        </form>
    </div>
</div>
</x-app-layout>
