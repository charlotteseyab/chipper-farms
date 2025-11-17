<x-app-layout>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="bg-white rounded-xl shadow-2xl overflow-hidden border border-gray-100">

            <div class="bg-indigo-600 p-6 sm:p-8">
                    <!-- <h1 class="text-3xl font-bold text-white tracking-tight">Edit Medication Record</h1>
                    <p class="text-indigo-200 mt-1 text-sm">Update the details for this administration.</p> -->
                <label class="block text-sm font-medium text-black mb-1">Pig ID</label>
                    <div class="font-mono text-2xl font-bold text-black">
                        {{ $medication->pig->ear_tag_number ?? $medication->pig->code ?? '—' }}
                    </div>
            </div>

            <!-- <div class="mb-8 p-4 bg-yellow-50 border-l-4 border-yellow-500 rounded-lg">
                    <label class="block text-sm font-medium text-yellow-800 mb-1">Pig ID</label>
                    <div class="font-mono text-2xl font-bold text-yellow-900">
                        {{ $medication->pig->ear_tag_number ?? $medication->pig->code ?? '—' }}
                    </div>
                </div> -->

            <div class="p-6 sm:p-8">
                <form action="{{ route('medications.update', $medication) }}" method="POST" class="space-y-6">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label for="medication_name" class="block text-sm font-medium text-gray-700 mb-1">Medication Name</label>
                            <input type="text" name="medication_name" id="medication_name" value="{{ old('medication_name', $medication->medication_name) }}" required 
                                class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-3 focus:ring-indigo-500 focus:border-indigo-500 transition duration-150">
                        </div>
                        
                        <div>
                            <label for="administration_date" class="block text-sm font-medium text-gray-700 mb-1">Administration Date</label>
                            <input type="date" name="administration_date" id="administration_date" value="{{ old('administration_date', $medication->administration_date ? $medication->administration_date->format('Y-m-d') : '' ) }}" required 
                                class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-3 focus:ring-indigo-500 focus:border-indigo-500 transition duration-150">
                        </div>
                        
                        <div>
                            <label for="dosage" class="block text-sm font-medium text-gray-700 mb-1">Dosage</label>
                            <input type="text" name="dosage" id="dosage" value="{{ old('dosage', $medication->dosage) }}" required 
                                class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-3 focus:ring-indigo-500 focus:border-indigo-500 transition duration-150">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="purpose" class="block text-sm font-medium text-gray-700 mb-1">Purpose</label>
                            <input type="text" name="purpose" id="purpose" value="{{ old('purpose', $medication->purpose) }}" 
                                class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-3 focus:ring-indigo-500 focus:border-indigo-500 transition duration-150">
                        </div>
                        
                        <div>
                            <label for="route" class="block text-sm font-medium text-gray-700 mb-1">Route</label>
                            <input type="text" name="route" id="route" value="{{ old('route', $medication->route) }}" 
                                class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-3 focus:ring-indigo-500 focus:border-indigo-500 transition duration-150">
                        </div>
                    </div>

                    <div>
                        <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea name="notes" id="notes" rows="4" 
                            class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-3 focus:ring-indigo-500 focus:border-indigo-500 transition duration-150">{{ old('notes', $medication->notes) }}</textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-4 pt-4 border-t">
                        <a href="{{ route('medications.show', $medication) }}" class="px-5 py-2 text-gray-600 rounded-lg transition hover:bg-gray-100 border border-gray-300">
                            Cancel
                        </a>
                        <button type="submit" class="px-5 py-2 bg-indigo-600 text-white font-semibold rounded-lg shadow-md transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                            💾 Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>