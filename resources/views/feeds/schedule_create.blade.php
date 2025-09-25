<x-app-layout>
    <div class="max-w-5xl mx-auto px-4 py-8">
        <div class="bg-white rounded-xl shadow-md border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h1 class="text-xl font-semibold text-gray-900">Create Feed Schedule</h1>
                <a href="{{ route('feeds.schedule') }}" class="text-blue-600 hover:underline">← Back to Schedule</a>
            </div>

            <form method="POST" action="{{ route('feeds.schedule.store') }}" class="p-6 space-y-6">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Structure <span class="text-red-500">*</span></label>
                        <select name="structure_id" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="" disabled selected>-- Select --</option>
                            @foreach($structures as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Feed Type <span class="text-red-500">*</span></label>
                        <select name="feed_type_id" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500" required>
                            <option value="" disabled selected>-- Select --</option>
                            @foreach($feedTypes as $ft)
                                <option value="{{ $ft->id }}">{{ $ft->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @if(isset($onHandMap) && $onHandMap->count())
                    <div class="text-sm text-gray-600 bg-gray-50 p-3 rounded-lg">
                        <span class="font-medium">On-hand stock by feed type:</span>
                        <div class="flex flex-wrap gap-2 mt-2">
                            @foreach($feedTypes as $ft)
                                @php($oh = $onHandMap[$ft->id] ?? null)
                                <span class="px-2 py-1 bg-white border border-gray-200 rounded-md text-xs">
                                    {{ $ft->name }}: {{ $oh ? number_format($oh->quantity, 2) . ' ' . $oh->unit : '0.00' }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                        <input type="number" name="planned_amount" step="0.01" min="0.1" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Unit</label>
                        <input type="text" name="unit" value="kg" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                        <input type="date" name="scheduled_for" value="{{ now()->toDateString() }}" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="3" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg shadow-sm transition">Schedule</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
