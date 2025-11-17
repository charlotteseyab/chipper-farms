<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-xl shadow-2xl overflow-hidden border border-gray-100">

            <!-- <div class="bg-indigo-700 p-6 sm:p-8">
                <h1 class="text-3xl font-bold text-white tracking-tight">Medication Record Details</h1>
                <p class="text-indigo-200 mt-1 text-sm">Comprehensive view of the administered treatment.</p>
            </div> -->

            <div class="p-6 sm:p-8">

                <div class="mb-8 p-4 bg-yellow-50 border-l-4 border-yellow-500 rounded-lg">
                    <label class="block text-sm font-medium text-yellow-800 mb-1">Pig ID</label>
                    <div class="font-mono text-2xl font-bold text-yellow-900">
                        {{ $medication->pig->ear_tag_number ?? $medication->pig->code ?? '—' }}
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
                    <label class="block text-gray-500 text-sm font-medium uppercase tracking-wider mb-2">Notes</label>
                    <div class="whitespace-pre-line text-gray-700 bg-gray-50 p-3 rounded-md shadow-inner min-h-[5rem]">
                        {{ $medication->notes ?? 'No additional notes recorded.' }}
                    </div>
                </div>

                <div class="flex items-center justify-end space-x-3 border-t pt-6">
                    <a href="{{ url()->previous() }}" class="px-5 py-2 text-gray-600 rounded-lg transition hover:bg-gray-100 border border-gray-300">
                        Back to List
                    </a>
                    <a href="{{ route('medications.edit', $medication) }}" class="px-5 py-2 bg-indigo-600 text-white font-semibold rounded-lg shadow-md transition hover:bg-indigo-700 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        ✏️ Edit Record
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>