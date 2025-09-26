<x-app-layout>
    <div class="min-h-screen py-6">
        <div class="max-w-6xl mx-auto px-4">
            
            <div class="bg-white rounded-xl shadow-xl border border-gray-100 overflow-hidden">
                
                <div class="px-6 py-4 border-b border-gray-200 bg-white flex items-center justify-between">
                    <h1 class="text-xl font-bold text-gray-900">Create Feed Schedule </h1>
                    <a href="{{ route('feeds.schedule') }}" 
                       class="inline-flex items-center text-sm font-medium text-indigo-600 hover:text-indigo-800 transition">
                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                        Back to Schedule
                    </a>
                </div>

                <form method="POST" action="{{ route('feeds.schedule.store') }}" class="p-6 space-y-6">
                    @csrf
                    
                    <div class="space-y-3 border-b pb-4">
                        <h2 class="text-base font-semibold text-gray-800">1. Select Target</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            
                            <div>
                                <label for="structure_id" class="block text-sm font-medium text-gray-700 mb-1">Structure <span class="text-red-500">*</span></label>
                                <select id="structure_id" name="structure_id" class="w-full border-gray-300 rounded-lg py-2 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500 transition">
                                    <option value="" disabled selected>-- Select Structure --</option>
                                    @foreach($structures as $s)
                                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            
                            <div>
                                <label for="feed_type_id" class="block text-sm font-medium text-gray-700 mb-1">Feed Type <span class="text-red-500">*</span></label>
                                <select id="feed_type_id" name="feed_type_id" class="w-full border-gray-300 rounded-lg py-2 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500 transition" required>
                                    <option value="" disabled selected>-- Select Feed Type --</option>
                                    @foreach($feedTypes as $ft)
                                        <option value="{{ $ft->id }}">{{ $ft->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    @if(isset($onHandMap) && $onHandMap->count())
                        <div class="text-xs bg-indigo-50 p-3 rounded-lg border border-indigo-200">
                            <h3 class="font-bold text-indigo-700 mb-2">Warehouse Stock Summary:</h3>
                            <div class="flex flex-wrap gap-2">
                                @foreach($feedTypes as $ft)
                                    @php($oh = $onHandMap[$ft->id] ?? null)
                                    <span class="px-2 py-0.5 bg-white border border-indigo-300 text-indigo-800 rounded-full text-xs font-medium shadow-sm">
                                        {{ $ft->name }}: 
                                        <span class="font-bold">{{ $oh ? number_format($oh->quantity, 2) : '0.00' }}</span> 
                                        <span class="text-gray-500">{{ $oh ? $oh->unit : '' }}</span>
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="space-y-3 border-b pb-4">
                         <h2 class="text-base font-semibold text-gray-800">2. Define Schedule Details</h2>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            
                            <div>
                                <label for="planned_amount" class="block text-sm font-medium text-gray-700 mb-1">Planned Amount <span class="text-red-500">*</span></label>
                                <input type="number" id="planned_amount" name="planned_amount" step="0.01" min="0.1" placeholder="e.g., 500.00" class="w-full border-gray-300 rounded-lg py-2 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500 transition" required>
                            </div>
                            
                            <div>
                                <label for="unit" class="block text-sm font-medium text-gray-700 mb-1">Unit</label>
                                <input type="text" id="unit" name="unit" value="kg" placeholder="kg" class="w-full border-gray-300 rounded-lg py-2 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500 transition" required>
                            </div>
                            
                            <div>
                                <label for="scheduled_for" class="block text-sm font-medium text-gray-700 mb-1">Scheduled Date <span class="text-red-500">*</span></label>
                                <input type="date" id="scheduled_for" name="scheduled_for" value="{{ now()->toDateString() }}" class="w-full border-gray-300 rounded-lg py-2 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500 transition" required>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h2 class="text-base font-semibold text-gray-800 mb-3">3. Add Notes (Optional)</h2>
                        <label for="notes" class="sr-only">Notes</label>
                        <textarea id="notes" name="notes" rows="3" placeholder="e.g., Use the higher protein feed this week due to cold weather." class="w-full border-gray-300 rounded-lg shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500 transition"></textarea>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-base rounded-lg shadow-lg transition duration-300 ease-in-out">
                            Schedule Feeding Event
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>